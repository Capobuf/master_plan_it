<?php

namespace Tests\Support;

final class BudgetApprovalFixture
{
    /** @return array{net: string, vat: string, gross: string, official: string} */
    public static function measure(
        string $net,
        string $vat,
        string $gross,
        string $basis = 'net',
    ): array {
        foreach ([$net, $vat, $gross] as $amount) {
            if (! preg_match('/^-?(?:0|[1-9]\d*)\.\d{2}$/D', $amount) || $amount === '-0.00') {
                throw new \InvalidArgumentException('Budget approval fixture amounts must be canonical two-place strings.');
            }
        }

        if (! in_array($basis, ['net', 'gross'], true)) {
            throw new \InvalidArgumentException('Budget approval fixture basis must be net or gross.');
        }

        if (bcadd($net, $vat, 2) !== $gross) {
            throw new \InvalidArgumentException('Budget approval fixture measures must reconcile exactly.');
        }

        return [
            'net' => $net,
            'vat' => $vat,
            'gross' => $gross,
            'official' => $basis === 'net' ? $net : $gross,
        ];
    }

    /** @return array<string, mixed> */
    public static function canonical(string $basis = 'net', int $tenantId = 101, int $planningYearId = 2026): array
    {
        return [
            'tenant' => ['id' => $tenantId, 'timezone' => 'Europe/Rome', 'currency' => 'EUR'],
            'planning_year' => ['id' => $planningYearId, 'year_label' => 2026, 'lock_version' => 7],
            'basis' => $basis,
            'contributors' => [
                [
                    'source_identity' => 'expense-row:501',
                    'kind' => 'ordinary_current_planning',
                    'amount' => self::measure('120.00', '26.40', '146.40', $basis),
                ],
                [
                    'source_identity' => 'plafond-allocation:81',
                    'kind' => 'plafond_allocation',
                    'amount' => self::measure('3500.00', '770.00', '4270.00', $basis),
                ],
            ],
            'excluded' => [
                ['source_identity' => 'expense-row:500', 'reason' => 'alternative_planning'],
                ['source_identity' => 'expense-row:502', 'reason' => 'covered_by_plafond'],
            ],
            'total' => self::measure('3620.00', '796.40', '4416.40', $basis),
        ];
    }

    /** @return array<string, mixed> */
    public static function empty(string $basis = 'net'): array
    {
        return ['contributors' => [], 'total' => self::measure('0.00', '0.00', '0.00', $basis)];
    }

    /** @return array<string, mixed> */
    public static function allZero(string $basis = 'net'): array
    {
        return [
            'contributors' => [[
                'source_identity' => 'expense-row:601',
                'kind' => 'ordinary_current_planning',
                'amount' => self::measure('0.00', '0.00', '0.00', $basis),
            ]],
            'total' => self::measure('0.00', '0.00', '0.00', $basis),
        ];
    }

    /** @return array<string, mixed> */
    public static function offsetting(string $basis = 'net'): array
    {
        return [
            'contributors' => [
                ['source_identity' => 'expense-row:701', 'amount' => self::measure('100.00', '22.00', '122.00', $basis)],
                ['source_identity' => 'expense-row:702', 'amount' => self::measure('-100.00', '-22.00', '-122.00', $basis)],
            ],
            'total' => self::measure('0.00', '0.00', '0.00', $basis),
        ];
    }

    /** @return array{tenant_a: array<string, mixed>, tenant_b: array<string, mixed>} */
    public static function tenantPair(): array
    {
        return [
            'tenant_a' => self::canonical('net', 101, 2026),
            'tenant_b' => self::canonical('gross', 202, 2027),
        ];
    }

    /** @return array{timezone: string, today: string, allowed: string, rejected: string} */
    public static function tenantTimezoneBoundary(): array
    {
        return [
            'timezone' => 'Pacific/Kiritimati',
            'today' => '2026-08-14',
            'allowed' => '2026-08-14',
            'rejected' => '2026-08-15',
        ];
    }
}
