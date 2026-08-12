<?php

namespace App\Domain\Expenses\Services;

use App\Domain\Economics\Data\EconomicMeasure;
use App\Domain\Expenses\Data\SaveExpenseData;
use App\Domain\Expenses\Data\SaveExpenseRowData;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Tenant;
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
}
