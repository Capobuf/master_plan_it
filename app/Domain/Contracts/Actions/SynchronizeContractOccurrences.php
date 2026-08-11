<?php

namespace App\Domain\Contracts\Actions;

use App\Domain\Contracts\Actions\Concerns\ManagesContracts;
use App\Domain\Contracts\Queries\ExpectedContractOccurrenceQuery;
use App\Domain\Expenses\Actions\Concerns\ManagesExpenseAggregate;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Money\Money;
use App\Domain\Money\Services\VatCalculator;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Contract;
use App\Models\ContractTerm;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SynchronizeContractOccurrences
{
    use ManagesContracts, ManagesExpenseAggregate;

    /** @return array{created:int,updated:int,skipped:int} */
    public function execute(User $actor, TenantContext $context, Contract $contract, string $correlationId): array
    {
        $this->contractPolicy($context)->generateOccurrence($actor, $contract)->authorize();
        [$actor] = $this->persistedContractContext($actor, $context);

        return DB::transaction(function () use ($actor, $context, $contract, $correlationId): array {
            $contract = Contract::query()->where('tenant_id', $context->tenantId)->lockForUpdate()->find($contract->getKey());
            if (! $contract instanceof Contract || ! $contract->active) {
                throw new \DomainException('GENERATION_NOT_APPLICABLE');
            }
            $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0];
            foreach (app(ExpectedContractOccurrenceQuery::class)->forContract($contract) as $expected) {
                $occurrenceCorrelationId = (string) Str::uuid();
                if ($expected->suppressed) {
                    $counts['skipped']++;

                    continue;
                }
                if ($expected->expenseId === null) {
                    if (! PlanningYear::query()->where('tenant_id', $context->tenantId)->where('year_label', $expected->planningYear)->exists()) {
                        $counts['skipped']++;

                        continue;
                    }
                    app(GenerateContractOccurrenceForYear::class)->generateExpected($actor, $context, $contract, $expected, $occurrenceCorrelationId);
                    $counts['created']++;

                    continue;
                }
                $row = ExpenseRow::query()->where('tenant_id', $context->tenantId)->where('source_key', $expected->sourceKey)->lockForUpdate()->first();
                $expense = $row instanceof ExpenseRow
                    ? Expense::query()->where('tenant_id', $context->tenantId)->lockForUpdate()->find($row->expense_id)
                    : null;
                if (! $row instanceof ExpenseRow || ! $expense instanceof Expense || $row->type !== ExpenseType::Quote || ! $row->is_system_managed
                    || $row->manual_override_at !== null || (int) $expense->current_planning_row_id === (int) $row->getKey()) {
                    $counts['skipped']++;

                    continue;
                }
                $term = ContractTerm::query()->where('tenant_id', $context->tenantId)->find($expected->termId);
                if (! $term instanceof ContractTerm) {
                    $counts['skipped']++;

                    continue;
                }
                $vatRate = (new VatCalculator)->rateFromAmounts(
                    Money::fromDecimal($expected->netAmount, $context->currencyCode),
                    Money::fromDecimal($expected->vatAmount, $context->currencyCode),
                );
                $row->fill(['vendor_id' => $contract->vendor_id, 'description' => $contract->title, 'quantity' => null, 'unit_price' => null, 'entered_amount' => $expected->netAmount, 'amount_includes_vat' => false, 'vat_rate' => $vatRate, 'lock_version' => $row->lock_version + 1]);
                $row->forceFill(['net_amount' => $expected->netAmount, 'vat_amount' => $expected->vatAmount, 'gross_amount' => $expected->grossAmount])->save();
                $expense->fill(['cost_center_id' => $contract->cost_center_id, 'title' => $contract->title.' — '.$expected->occurrenceDate, 'lock_version' => $expense->lock_version + 1])->save();
                $this->revisions($actor, $context, RevisionOperation::Update, $occurrenceCorrelationId, $expense, [$expense, $row]);
                $counts['updated']++;
            }
            $this->contractAudit('contract.synchronized', $correlationId, $actor, $context->tenant, $contract, $counts);

            return $counts;
        });
    }
}
