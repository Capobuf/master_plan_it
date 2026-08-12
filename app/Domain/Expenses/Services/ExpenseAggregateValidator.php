<?php

namespace App\Domain\Expenses\Services;

use App\Domain\Economics\Services\MoneyCalculator;
use App\Domain\Economics\Services\VatCalculator;
use App\Domain\Expenses\Data\SaveExpenseData;
use App\Domain\Expenses\Data\SaveExpenseRowData;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Models\Contract;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\PlanningYear;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Vendor;
use DateTimeImmutable;
use Illuminate\Validation\ValidationException;

final class ExpenseAggregateValidator
{
    /**
     * @param  list<SaveExpenseRowData>  $rows
     * @return array{header: array<string, mixed>, rows: list<array<string, mixed>>}
     */
    public function validate(Tenant $tenant, SaveExpenseData $data, array $rows, ?Expense $currentExpense = null): array
    {
        if (trim($data->title) === '' || mb_strlen($data->title) > 255) {
            $this->fail('title', 'The title must be between 1 and 255 characters.');
        }
        if ($rows === []) {
            $this->fail('rows', 'At least one expense row is required.');
        }

        $year = PlanningYear::query()->where('tenant_id', $tenant->getKey())->whereKey($data->planningYearId)->first();
        if (! $year instanceof PlanningYear) {
            $this->fail('planning_year_id', 'The selected planning year is invalid.');
        }
        if (! $this->isCurrentOrActive($year->active, $currentExpense?->planning_year_id, $data->planningYearId)) {
            $this->fail('planning_year_id', 'The selected planning year is inactive.');
        }
        $costCenter = CostCenter::query()->where('tenant_id', $tenant->getKey())->whereKey($data->costCenterId)->first();
        if (! $costCenter instanceof CostCenter) {
            $this->fail('cost_center_id', 'The selected cost center is invalid.');
        }
        if (! $this->isCurrentOrActive($costCenter->active, $currentExpense?->cost_center_id, $data->costCenterId)) {
            $this->fail('cost_center_id', 'The selected cost center is inactive.');
        }
        $contract = $data->contractId === null ? null : Contract::query()->where('tenant_id', $tenant->getKey())->whereKey($data->contractId)->first();
        if ($data->contractId !== null && ! $contract instanceof Contract) {
            $preservedGeneratedSource = $currentExpense?->exists === true
                && (int) $currentExpense->getRawOriginal('contract_id') === $data->contractId
                && $currentExpense->rows()->whereNotNull('source_key')->exists()
                && Contract::withTrashed()
                    ->where('tenant_id', $tenant->getKey())
                    ->whereKey($data->contractId)
                    ->whereNotNull('deleted_at')
                    ->exists();

            if (! $preservedGeneratedSource) {
                $this->fail('contract_id', 'The selected contract is invalid.');
            }
        }
        if ($data->projectId !== null && ! Project::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereKey($data->projectId)
            ->exists()) {
            $this->fail('project_id', 'The selected project is invalid.');
        }
        if ($currentExpense instanceof Expense
            && $this->nullableId($currentExpense->credit_for_expense_id) !== $data->creditForExpenseId) {
            $this->fail('credit_for_expense_id', 'The credit origin cannot be changed after creation.');
        }
        $creditFor = $data->creditForExpenseId === null ? null : Expense::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereKey($data->creditForExpenseId)
            ->with('planningYear')
            ->first();
        if ($data->creditForExpenseId !== null && ! $creditFor instanceof Expense) {
            $this->fail('credit_for_expense_id', 'The selected credit origin is invalid.');
        }
        if ($creditFor instanceof Expense
            && (! $creditFor->planningYear instanceof PlanningYear
                || (int) $year->year_label <= (int) $creditFor->planningYear->year_label)) {
            $this->fail('credit_for_expense_id', 'A credit must belong to a year after its origin expense.');
        }

        $normalized = [];
        $ids = [];
        $positions = [];
        $selectedPlanningCount = 0;
        $planningCount = 0;
        foreach ($rows as $index => $row) {
            if (! $row instanceof SaveExpenseRowData) {
                $this->fail("rows.{$index}", 'The expense row is invalid.');
            }
            if ($row->id !== null && isset($ids[$row->id])) {
                $this->fail("rows.{$index}.id", 'The expense row is duplicated.');
            }
            $ids[$row->id ?? -($index + 1)] = true;
            if (isset($positions[$row->position])) {
                $this->fail("rows.{$index}.position", 'Each current expense row requires a unique position.');
            }
            $positions[$row->position] = true;
            if ($row->isCurrentPlanning) {
                $selectedPlanningCount++;
                if (! in_array($row->type, [ExpenseType::Estimate, ExpenseType::Quote], true)) {
                    $this->fail("rows.{$index}.is_current_planning", 'Only an Estimate or Quote may be the current planning row.');
                }
            }
            if (in_array($row->type, [ExpenseType::Estimate, ExpenseType::Quote], true)) {
                $planningCount++;
            }
            $normalized[] = $this->row($tenant, $year, $data->kind, $row, $index, $currentExpense);
        }
        if ($creditFor instanceof Expense) {
            foreach ($rows as $index => $row) {
                $normalizedAmount = $normalized[$index]['entered_amount'] ?? '0.00';
                if ($row->type !== ExpenseType::Actual || bccomp((string) $normalizedAmount, '0', MoneyCalculator::SCALE) >= 0) {
                    $this->fail("rows.{$index}.entered_amount", 'A linked next-year credit accepts only negative Actual rows.');
                }
            }
        }
        if (($planningCount === 0 && $selectedPlanningCount !== 0)
            || ($planningCount > 0 && $selectedPlanningCount !== 1)) {
            $this->fail('rows', 'Exactly one current planning row is required when planning rows exist.');
        }

        return [
            'header' => [
                'planning_year_id' => $data->planningYearId,
                'cost_center_id' => $data->costCenterId,
                'kind' => $data->kind,
                'title' => trim($data->title),
                'notes' => $this->nullableText($data->notes),
                'project_id' => $data->projectId,
                'contract_id' => $data->contractId,
                'credit_for_expense_id' => $data->creditForExpenseId,
            ],
            'rows' => $normalized,
        ];
    }

