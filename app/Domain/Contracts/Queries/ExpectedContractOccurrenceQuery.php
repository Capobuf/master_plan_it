<?php

namespace App\Domain\Contracts\Queries;

use App\Domain\Contracts\Data\ContractOccurrenceKey;
use App\Domain\Contracts\Data\ExpectedContractOccurrence;
use App\Domain\Contracts\Enums\BillingCycle;
use App\Domain\Expenses\Enums\ActualConfirmationState;
use App\Models\Contract;
use App\Models\ContractGenerationException;
use App\Models\ContractTerm;
use App\Models\ExpenseRow;
use Carbon\CarbonImmutable;

final class ExpectedContractOccurrenceQuery
{
    /** @return list<ExpectedContractOccurrence> */
    public function forContract(Contract $contract, ?int $year = null): array
    {
        $terms = ContractTerm::query()->where('tenant_id', $contract->tenant_id)->where('contract_id', $contract->getKey())->orderBy('effective_start')->get();
        $exceptions = ContractGenerationException::query()->where('tenant_id', $contract->tenant_id)->where('contract_id', $contract->getKey())->pluck('id', 'source_key');
        $rows = ExpenseRow::query()->where('tenant_id', $contract->tenant_id)->whereNotNull('source_key')->get()->keyBy('source_key');
        $result = [];
        foreach ($terms as $term) {
            foreach ($this->dates($term) as $date) {
                if ($year !== null && $date->year !== $year) { continue; }
                $key = ContractOccurrenceKey::make($contract, $term, $date->year, $date)->value;
                $row = $rows->get($key);
                $result[] = new ExpectedContractOccurrence(
                    (int) $contract->getKey(), (int) $term->getKey(), $date->year, $date->toDateString(), $key,
                    (string) $term->net_amount, (string) $term->vat_amount, (string) $term->gross_amount,
                    $exceptions->has($key), $row instanceof ExpenseRow ? (int) $row->expense_id : null,
                    $row instanceof ExpenseRow && $row->confirmation_state instanceof ActualConfirmationState
                        ? $row->confirmation_state->value
                        : null,
                );
            }
        }
        usort($result, fn ($a, $b) => [$a->occurrenceDate, $a->termId] <=> [$b->occurrenceDate, $b->termId]);
        return $result;
    }

    /** @return list<CarbonImmutable> */
    private function dates(ContractTerm $term): array
    {
        $start = CarbonImmutable::parse($term->effective_start);
        $end = CarbonImmutable::parse($term->effective_end);
        $dates = [];
        if ($term->billing_cycle === BillingCycle::Annual) {
            for ($year = $start->year; $year <= $end->year; $year++) {
                $date = CarbonImmutable::create($year, $start->month, min($start->day, CarbonImmutable::create($year, $start->month)->daysInMonth));
                if ($date->betweenIncluded($start, $end)) { $dates[] = $date; }
            }
            return $dates;
        }
        $month = $start->startOfMonth();
        while ($month->lessThanOrEqualTo($end->startOfMonth())) {
            $date = $month->day(min($start->day, $month->daysInMonth));
            if ($date->betweenIncluded($start, $end)) { $dates[] = $date; }
            $month = $month->addMonthNoOverflow()->startOfMonth();
        }
        return $dates;
    }
}
