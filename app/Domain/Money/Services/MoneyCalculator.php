<?php

namespace App\Domain\Money\Services;

use App\Domain\Money\Money;
use DomainException;

final class MoneyCalculator
{
    private const CALCULATION_SCALE = 12;

    public function add(Money $first, Money $second): Money
    {
        $this->assertSameCurrency($first, $second);

        return $this->calculatedMoney(
            bcadd($first->amount(), $second->amount(), self::CALCULATION_SCALE),
            $first->currency(),
        );
    }

    public function subtract(Money $first, Money $second): Money
    {
        $this->assertSameCurrency($first, $second);

        return $this->calculatedMoney(
            bcsub($first->amount(), $second->amount(), self::CALCULATION_SCALE),
            $first->currency(),
        );
    }

    public function multiply(Money $money, string $multiplier): Money
    {
        $normalizedMultiplier = $this->normalizeMultiplier($multiplier);
        $product = bcmul($money->amount(), $normalizedMultiplier, self::CALCULATION_SCALE);

        return $this->calculatedMoney($product, $money->currency());
    }

    public function divide(Money $money, string $divisor): Money
    {
        $normalizedDivisor = $this->normalizeMultiplier($divisor);
        if (bccomp($normalizedDivisor, '0', Money::SCALE) === 0) {
            throw new DomainException('INVALID_MONEY');
        }

        return $this->calculatedMoney(
            bcdiv($money->amount(), $normalizedDivisor, self::CALCULATION_SCALE),
            $money->currency(),
        );
    }

    private function calculatedMoney(string $amount, string $currency): Money
    {
        return Money::fromDecimal($this->roundDecimal($amount, Money::SCALE), $currency);
    }

    private function normalizeMultiplier(string $multiplier): string
    {
        if (! preg_match('/^-?\d+(?:\.\d{1,2})?$/', $multiplier)) {
            throw new DomainException('INVALID_DECIMAL');
        }

        [$integer, $fraction] = array_pad(explode('.', ltrim($multiplier, '-'), 2), 2, '');
        $integer = ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;
        if (strlen($integer) > 17) {
            throw new DomainException('INVALID_DECIMAL');
        }

        $normalized = $integer.'.'.str_pad($fraction, Money::SCALE, '0');

        return str_starts_with($multiplier, '-') && bccomp($normalized, '0', Money::SCALE) !== 0
            ? '-'.$normalized
            : $normalized;
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

    private function assertSameCurrency(Money $first, Money $second): void
    {
        if ($first->currency() !== $second->currency()) {
            throw new DomainException('INVALID_MONEY');
        }
    }
}
