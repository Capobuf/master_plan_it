<?php

namespace App\Domain\Expenses\Services;

use App\Domain\Expenses\Data\SaveExpenseData;
use App\Domain\Expenses\Data\SaveExpenseRowData;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Money\Money;
use App\Domain\Money\Services\MoneyCalculator;
use App\Domain\Money\Services\VatCalculator;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\Vendor;
use DateTimeImmutable;
use Illuminate\Validation\ValidationException;

final class ExpenseAggregateValidator
{
    /**
     * @param list<SaveExpenseRowData> $rows
     * @return array{header: array<string, mixed>, rows: list<array<string, mixed>>}
     */
    public function validate(Tenant $tenant, SaveExpenseData $data, array $rows, ?Expense $currentExpense = null): array
    {
        if ($data->projectId !== null || ($data->projectId !== null && $data->contractId !== null)) {
            $this->fail('project_id', 'Projects are not available in this workflow.');
        }
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
        if (! CostCenter::query()->where('tenant_id', $tenant->getKey())->whereKey($data->costCenterId)->exists()) {
            $this->fail('cost_center_id', 'The selected cost center is invalid.');
        }
        if ($data->contractId !== null && ! \App\Models\Contract::query()->where('tenant_id', $tenant->getKey())->whereKey($data->contractId)->exists()) {
            $preservedGeneratedSource = $currentExpense?->exists === true
                && (int) $currentExpense->getRawOriginal('contract_id') === $data->contractId
                && $currentExpense->rows()->whereNotNull('source_key')->exists()
                && \App\Models\Contract::withTrashed()
                    ->where('tenant_id', $tenant->getKey())
                    ->whereKey($data->contractId)
                    ->whereNotNull('deleted_at')
                    ->exists();

            if (! $preservedGeneratedSource) {
                $this->fail('contract_id', 'The selected contract is invalid.');
            }
        }

        $normalized = [];
        $ids = [];
        foreach ($rows as $index => $row) {
            if (! $row instanceof SaveExpenseRowData) {
                $this->fail("rows.{$index}", 'The expense row is invalid.');
            }
            if ($row->id !== null && isset($ids[$row->id])) {
                $this->fail("rows.{$index}.id", 'The expense row is duplicated.');
            }
            $ids[$row->id ?? -($index + 1)] = true;
            $normalized[] = $this->row($tenant, $year, $data->kind, $row, $index);
        }

        return [
            'header' => [
                'planning_year_id' => $data->planningYearId,
                'cost_center_id' => $data->costCenterId,
                'kind' => $data->kind,
                'title' => trim($data->title),
                'notes' => $this->nullableText($data->notes),
                'project_id' => null,
                'contract_id' => $data->contractId,
            ],
            'rows' => $normalized,
        ];
    }

    /** @return array<string, mixed> */
    private function row(Tenant $tenant, PlanningYear $year, ExpenseKind $kind, SaveExpenseRowData $row, int $index): array
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
        if ($row->vendorId !== null && ! Vendor::query()->where('tenant_id', $tenant->getKey())->whereKey($row->vendorId)->exists()) {
            $this->fail("{$prefix}.vendor_id", 'The selected vendor is invalid.');
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

        $this->dateShape($row, $prefix);
        $quantity = $this->nullableDecimal($row->quantity, "{$prefix}.quantity");
        $unitPrice = $this->nullableDecimal($row->unitPrice, "{$prefix}.unit_price");
        $entered = $this->decimal($row->enteredAmount, "{$prefix}.entered_amount");
        if ($unitPrice !== null && bccomp($unitPrice, '0', 6) !== 0) {
            if ($quantity === null) {
                $this->fail("{$prefix}.quantity", 'Quantity is required with a unit price.');
            }
            $entered = (new MoneyCalculator)->multiply(
                Money::fromDecimal($unitPrice, (string) $tenant->currency_code),
                $quantity,
            )->amount();
        }
        if ($row->type !== ExpenseType::Actual && bccomp($entered, '0', 6) < 0) {
            $this->fail("{$prefix}.entered_amount", 'Estimate and Quote amounts cannot be negative.');
        }
        $vatRate = trim($row->vatRate) === '' ? (string) $tenant->default_vat_rate : $row->vatRate;
        $amount = Money::fromDecimal($entered, (string) $tenant->currency_code);
        $breakdown = $row->amountIncludesVat
            ? (new VatCalculator)->fromIncludedAmount($amount, $vatRate)
            : (new VatCalculator)->fromExcludedAmount($amount, $vatRate);

        return [
            'id' => $row->id,
            'position' => $row->position,
            'vendor_id' => $row->vendorId,
            'type' => $row->type,
            'description' => trim($row->description),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'entered_amount' => $entered,
            'amount_includes_vat' => $row->amountIncludesVat,
            'vat_rate' => $vatRate,
            'net_amount' => $breakdown->net()->amount(),
            'vat_amount' => $breakdown->vat()->amount(),
            'gross_amount' => $breakdown->gross()->amount(),
            'is_extra' => $row->isExtra,
            'funded_plafond_expense_id' => $row->fundedPlafondExpenseId,
            'spend_date' => $row->spendDate,
            'period_start' => $row->periodStart,
            'period_end' => $row->periodEnd,
            'distribution' => $row->distribution,
            'external_reference' => $this->nullableText($row->externalReference),
            'expected_lock_version' => $row->expectedLockVersion,
        ];
    }

    private function dateShape(SaveExpenseRowData $row, string $prefix): void
    {
        $spend = $row->spendDate !== null;
        $periodAny = $row->periodStart !== null || $row->periodEnd !== null || $row->distribution !== null;
        $periodComplete = $row->periodStart !== null && $row->periodEnd !== null && $row->distribution !== null;
        if (($spend && $periodAny) || (! $spend && ! $periodComplete)) {
            $this->fail("{$prefix}.spend_date", 'Use either a spend date or a complete period.');
        }
        try {
            if ($spend) {
                new DateTimeImmutable((string) $row->spendDate);
            } else {
                $start = new DateTimeImmutable((string) $row->periodStart);
                $end = new DateTimeImmutable((string) $row->periodEnd);
                if ($start > $end) {
                    $this->fail("{$prefix}.period_end", 'The period end must not precede its start.');
                }
            }
        } catch (\Throwable) {
            $this->fail("{$prefix}.spend_date", 'The row dates are invalid.');
        }
    }

    private function decimal(string $value, string $field): string
    {
        try { return Money::fromDecimal($value, 'EUR')->amount(); }
        catch (\Throwable) { $this->fail($field, 'The amount must be a decimal with at most 6 places.'); }
    }

    private function nullableDecimal(?string $value, string $field): ?string
    {
        return $value === null || trim($value) === '' ? null : $this->decimal($value, $field);
    }

    private function nullableText(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