    /** @return array<string, mixed> */
    private function row(Tenant $tenant, PlanningYear $year, ExpenseKind $kind, SaveExpenseRowData $row, int $index, ?Expense $currentExpense): array
    {
        $prefix = "rows.{$index}";
        if (trim($row->description) === '' || mb_strlen($row->description) > 255) {
            $this->fail("{$prefix}.description", 'A row description is required.');
        }
        if ($row->position < 1) {
            $this->fail("{$prefix}.position", 'The row position is invalid.');
        }
        if ($kind === ExpenseKind::Ordinary && $row->vendorId === null) {
            $this->fail("{$prefix}.vendor_id", 'Ordinary expense rows require a vendor.');
        }
        $vendor = $row->vendorId === null ? null : Vendor::query()->where('tenant_id', $tenant->getKey())->whereKey($row->vendorId)->first();
        if ($row->vendorId !== null && ! $vendor instanceof Vendor) {
            $this->fail("{$prefix}.vendor_id", 'The selected vendor is invalid.');
        }
        if ($vendor instanceof Vendor && ! $vendor->active && ! $this->isCurrentRowVendor($currentExpense, $row->id, $vendor->getKey())) {
            $this->fail("{$prefix}.vendor_id", 'The selected vendor is inactive.');
        }
        if ($row->isExtra && $row->fundedPlafondExpenseId !== null) {
            $this->fail("{$prefix}.funded_plafond_expense_id", 'Extra and funded Plafond are mutually exclusive.');
        }
        if ($row->fundedPlafondExpenseId !== null && ! Expense::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('planning_year_id', $year->getKey())
            ->where('kind', ExpenseKind::Plafond->value)
            ->whereKey($row->fundedPlafondExpenseId)
            ->exists()) {
            $this->fail("{$prefix}.funded_plafond_expense_id", 'The funded Plafond is invalid.');
        }

        $this->dateShape($row, $prefix, $year);
        $hasEntered = $row->enteredAmount !== null;
        $hasQuantity = $row->quantity !== null;
        $hasUnitPrice = $row->unitPrice !== null;
        if (! (($hasEntered && ! $hasQuantity && ! $hasUnitPrice)
            || (! $hasEntered && $hasQuantity && $hasUnitPrice))) {
            $this->fail("{$prefix}.entered_amount", 'Use either entered amount or quantity and unit price.');
        }

        $quantity = $this->nullablePlainDecimal($row->quantity, "{$prefix}.quantity");
        $unitPrice = $this->nullableMoney($row->unitPrice, "{$prefix}.unit_price");
        if ($hasEntered) {
            $entered = $this->money((string) $row->enteredAmount, "{$prefix}.entered_amount");
        } else {
            $entered = (new MoneyCalculator)->multiply(
                (string) $unitPrice,
                (string) $quantity,
            );
        }
        if ($row->type !== ExpenseType::Actual && bccomp($entered, '0', MoneyCalculator::SCALE) < 0) {
            $this->fail("{$prefix}.entered_amount", 'Estimate and Quote amounts cannot be negative.');
        }
        $persistedVatRate = $row->id === null || ! $currentExpense instanceof Expense
            ? null
            : $currentExpense->rows()->whereKey($row->id)->value('vat_rate');
        $vatRate = $this->plainDecimal(
            trim($row->vatRate) === ''
                ? (string) ($persistedVatRate ?? $tenant->default_vat_rate)
                : $row->vatRate,
            "{$prefix}.vat_rate",
            false,
            10,
        );
        $vatCalculator = new VatCalculator(new MoneyCalculator);
        $basis = (string) $tenant->getRawOriginal('budget_basis');
        $breakdown = $row->amountIncludesVat
            ? $vatCalculator->fromIncluded($entered, $vatRate, $basis)
            : $vatCalculator->fromExcluded($entered, $vatRate, $basis);

        return [
            'id' => $row->id,
            'position' => $row->position,
            'vendor_id' => $row->vendorId,
            'type' => $row->type,
            'description' => trim($row->description),
            'notes' => $this->nullableText($row->notes),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'entered_amount' => $entered,
            'amount_includes_vat' => $row->amountIncludesVat,
            'vat_rate' => $vatRate,
            'net_amount' => $breakdown->net,
            'vat_amount' => $breakdown->vat,
            'gross_amount' => $breakdown->gross,
            'is_extra' => $row->isExtra,
            'funded_plafond_expense_id' => $row->fundedPlafondExpenseId,
            'spend_date' => $row->spendDate,
            'period_start' => $row->periodStart,
            'period_end' => $row->periodEnd,
            'distribution' => $row->distribution,
            'external_reference' => $this->nullableText($row->externalReference),
            'expected_lock_version' => $row->expectedLockVersion,
            'is_current_planning' => $row->isCurrentPlanning,
        ];
    }

