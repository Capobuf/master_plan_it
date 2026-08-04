<?php

namespace App\Domain\Money\Services;

use App\Domain\Money\Money;
use App\Domain\Money\VatBreakdown;
use DomainException;

final class VatCalculator
{
    public function fromExcludedAmount(Money $amount, string $rate): VatBreakdown
    {
        $rate = $this->normalizeRate($rate);
        $calculator = new MoneyCalculator;
        $net = $calculator->round($amount, 2);
        $vat = $this->roundedMoney(
            bcmul($amount->amount(), bcdiv($rate, '100', 12), 12),
            $amount->currency(),
        );
        $gross = Money::fromDecimal(
            bcadd($net->amount(), $vat->amount(), 2),
            $amount->currency(),
            2,
        );

        return new VatBreakdown($net, $vat, $gross);
    }

    public function fromIncludedAmount(Money $amount, string $rate): VatBreakdown
    {
        $rate = $this->normalizeRate($rate);
        $calculator = new MoneyCalculator;
        $gross = $calculator->round($amount, 2);
        $net = $this->roundedMoney(
            bcdiv($amount->amount(), bcadd('1', bcdiv($rate, '100', 12), 12), 12),
            $amount->currency(),
        );
        $vat = Money::fromDecimal(
            bcsub($gross->amount(), $net->amount(), 2),
            $amount->currency(),
            2,
        );

        return new VatBreakdown($net, $vat, $gross);
    }

    private function normalizeRate(string $rate): string
    {
        if (! preg_match('/^\d+(?:\.\d+)?$/', $rate)) {
            throw new DomainException('INVALID_MONEY');
        }

        [, $fraction] = array_pad(explode('.', $rate, 2), 2, '');

        if (strlen($fraction) > 6) {
            throw new DomainException('INVALID_MONEY');
        }

        $integer = ltrim(strtok($rate, '.'), '0');

        return ($integer === '' ? '0' : $integer).'.'.str_pad($fraction, 6, '0');
    }

    private function roundedMoney(string $amount, string $currency): Money
    {
        $negative = str_starts_with($amount, '-');
        $absoluteAmount = ltrim($amount, '-');
        [$integer, $fraction] = array_pad(explode('.', $absoluteAmount, 2), 2, '');
        $fraction = str_pad($fraction, 12, '0');
        $rounded = $integer.'.'.substr($fraction, 0, 2);

        if ((int) $fraction[2] >= 5) {
            $rounded = bcadd($rounded, '0.01', 2);
        }

        if ($negative && bccomp($rounded, '0', 2) !== 0) {
            $rounded = '-'.$rounded;
        }

        return Money::fromDecimal($rounded, $currency, 2);
    }
}
