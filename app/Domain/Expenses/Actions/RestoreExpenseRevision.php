<?php

namespace App\Domain\Expenses\Actions;

use App\Domain\Expenses\Actions\Concerns\ManagesExpenseAggregate;
use App\Domain\Expenses\Data\SaveExpenseData;
use App\Domain\Expenses\Data\SaveExpenseRowData;
use App\Domain\Expenses\Enums\Distribution;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Revisions\Queries\OperationalRevisionQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\RevisionBatch;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class RestoreExpenseRevision
{
    use ManagesExpenseAggregate;

    public function execute(
        User $actor,
        TenantContext $context,
        Expense $target,
        RevisionBatch $source,
        int $expectedLockVersion,
        string $correlationId,
    ): Expense {
        $this->expensePolicy($context)->restoreRevision($actor, $target)->authorize();
        [$actor, $tenant] = $this->persistedContext($actor, $context);

        return DB::transaction(function () use ($actor, $context, $correlationId, $expectedLockVersion, $source, $target, $tenant): Expense {
            $expense = Expense::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->find($target->getKey());
            if (! $expense instanceof Expense || $expense->lock_version !== $expectedLockVersion) {
                throw new DomainException('STALE_VERSION');
            }

            $logical = app(OperationalRevisionQuery::class);
            $batch = $logical->findVisibleBatch($context, $expense, (int) $source->getKey());
            $state = $logical->snapshot($context, $expense, $batch);
            $snapshot = $state[$expense->getMorphClass()][(int) $expense->getKey()] ?? null;
            if (! is_array($snapshot)) {
                throw new DomainException('REVISION_RESTORE_INVALID');
            }
            $kind = ExpenseKind::tryFrom((string) ($snapshot['kind'] ?? ''));
            if (! $kind instanceof ExpenseKind) {
                throw new DomainException('REVISION_RESTORE_INVALID');
            }

            $currentRows = $expense->rows()->lockForUpdate()->get()->keyBy(fn (ExpenseRow $row): int => (int) $row->getKey());
            $rowType = (new ExpenseRow)->getMorphClass();
            $historicalRows = $state[$rowType] ?? [];
            $rows = [];
            foreach ($historicalRows as $historicalId => $contents) {
                $current = $currentRows->get((int) $historicalId);
                $type = ExpenseType::tryFrom((string) ($contents['type'] ?? ''));
                $hasDistribution = isset($contents['distribution']);
                $distribution = $hasDistribution
                    ? Distribution::tryFrom((string) $contents['distribution']) : null;
                if (! $type instanceof ExpenseType || ($hasDistribution && ! $distribution instanceof Distribution)) {
                    throw new DomainException('REVISION_RESTORE_INVALID');
                }
                $rows[] = new SaveExpenseRowData(
                    id: $current instanceof ExpenseRow ? (int) $current->getKey() : null,
                    position: (int) ($contents['position'] ?? 0),
                    vendorId: isset($contents['vendor_id']) ? (int) $contents['vendor_id'] : null,
                    type: $type,
                    description: (string) ($contents['description'] ?? ''),
                    quantity: $this->nullableDecimal($contents['quantity'] ?? null),
                    unitPrice: $this->nullableDecimal($contents['unit_price'] ?? null),
                    enteredAmount: $this->legacyDecimal($contents['entered_amount'] ?? ''),
                    amountIncludesVat: (bool) ($contents['amount_includes_vat'] ?? false),
                    vatRate: $this->legacyDecimal($contents['vat_rate'] ?? ''),
                    isExtra: (bool) ($contents['is_extra'] ?? false),
                    fundedPlafondExpenseId: isset($contents['funded_plafond_expense_id']) ? (int) $contents['funded_plafond_expense_id'] : null,
                    spendDate: $this->nullableString($contents['spend_date'] ?? null),
                    periodStart: $this->nullableString($contents['period_start'] ?? null),
                    periodEnd: $this->nullableString($contents['period_end'] ?? null),
                    distribution: $distribution,
                    externalReference: $this->nullableString($contents['external_reference'] ?? null),
                    expectedLockVersion: $current?->lock_version,
                    isCurrentPlanning: (int) ($snapshot['current_planning_row_id'] ?? 0) === (int) $historicalId,
                );
            }
            $historicalIds = array_map('intval', array_keys($historicalRows));
            $deletedRows = $currentRows
                ->reject(fn (ExpenseRow $row): bool => in_array((int) $row->getKey(), $historicalIds, true))
                ->map(fn (ExpenseRow $row): array => ['id' => (int) $row->getKey(), 'lock_version' => (int) $row->lock_version])
                ->values()->all();

            $data = new SaveExpenseData(
                planningYearId: (int) ($snapshot['planning_year_id'] ?? 0),
                costCenterId: (int) ($snapshot['cost_center_id'] ?? 0),
                kind: $kind,
                title: (string) ($snapshot['title'] ?? ''),
                notes: $this->nullableString($snapshot['notes'] ?? null),
                projectId: isset($snapshot['project_id']) ? (int) $snapshot['project_id'] : null,
                contractId: isset($snapshot['contract_id']) ? (int) $snapshot['contract_id'] : null,
                expectedLockVersion: $expectedLockVersion,
                creditForExpenseId: isset($snapshot['credit_for_expense_id']) ? (int) $snapshot['credit_for_expense_id'] : null,
            );
            $changed = $this->saveAggregate($expense, $tenant, $data, $rows, $actor, $deletedRows);
            if ($changed === []) {
                throw new DomainException('REVISION_RESTORE_INVALID');
            }
            $this->revisions($actor, $context, RevisionOperation::Restore, $correlationId, $expense, $changed, (int) $batch->getKey());
            $this->audit('expense.restored', $correlationId, $actor, $tenant, $expense, ['restored_from_batch_id' => (int) $batch->getKey()]);
            $this->purgeDeletedRowAttachments($actor, $context, $deletedRows, $correlationId);

            return $expense->fresh(['rows']);
        });
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }

    private function nullableDecimal(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : $this->legacyDecimal($value);
    }

    private function legacyDecimal(mixed $value): string
    {
        $decimal = (string) $value;

        return preg_match('/^-?\d+(?:\.\d{1,2}0*)?$/D', $decimal) === 1
            ? bcadd($decimal, '0', 2)
            : $decimal;
    }
}
