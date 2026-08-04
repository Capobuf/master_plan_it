<?php

namespace App\Domain\Money;

use DomainException;

final readonly class Money
{
    private function __construct(
        private string $amount,
        private string $currency,
    ) {}

    public static function fromDecimal(string $amount, string $currency, int $scale = 6): self
    {
        if ($scale < 0 || $scale > 6 || ! preg_match('/^-?\d+(?:\.\d+)?$/', $amount)) {
            throw new DomainException('INVALID_MONEY');
        }

        $normalizedCurrency = strtoupper($currency);

        if (! preg_match('/^[A-Z]{3}$/', $normalizedCurrency)) {
            throw new DomainException('INVALID_MONEY');
        }

        [$integer, $fraction] = array_pad(explode('.', ltrim($amount, '-'), 2), 2, '');

        if (strlen($fraction) > 6 || strlen($fraction) > $scale) {
            throw new DomainException('INVALID_MONEY');
        }

        $integer = ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;

        if (strlen($integer) > 13) {
            throw new DomainException('INVALID_MONEY');
        }

        $normalizedAmount = $integer;

        if ($scale > 0) {
            $normalizedAmount .= '.'.str_pad($fraction, $scale, '0');
        }

        if (str_starts_with($amount, '-') && bccomp($normalizedAmount, '0', $scale) !== 0) {
            $normalizedAmount = '-'.$normalizedAmount;
        }

        return new self($normalizedAmount, $normalizedCurrency);
    }

    public function amount(): string
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }
}
