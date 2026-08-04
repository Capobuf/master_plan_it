<?php

namespace Tests\Accounting\Unit;

use App\Domain\Money\Money;
use App\Domain\Money\Services\VatCalculator;
use App\Domain\Money\VatBreakdown;
use PHPUnit\Framework\TestCase;

class VatCalculatorTest extends TestCase
{
    public function test_vat_excluded_amount_produces_exact_net_vat_and_gross_components(): void
    {
        $breakdown = (new VatCalculator)->fromExcludedAmount(
            Money::fromDecimal('100.000000', 'EUR'),
            '22.000000',
        );

        $this->assertBreakdown($breakdown, '100.00', '22.00', '122.00', 'EUR');
    }

    public function test_vat_included_amount_produces_exact_net_vat_and_gross_components(): void
    {
        $breakdown = (new VatCalculator)->fromIncludedAmount(
            Money::fromDecimal('122.000000', 'EUR'),
            '22.000000',
        );

        $this->assertBreakdown($breakdown, '100.00', '22.00', '122.00', 'EUR');
    }

    public function test_negative_amount_keeps_a_reconciled_negative_vat_breakdown(): void
    {
        $breakdown = (new VatCalculator)->fromExcludedAmount(
            Money::fromDecimal('-100.000000', 'EUR'),
            '22.000000',
        );

        $this->assertBreakdown($breakdown, '-100.00', '-22.00', '-122.00', 'EUR');
    }

    public function test_vat_excluded_positive_half_up_boundaries_reconcile_at_two_decimals(): void
    {
        $breakdown = (new VatCalculator)->fromExcludedAmount(
            Money::fromDecimal('0.025000', 'EUR'),
            '20.000000',
        );

        $this->assertBreakdown($breakdown, '0.03', '0.01', '0.04', 'EUR');
    }

    public function test_vat_excluded_negative_half_up_boundaries_reconcile_at_two_decimals(): void
    {
        $breakdown = (new VatCalculator)->fromExcludedAmount(
            Money::fromDecimal('-0.025000', 'EUR'),
            '20.000000',
        );

        $this->assertBreakdown($breakdown, '-0.03', '-0.01', '-0.04', 'EUR');
    }

    public function test_zero_amount_is_a_reconciled_zero_breakdown(): void
    {
        $breakdown = (new VatCalculator)->fromIncludedAmount(
            Money::fromDecimal('0.000000', 'EUR'),
            '22.000000',
        );

        $this->assertBreakdown($breakdown, '0.00', '0.00', '0.00', 'EUR');
    }

    private function assertBreakdown(
        VatBreakdown $breakdown,
        string $net,
        string $vat,
        string $gross,
        string $currency,
    ): void {
        $this->assertSame($net, $breakdown->net()->amount());
        $this->assertSame($vat, $breakdown->vat()->amount());
        $this->assertSame($gross, $breakdown->gross()->amount());
        $this->assertSame($currency, $breakdown->net()->currency());
        $this->assertSame($currency, $breakdown->vat()->currency());
        $this->assertSame($currency, $breakdown->gross()->currency());
        $this->assertSame($gross, bcadd($breakdown->net()->amount(), $breakdown->vat()->amount(), 2));
    }
}
