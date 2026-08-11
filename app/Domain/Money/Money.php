<?php

namespace App\Domain\Money;

use DomainException;

final readonly class Money
{
    public const SCALE = 2;

    private const PRECISION = 19;

    private function __construct(
        private string $amount,
        private string $currency,
    ) {}

    public static function fromDecimal(string $amount, string $currency): self
    {
        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $amount)) {
            throw new DomainException('INVALID_MONEY');
        }

        $normalizedCurrency = strtoupper($currency);

        if (! preg_match('/^[A-Z]{3}$/', $normalizedCurrency)) {
            throw new DomainException('INVALID_MONEY');
        }

        [$integer, $fraction] = array_pad(explode('.', ltrim($amount, '-'), 2), 2, '');

        if (strlen($fraction) > self::SCALE) {
            throw new DomainException('INVALID_MONEY');
        }

        $integer = ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;

        if (strlen($integer) > self::PRECISION - self::SCALE) {
            throw new DomainException('INVALID_MONEY');
        }

        $normalizedAmount = $integer;

        $normalizedAmount .= '.'.str_pad($fraction, self::SCALE, '0');

        if (str_starts_with($amount, '-') && bccomp($normalizedAmount, '0', self::SCALE) !== 0) {
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
