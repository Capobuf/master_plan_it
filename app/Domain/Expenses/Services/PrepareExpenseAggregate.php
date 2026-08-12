<?php

namespace App\Domain\Expenses\Services;

use App\Domain\Economics\Data\EconomicMeasure;
use App\Domain\Economics\Data\PlafondEconomicProjection;
use App\Domain\Economics\Data\PlafondImpact;
use App\Domain\Economics\Data\ProjectedEconomicLine;
use App\Domain\Economics\Services\EconomicEngine;
use App\Domain\Economics\Services\MoneyCalculator;
use App\Domain\Expenses\Data\SaveExpenseData;
use App\Domain\Expenses\Data\SaveExpenseRowData;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Reporting\Queries\EconomicDatasetQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use DomainException;
use Illuminate\Validation\ValidationException;

final class PrepareExpenseAggregate
{
    /**
     * @param  list<SaveExpenseRowData>  $rows
     * @param  list<array{id: int, lock_version: int}>  $deletedRows
     * @return array<string, mixed>
     */
    public function prepare(
        Tenant $tenant,
        SaveExpenseData $data,
        array $rows,
        ?Expense $currentExpense = null,
        array $deletedRows = [],
        ?User $actor = null,
        ?TenantContext $context = null,
    ): array {
        $validated = app(ExpenseAggregateValidator::class)->validate($tenant, $data, $rows, $currentExpense);
        $this->validateVersionsAndMembership($currentExpense, $data->expectedLockVersion, $rows, $deletedRows);

        $year = PlanningYear::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereKey($data->planningYearId)
            ->firstOrFail();
        $basis = (string) $tenant->getRawOriginal('budget_basis');
        $planning = EconomicMeasure::zero($basis);
        $actual = EconomicMeasure::zero($basis);
        $vendorNames = Vendor::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereKey(collect($validated['rows'])->pluck('vendor_id')->filter()->unique()->all())
            ->pluck('name', 'id');
        $preparedRows = [];

        foreach ($validated['rows'] as $row) {
            $amount = EconomicMeasure::fromAmounts(
                (string) $row['net_amount'],
                (string) $row['vat_amount'],
                (string) $row['gross_amount'],
                $basis,
            );
            if ((bool) $row['is_current_planning']) {
                $planning = $planning->plus($amount, $basis);
            }
            $type = $row['type'];
            if ($type === ExpenseType::Actual || $type === ExpenseType::Actual->value) {
                $actual = $actual->plus($amount, $basis);
            }

            $preparedRows[] = [
                ...$row,
                'type' => $type instanceof ExpenseType ? $type->value : (string) $type,
                'vendor_name' => $row['vendor_id'] === null ? null : $vendorNames->get((int) $row['vendor_id']),
                'lock_version' => $row['expected_lock_version'],
                'amount' => $this->measure($amount),
            ];
        }

        $plafondImpacts = [];
        if ($actor instanceof User && $context instanceof TenantContext) {
            $currentAnnual = app(EconomicEngine::class)->project(
                app(EconomicDatasetQuery::class)->execute($actor, $context, $data->planningYearId),
            );
            $proposedAnnual = app(ProposedExpenseProjection::class)->project(
                $actor,
                $context,
                $tenant,
                $data,
                $validated,
                $currentExpense,
            );
            $affectedIds = collect($this->currentFundingIds($currentExpense))
                ->merge(collect($validated['rows'])->pluck('funded_plafond_expense_id')->filter())
                ->map(static fn ($id): int => (int) $id)
                ->unique()
                ->sort()
                ->values();
            $proposalExpenseId = $currentExpense instanceof Expense
                ? (int) $currentExpense->getKey()
                : -1;
            foreach ($affectedIds as $plafondId) {
                $currentPlafond = $currentAnnual->plafonds[$plafondId] ?? null;
                $proposedPlafond = $proposedAnnual->plafonds[$plafondId] ?? null;
                if ($currentPlafond === null || $proposedPlafond === null) {
                    throw new DomainException('ECONOMIC_RECONCILIATION_FAILED');
                }
                $requested = '0.00';
                foreach ($proposedPlafond->coveredLines as $line) {
                    if ($line->expenseId === $proposalExpenseId) {
                        $requested = (new MoneyCalculator)->add($requested, $line->amount->official);
                    }
                }
                $computed = app(PlafondCapacityService::class)->impact(
                    $currentPlafond,
                    $proposedPlafond,
                    $requested,
                );
                $plafondImpacts[] = $this->impact(new PlafondImpact(
                    $computed->current,
                    $computed->proposed,
                    $computed->requested,
                    $computed->shortage,
                    $computed->canConfirm,
                    [],
                ));
            }
        }

        return [
            'planning_year_id' => $data->planningYearId,
            'economic_year_label' => (int) $year->year_label,
            'currency' => (string) $tenant->currency_code,
            'basis' => $basis,
            'current_planning_row_position' => collect($preparedRows)->firstWhere('is_current_planning', true)['position'] ?? null,
            'totals' => [
                'current_planning' => $this->measure($planning),
                'actual' => $this->measure($actual),
            ],
            'rows' => $preparedRows,
            'plafond_impacts' => $plafondImpacts,
        ];
    }