    private function dateShape(SaveExpenseRowData $row, string $prefix, PlanningYear $year): void
    {
        $spend = $row->spendDate !== null;
        $periodAny = $row->periodStart !== null || $row->periodEnd !== null || $row->distribution !== null;
        if ($periodAny) {
            $this->fail("{$prefix}.period_start", 'Automatic period distribution is not supported.');
        }
        if ($row->type === ExpenseType::Actual && ! $spend) {
            $this->fail("{$prefix}.spend_date", 'An Actual row requires its economic date.');
        }
        if ($row->type !== ExpenseType::Actual && $spend) {
            $this->fail("{$prefix}.spend_date", 'Only an Actual row may have a spend date.');
        }
        try {
            if ($spend) {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $row->spendDate);
                $errors = DateTimeImmutable::getLastErrors();
                if (! $date instanceof DateTimeImmutable
                    || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
                    || $date->format('Y-m-d') !== $row->spendDate) {
                    $this->fail("{$prefix}.spend_date", 'The row date is invalid.');
                }
            }
        } catch (\Throwable) {
            $this->fail("{$prefix}.spend_date", 'The row dates are invalid.');
        }
    }

    private function money(string $value, string $field): string
    {
        try {
            return (new MoneyCalculator)->normalize($value);
        } catch (\Throwable) {
            $this->fail($field, 'The amount must be a decimal with at most 2 places.');
        }
    }

    private function nullableMoney(?string $value, string $field): ?string
    {
        return $value === null || trim($value) === '' ? null : $this->money($value, $field);
    }

    private function plainDecimal(
        string $value,
        string $field,
        bool $allowNegative = true,
        int $maxIntegerDigits = 17,
    ): string {
        $pattern = $allowNegative
            ? '/^-?(?:0|[1-9]\d*)(?:\.\d{1,2})?$/'
            : '/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/';
        if (! preg_match($pattern, $value)) {
            $this->fail($field, 'The value must be a decimal with at most 2 places.');
        }

        [$integer, $fraction] = array_pad(explode('.', ltrim($value, '-'), 2), 2, '');
        $integer = ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;
        if (strlen($integer) > $maxIntegerDigits) {
            $this->fail($field, 'The decimal value is too large.');
        }

        $normalized = $integer.'.'.str_pad($fraction, MoneyCalculator::SCALE, '0');

        return str_starts_with($value, '-') && bccomp($normalized, '0', MoneyCalculator::SCALE) !== 0
            ? '-'.$normalized
            : $normalized;
    }

    private function nullablePlainDecimal(?string $value, string $field): ?string
    {
        return $value === null || trim($value) === '' ? null : $this->plainDecimal($value, $field);
    }

    private function nullableText(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }

    private function isCurrentOrActive(bool $active, ?int $currentId, int $selectedId): bool
    {
        return $active || $currentId === $selectedId;
    }

    private function isCurrentRowVendor(?Expense $expense, ?int $rowId, int $vendorId): bool
    {
        if (! $expense instanceof Expense || $rowId === null) {
            return false;
        }

        return $expense->rows()
            ->whereKey($rowId)
            ->where('vendor_id', $vendorId)
            ->exists();
    }

    private function nullableId(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
