<?php

namespace App\Domain\Money\Services;

use App\Domain\Money\Money;
use DateTimeImmutable;
use DomainException;

final class MonthlyAllocator
{
    /** @return array<string, Money> */
    public function allocate(Money $amount, DateTimeImmutable $start, DateTimeImmutable $end, string $distribution): array
    {
        if ($start > $end || ! in_array($distribution, ['all', 'start', 'end'], true)) {
            throw new DomainException('DATE_MODE_CONFLICT');
        }

        $months = $this->months($start, $end);
        $calculator = new MoneyCalculator;
        $total = $calculator->round($amount, 2);

        if ($distribution === 'start') {
            return [$months[0] => $total];
        }

        if ($distribution === 'end') {
            return [$months[array_key_last($months)] => $total];
        }

        $share = $calculator->round(
            Money::fromDecimal(bcdiv($total->amount(), (string) count($months), 6), $total->currency()),
            2,
        );
        $allocation = [];
        $allocated = '0.00';

        foreach ($months as $month) {
            $allocation[$month] = $share;
            $allocated = bcadd($allocated, $share->amount(), 2);
        }

        $lastMonth = $months[array_key_last($months)];
        $allocation[$lastMonth] = Money::fromDecimal(
            bcadd($allocation[$lastMonth]->amount(), bcsub($total->amount(), $allocated, 2), 2),
            $total->currency(),
            2,
        );

        return $allocation;
    }

    /** @return list<string> */
    private function months(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $month = new DateTimeImmutable($start->format('Y-m-01'), $start->getTimezone());
        $lastMonth = new DateTimeImmutable($end->format('Y-m-01'), $end->getTimezone());
        $months = [];

        while ($month <= $lastMonth) {
            $months[] = $month->format('Y-m');
            $month = $month->modify('+1 month');
        }

        return $months;
    }
}
