<?php

namespace Tests\Accounting\Unit;

use App\Domain\Money\Money;
use App\Domain\Money\Services\MoneyCalculator;
use App\Domain\Money\VatBreakdown;
use DomainException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_normalizes_sub_scale_decimal_string_input_to_six_places_without_float_conversion(): void
    {
        $money = Money::fromDecimal('-12.5', 'eur');

        $this->assertSame('-12.500000', $money->amount());
        $this->assertSame('EUR', $money->currency());
    }

    public function test_it_accepts_the_decimal_19_6_boundaries_and_rejects_range_overflow(): void
    {
        $this->assertSame(
            '9999999999999.999999',
            Money::fromDecimal('9999999999999.999999', 'EUR')->amount(),
        );
        $this->assertSame(
            '-9999999999999.999999',
            Money::fromDecimal('-9999999999999.999999', 'EUR')->amount(),
        );

        foreach (['10000000000000.000000', '-10000000000000.000000'] as $amount) {
            $this->assertInvalidMoney(static fn (): Money => Money::fromDecimal($amount, 'EUR'));
        }
    }

    public function test_it_rejects_malformed_exponent_and_over_scale_decimal_input(): void
    {
        foreach (['', ' 1.000000', '1e3', '12.1234567'] as $amount) {
            $this->assertInvalidMoney(static fn (): Money => Money::fromDecimal($amount, 'EUR'));
        }
    }

    public function test_it_rejects_invalid_currency_codes(): void
    {
        foreach (['', 'EU', 'EURO', 'E1R'] as $currency) {
            $this->assertInvalidMoney(static fn (): Money => Money::fromDecimal('1.000000', $currency));
        }
    }

    public function test_money_is_readonly(): void
    {
        $this->assertTrue((new \ReflectionClass(Money::class))->isReadOnly());
    }

    public function test_vat_breakdown_is_readonly(): void
    {
        $this->assertTrue((new \ReflectionClass(VatBreakdown::class))->isReadOnly());
    }

    public function test_exact_addition_subtraction_and_multiplication_preserve_currency_and_decimal_strings(): void
    {
        $calculator = new MoneyCalculator;
        $first = Money::fromDecimal('12.500000', 'EUR');
        $second = Money::fromDecimal('0.200000', 'EUR');

        $sum = $calculator->add($first, $second);

        $this->assertSame('12.700000', $sum->amount());
        $this->assertSame('12.300000', $calculator->subtract($first, $second)->amount());
        $this->assertSame('50.000000', $calculator->multiply($first, '4.000000')->amount());
        $this->assertSame('EUR', $sum->currency());
        $this->assertNotSame($first, $sum);
        $this->assertSame('12.500000', $first->amount());
        $this->assertSame('0.200000', $second->amount());
    }

    public function test_half_up_rounding_is_explicit_at_the_business_result_boundary(): void
    {
        $calculator = new MoneyCalculator;

        $this->assertSame('100.01', $calculator->round(Money::fromDecimal('100.005000', 'EUR'), 2)->amount());
        $this->assertSame('-100.01', $calculator->round(Money::fromDecimal('-100.005000', 'EUR'), 2)->amount());
    }

    public function test_mixed_currencies_are_rejected_instead_of_converted(): void
    {
        $calculator = new MoneyCalculator;

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('INVALID_MONEY');

        $calculator->add(
            Money::fromDecimal('1.000000', 'EUR'),
            Money::fromDecimal('1.000000', 'USD'),
        );
    }

    /** @param callable(): Money $operation */
    private function assertInvalidMoney(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Invalid authoritative money input was accepted.');
        } catch (DomainException $exception) {
            $this->assertSame('INVALID_MONEY', $exception->getMessage());
        }
    }
}