    /**
     * @param  list<SaveExpenseRowData>  $rows
     * @param  list<array{id: int, lock_version: int}>  $deletedRows
     */
    private function validateVersionsAndMembership(?Expense $expense, ?int $expectedRootVersion, array $rows, array $deletedRows): void
    {
        $submitted = collect($rows)->filter(static fn (SaveExpenseRowData $row): bool => $row->id !== null)
            ->mapWithKeys(static fn (SaveExpenseRowData $row): array => [(int) $row->id => $row->expectedLockVersion]);
        $deleted = collect($deletedRows)->mapWithKeys(static fn (array $row): array => [(int) $row['id'] => (int) $row['lock_version']]);

        if (! $expense instanceof Expense) {
            if ($submitted->isNotEmpty() || $deleted->isNotEmpty()) {
                $this->invalidRows();
            }

            return;
        }

        if ($expectedRootVersion === null || (int) $expense->lock_version !== $expectedRootVersion) {
            throw new DomainException('STALE_VERSION');
        }

        $existing = $expense->rows()->orderBy('id')->get(['id', 'lock_version'])->keyBy('id');
        $addressed = $submitted->keys()->merge($deleted->keys());
        if ($addressed->duplicates()->isNotEmpty()
            || $addressed->sort()->values()->all() !== $existing->keys()->sort()->values()->all()) {
            $this->invalidRows();
        }
        foreach ($submitted->merge($deleted) as $id => $version) {
            $row = $existing->get((int) $id);
            if (! $row instanceof ExpenseRow || $version === null || (int) $row->lock_version !== (int) $version) {
                throw new DomainException('STALE_VERSION');
            }
        }
    }

    private function invalidRows(): never
    {
        throw ValidationException::withMessages([
            'rows' => 'The submitted expense rows are invalid.',
        ]);
    }

    /** @return array{net: string, vat: string, gross: string, official: string} */
    private function measure(EconomicMeasure $measure): array
    {
        return [
            'net' => $measure->net,
            'vat' => $measure->vat,
            'gross' => $measure->gross,
            'official' => $measure->official,
        ];
    }

    /** @return list<int> */
    private function currentFundingIds(?Expense $expense): array
    {
        if (! $expense instanceof Expense) {
            return [];
        }

        return $expense->rows()
            ->whereNotNull('funded_plafond_expense_id')
            ->pluck('funded_plafond_expense_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->all();
    }

    /** @return array<string, mixed> */
    private function impact(PlafondImpact $impact): array
    {
        return [
            'plafond' => [
                'id' => $impact->current->plafondExpenseId,
                'title' => $impact->current->title,
            ],
            'currency' => $impact->current->currency,
            'basis' => $impact->current->basis,
            'current' => $this->plafondMeasures($impact->current),
            'proposed' => $this->plafondMeasures($impact->proposed),
            'requested' => $impact->requested,
            'shortage' => $impact->shortage,
            'can_confirm' => $impact->canConfirm,
            'blocking_rows' => array_map(
                fn (ProjectedEconomicLine $line): array => $this->coveredLine($line),
                $impact->blockingRows,
            ),
        ];
    }

    /** @return array<string, array{net: string, vat: string, gross: string, official: string}> */
    private function plafondMeasures(PlafondEconomicProjection $projection): array
    {
        return [
            'allocation' => $this->measure($projection->allocation),
            'coverage_planned' => $this->measure($projection->coveragePlanned),
            'consumed' => $this->measure($projection->consumed),
            'available' => $this->measure($projection->available),
        ];
    }

    /** @return array<string, mixed> */
    private function coveredLine(ProjectedEconomicLine $line): array
    {
        return [
            'expense_id' => $line->expenseId,
            'expense_title' => $line->expenseTitle,
            'row_id' => $line->rowId,
            'description' => $line->description,
            'type' => $line->type,
            'date' => $line->spendDate,
            'contributes_to_coverage_planned' => $line->contributesToCoveragePlanned,
            'contributes_to_consumed' => $line->contributesToConsumed,
            'expense_cost_center' => ['id' => $line->costCenterId, 'name' => $line->costCenterName],
            'plafond_cost_center' => [
                'id' => $line->plafondCostCenterId,
                'name' => $line->plafondCostCenterName,
            ],
            'amount' => $this->measure($line->amount),
        ];
    }
}
