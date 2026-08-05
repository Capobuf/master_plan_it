<?php

namespace App\Support\Formatting;

use InvalidArgumentException;

final class MoneyFormatter
{
    /** @var array<string, string> */
    private const array CURRENCY_SYMBOLS = [
        'EUR' => '€',
        'GBP' => '£',
        'USD' => '$',
    ];

    public static function format(string $amount, string $currency): string
    {
        if (! preg_match('/^(?<negative>-?)(?<integer>\d+)(?:\.(?<fraction>\d+))?$/D', $amount, $matches)) {
            throw new InvalidArgumentException('The amount must be a decimal string.');
        }

        $integer = ltrim($matches['integer'], '0');
        $integer = $integer === '' ? '0' : $integer;
        $fraction = str_pad($matches['fraction'] ?? '', 2, '0');
        $isNegative = $matches['negative'] === '-';

        if (strlen($fraction) > 2) {
            $shouldRound = (int) $fraction[2] >= 5;
            $fraction = substr($fraction, 0, 2);

            if ($shouldRound) {
                [$integer, $fraction] = self::incrementCents($integer, $fraction);
            }
        }

        if ($integer === '0' && $fraction === '00') {
            $isNegative = false;
        }

        $groups = [];

        while (strlen($integer) > 3) {
            array_unshift($groups, substr($integer, -3));
            $integer = substr($integer, 0, -3);
        }

        array_unshift($groups, $integer);

        $formattedAmount = ($isNegative ? '-' : '')
            .implode("'", $groups)
            .','
            .$fraction;

        $currency = strtoupper($currency);
        $currencyLabel = self::CURRENCY_SYMBOLS[$currency] ?? $currency;

        return $formattedAmount.' '.$currencyLabel;
    }

    /** @return array{string, string} */
    private static function incrementCents(string $integer, string $fraction): array
    {
        $cents = (int) $fraction + 1;

        if ($cents < 100) {
            return [$integer, str_pad((string) $cents, 2, '0', STR_PAD_LEFT)];
        }

        return [self::incrementInteger($integer), '00'];
    }

    private static function incrementInteger(string $integer): string
    {
        $digits = str_split($integer);

        for ($index = count($digits) - 1; $index >= 0; $index--) {
            if ($digits[$index] !== '9') {
                $digits[$index] = (string) ((int) $digits[$index] + 1);

                return implode('', $digits);
            }

            $digits[$index] = '0';
        }

        return '1'.implode('', $digits);
    }
}
