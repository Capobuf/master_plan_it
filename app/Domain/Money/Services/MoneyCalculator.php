<?php

namespace App\Domain\Money\Services;

use App\Domain\Money\Money;
use DomainException;

final class MoneyCalculator
{
    public function add(Money $first, Money $second): Money
    {
        $this->assertSameCurrency($first, $second);

        return Money::fromDecimal(bcadd($first->amount(), $second->amount(), 6), $first->currency());
    }

    public function subtract(Money $first, Money $second): Money
    {
        $this->assertSameCurrency($first, $second);

        return Money::fromDecimal(bcsub($first->amount(), $second->amount(), 6), $first->currency());
    }

    public function multiply(Money $money, string $multiplier): Money
    {
        $normalizedMultiplier = Money::fromDecimal($multiplier, $money->currency());
        $product = bcmul($money->amount(), $normalizedMultiplier->amount(), 12);

        return Money::fromDecimal(
            $this->roundDecimal($product, 6),
            $money->currency(),
        );
    }

    public function round(Money $money, int $scale): Money
    {
        if ($scale < 0 || $scale > 6) {
            throw new DomainException('INVALID_MONEY');
        }

        return Money::fromDecimal(
            $this->roundDecimal($money->amount(), $scale),
            $money->currency(),
            $scale,
        );
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
