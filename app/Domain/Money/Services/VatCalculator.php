<?php

namespace App\Domain\Money\Services;

use App\Domain\Money\Money;
use App\Domain\Money\VatBreakdown;
use DomainException;

final class VatCalculator
{
    private const CALCULATION_SCALE = 12;

    public function fromExcludedAmount(Money $amount, string $rate): VatBreakdown
    {
        $rate = $this->normalizeRate($rate);
        $calculator = new MoneyCalculator;
        $net = $amount;
        $vat = $this->roundedMoney(
            bcmul($amount->amount(), bcdiv($rate, '100', 12), 12),
            $amount->currency(),
        );
        $gross = $calculator->add($net, $vat);

        return new VatBreakdown($net, $vat, $gross);
    }

    public function fromIncludedAmount(Money $amount, string $rate): VatBreakdown
    {
        $rate = $this->normalizeRate($rate);
        $calculator = new MoneyCalculator;
        $gross = $amount;
        $net = $this->roundedMoney(
            bcdiv($amount->amount(), bcadd('1', bcdiv($rate, '100', 12), 12), 12),
            $amount->currency(),
        );
        $vat = $calculator->subtract($gross, $net);

        return new VatBreakdown($net, $vat, $gross);
    }

    public function rateFromAmounts(Money $net, Money $vat): string
    {
        if ($net->currency() !== $vat->currency()) {
            throw new DomainException('INVALID_MONEY');
        }
        if (bccomp($net->amount(), '0', Money::SCALE) === 0) {
            return '0.00';
        }

        $calculated = bcmul(
            bcdiv($vat->amount(), $net->amount(), self::CALCULATION_SCALE),
            '100',
            self::CALCULATION_SCALE,
        );

        return $this->normalizeRate($this->roundDecimal($calculated, Money::SCALE));
    }

    private function normalizeRate(string $rate): string
    {
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $rate)) {
            throw new DomainException('INVALID_VAT_RATE');
        }

        [$integer, $fraction] = array_pad(explode('.', $rate, 2), 2, '');

        $integer = ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;

        if (strlen($integer) > 10) {
            throw new DomainException('INVALID_VAT_RATE');
        }

        return $integer.'.'.str_pad($fraction, Money::SCALE, '0');
    }

    private function roundedMoney(string $amount, string $currency): Money
    {
        return Money::fromDecimal($this->roundDecimal($amount, Money::SCALE), $currency);
    }

    private function roundDecimal(string $amount, int $scale): string
    {
        $negative = str_starts_with($amount, '-');
        $absoluteAmount = ltrim($amount, '-');
        [$integer, $fraction] = array_pad(explode('.', $absoluteAmount, 2), 2, '');
        $fraction = str_pad($fraction, $scale + 1, '0');
        $rounded = $integer.($scale > 0 ? '.'.substr($fraction, 0, $scale) : '');

        if ((int) ($fraction[$scale] ?? '0') >= 5) {
            $increment = $scale === 0 ? '1' : '0.'.str_repeat('0', $scale - 1).'1';
            $rounded = bcadd($rounded, $increment, $scale);
        }

        if ($negative && bccomp($rounded, '0', $scale) !== 0) {
            $rounded = '-'.$rounded;
        }

        return $rounded;
    }
}
