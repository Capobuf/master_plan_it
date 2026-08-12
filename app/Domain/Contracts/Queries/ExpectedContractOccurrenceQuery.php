<?php

namespace App\Domain\Contracts\Queries;

use App\Domain\Contracts\Data\ContractOccurrenceKey;
use App\Domain\Contracts\Data\ExpectedContractOccurrence;
use App\Domain\Contracts\Enums\BillingCycle;
use App\Models\Contract;
use App\Models\ContractTerm;
use App\Models\ExpenseRow;
use Carbon\CarbonImmutable;

final class ExpectedContractOccurrenceQuery
{
    /** @return list<ExpectedContractOccurrence> */
    public function forContract(Contract $contract, ?int $year = null): array
    {
        $terms = $contract->terms()
            ->where('tenant_id', $contract->tenant_id)
            ->orderBy('effective_start')
            ->get();
        $exceptions = $contract->generationExceptions()
            ->where('tenant_id', $contract->tenant_id)
            ->pluck('id', 'source_key');
        $rows = $contract->expenses()
            ->where('tenant_id', $contract->tenant_id)
            ->with(['rows' => fn ($query) => $query->whereNotNull('source_key')])
            ->get()
            ->flatMap(fn ($expense) => $expense->rows)
            ->keyBy('source_key');
        /** @var array<int, array{term_id:int, date:CarbonImmutable, net:string, vat:string, gross:string, vat_rate:?string}> $annual */
        $annual = [];
        foreach ($terms as $term) {
            foreach ($this->dates($term) as $date) {
                if ($year !== null && $date->year !== $year) {
                    continue;
                }
                $bucket = $annual[$date->year] ?? [
                    'term_id' => (int) $term->getKey(),
                    'date' => $date,
                    'net' => '0.00',
                    'vat' => '0.00',
                    'gross' => '0.00',
                    'vat_rate' => (string) $term->vat_rate,
                ];
                if ($bucket['vat_rate'] !== null && bccomp($bucket['vat_rate'], (string) $term->vat_rate, 2) !== 0) {
                    $bucket['vat_rate'] = null;
                }
                $bucket['net'] = bcadd($bucket['net'], (string) $term->net_amount, 2);
                $bucket['vat'] = bcadd($bucket['vat'], (string) $term->vat_amount, 2);
                $bucket['gross'] = bcadd($bucket['gross'], (string) $term->gross_amount, 2);
                $annual[$date->year] = $bucket;
            }
        }

        $result = [];
        ksort($annual);
        foreach ($annual as $planningYear => $bucket) {
            $key = ContractOccurrenceKey::annual($contract, $planningYear)->value;
            $row = $rows->get($key);
            $result[] = new ExpectedContractOccurrence(
                (int) $contract->getKey(),
                $bucket['term_id'],
                $planningYear,
                sprintf('%04d-01-01', $planningYear),
                $key,
                $bucket['net'],
                $bucket['vat'],
                $bucket['gross'],
                $bucket['vat_rate'],
                $exceptions->has($key),
                $row instanceof ExpenseRow ? (int) $row->expense_id : null,
                $row instanceof ExpenseRow ? ($row->manual_override_at === null ? 'managed' : 'manual') : null,
                $row instanceof ExpenseRow && (
                    bccomp($bucket['net'], (string) $row->net_amount, 6) !== 0
                    || bccomp($bucket['vat'], (string) $row->vat_amount, 6) !== 0
                    || bccomp($bucket['gross'], (string) $row->gross_amount, 6) !== 0
                ) ? [
                    'net' => bcsub($bucket['net'], (string) $row->net_amount, 2),
                    'vat' => bcsub($bucket['vat'], (string) $row->vat_amount, 2),
                    'gross' => bcsub($bucket['gross'], (string) $row->gross_amount, 2),
                ] : null,
            );
        }

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
                if ($date->betweenIncluded($start, $end)) {
                    $dates[] = $date;
                }
            }

            return $dates;
        }
        $month = $start->startOfMonth();
        while ($month->lessThanOrEqualTo($end->startOfMonth())) {
            $date = $month->day(min($start->day, $month->daysInMonth));
            if ($date->betweenIncluded($start, $end)) {
                $dates[] = $date;
            }
            $month = $month->addMonthNoOverflow()->startOfMonth();
        }

        return $dates;
    }
}
