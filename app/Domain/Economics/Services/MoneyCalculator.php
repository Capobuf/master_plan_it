<?php

namespace App\Domain\Economics\Services;

use DomainException;

/** Exact decimal normalization and arithmetic for authoritative economic inputs. */
final class MoneyCalculator
{
    public const SCALE = 2;

    private const INTERMEDIATE_SCALE = 12;

    public function normalize(string $value, bool $allowNegative = true): string
    {
        if (! preg_match('/^-?(0|[1-9]\d*)(\.\d{1,2})?$/D', $value)) {
            throw new DomainException('INVALID_DECIMAL');
        }
        if (! $allowNegative && str_starts_with($value, '-')) {
            throw new DomainException('INVALID_DECIMAL');
        }
        [$whole, $fraction] = array_pad(explode('.', ltrim($value, '-'), 2), 2, '');
        if (strlen($whole) > 17) {
            throw new DomainException('DECIMAL_OVERFLOW');
        }
        $normalized = $whole.'.'.str_pad($fraction, self::SCALE, '0');

        return str_starts_with($value, '-') && bccomp($normalized, '0.00', self::SCALE) !== 0 ? '-'.$normalized : $normalized;
    }

    public function multiply(string $first, string $second): string
    {
        return $this->rounded(bcmul($this->normalize($first), $this->normalize($second), self::INTERMEDIATE_SCALE));
    }

    public function divide(string $amount, string $divisor): string
    {
        $normalizedDivisor = $this->normalize($divisor);
        if (bccomp($normalizedDivisor, '0.00', self::SCALE) === 0) {
            throw new DomainException('INVALID_DECIMAL');
        }

        return $this->rounded(bcdiv($this->normalize($amount), $normalizedDivisor, self::INTERMEDIATE_SCALE));
    }

    public function add(string $first, string $second): string
    {
        return $this->rounded(bcadd($this->normalize($first), $this->normalize($second), self::INTERMEDIATE_SCALE));
    }

    public function subtract(string $first, string $second): string
    {
        return $this->rounded(bcsub($this->normalize($first), $this->normalize($second), self::INTERMEDIATE_SCALE));
    }

    public function rounded(string $value): string
    {
        $negative = str_starts_with($value, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($value, '-'), 2), 2, '');
        $fraction = str_pad($fraction, self::SCALE + 1, '0');
        $result = $whole.'.'.substr($fraction, 0, self::SCALE);
        if ((int) $fraction[self::SCALE] >= 5) {
            $result = bcadd($result, '0.01', self::SCALE);
        }
        if (strlen($whole) > 17) {
            throw new DomainException('DECIMAL_OVERFLOW');
        }

        return $negative && bccomp($result, '0.00', self::SCALE) !== 0 ? '-'.$result : $result;
    }
}
