<?php

namespace Tests\Accounting\Unit;

use App\Domain\Economics\Services\MoneyCalculator;
use App\Domain\Money\Money;
use App\Domain\Money\Services\VatCalculator;
use App\Domain\Money\VatBreakdown;
use DomainException;
use PHPUnit\Framework\TestCase;

class VatCalculatorTest extends TestCase
{
    public function test_vat_excluded_amount_produces_exact_net_vat_and_gross_components(): void
    {
        $breakdown = (new VatCalculator)->fromExcludedAmount(
            Money::fromDecimal('100.00', 'EUR'),
            '22.00',
        );

        $this->assertBreakdown($breakdown, '100.00', '22.00', '122.00', 'EUR');
    }

    public function test_vat_included_amount_produces_exact_net_vat_and_gross_components(): void
    {
        $breakdown = (new VatCalculator)->fromIncludedAmount(
            Money::fromDecimal('122.00', 'EUR'),
            '22.00',
        );

        $this->assertBreakdown($breakdown, '100.00', '22.00', '122.00', 'EUR');
    }

    public function test_negative_amount_keeps_a_reconciled_negative_vat_breakdown(): void
    {
        $breakdown = (new VatCalculator)->fromExcludedAmount(
            Money::fromDecimal('-100.00', 'EUR'),
            '22.00',
        );

        $this->assertBreakdown($breakdown, '-100.00', '-22.00', '-122.00', 'EUR');
    }

    public function test_vat_excluded_positive_half_up_boundaries_reconcile_at_two_decimals(): void
    {
        $breakdown = (new VatCalculator)->fromExcludedAmount(
            Money::fromDecimal('0.05', 'EUR'),
            '10.00',
        );

        $this->assertBreakdown($breakdown, '0.05', '0.01', '0.06', 'EUR');
    }

    public function test_vat_excluded_negative_half_up_boundaries_reconcile_at_two_decimals(): void
    {
        $breakdown = (new VatCalculator)->fromExcludedAmount(
            Money::fromDecimal('-0.05', 'EUR'),
            '10.00',
        );

        $this->assertBreakdown($breakdown, '-0.05', '-0.01', '-0.06', 'EUR');
    }

    public function test_zero_amount_is_a_reconciled_zero_breakdown(): void
    {
        $breakdown = (new VatCalculator)->fromIncludedAmount(
            Money::fromDecimal('0.00', 'EUR'),
            '22.00',
        );

        $this->assertBreakdown($breakdown, '0.00', '0.00', '0.00', 'EUR');
    }

    public function test_maximum_decimal_12_2_vat_rate_is_accepted_when_the_result_is_calculable(): void
    {
        $breakdown = (new VatCalculator)->fromExcludedAmount(
            Money::fromDecimal('0.01', 'EUR'),
            '9999999999.99',
        );

        $this->assertBreakdown($breakdown, '0.01', '1000000.00', '1000000.01', 'EUR');
    }

    public function test_vat_rate_with_more_than_two_places_is_rejected_without_a_breakdown(): void
    {
        try {
            (new VatCalculator)->fromExcludedAmount(
                Money::fromDecimal('0.01', 'EUR'),
                '10.123',
            );
            $this->fail('Out-of-range VAT rate was accepted.');
        } catch (DomainException $exception) {
            $this->assertSame('INVALID_VAT_RATE', $exception->getMessage());
        }
    }

    public function test_rate_reconstructed_from_money_uses_high_precision_but_returns_scale_two(): void
    {
        $rate = (new VatCalculator)->rateFromAmounts(
            Money::fromDecimal('13.19', 'EUR'),
            Money::fromDecimal('1.38', 'EUR'),
        );

        $this->assertSame('10.46', $rate);
    }

    public function test_slice_023_target_vat_calculator_keeps_negative_included_amounts_reconciled(): void
    {
        $calculator = new \App\Domain\Economics\Services\VatCalculator(
            new MoneyCalculator,
        );

        $measure = $calculator->fromIncluded('-122.00', '22.00', 'gross');

        $this->assertSame('-100.00', $measure->net);
        $this->assertSame('-22.00', $measure->vat);
        $this->assertSame('-122.00', $measure->gross);
        $this->assertSame('-122.00', $measure->official);
    }

    public function test_slice_023_target_vat_calculator_covers_excluded_basis_and_rate_validation(): void
    {
        $calculator = new \App\Domain\Economics\Services\VatCalculator(
            new MoneyCalculator,
        );

        $measure = $calculator->fromExcluded('100.00', '22.00', 'net');

        $this->assertSame('100.00', $measure->net);
        $this->assertSame('22.00', $measure->vat);
        $this->assertSame('122.00', $measure->gross);
        $this->assertSame('100.00', $measure->official);

        try {
            $calculator->fromIncluded('122.00', '22.001', 'gross');
            $this->fail('An invalid VAT rate was accepted.');
        } catch (DomainException $exception) {
            $this->assertSame('INVALID_VAT_RATE', $exception->getMessage());
        }
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
