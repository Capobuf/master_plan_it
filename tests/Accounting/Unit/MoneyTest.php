<?php

namespace Tests\Accounting\Unit;

use App\Domain\Money\Money;
use App\Domain\Money\Services\MoneyCalculator;
use App\Domain\Money\VatBreakdown;
use DomainException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_normalizes_authoritative_money_to_exactly_two_places(): void
    {
        $this->assertSame('1.00', Money::fromDecimal('1', 'EUR')->amount());
        $this->assertSame('1.20', Money::fromDecimal('1.2', 'EUR')->amount());
        $this->assertSame('1.23', Money::fromDecimal('1.23', 'EUR')->amount());

        $money = Money::fromDecimal('-12.5', 'eur');
        $this->assertSame('-12.50', $money->amount());
        $this->assertSame('EUR', $money->currency());
    }

    public function test_it_rejects_input_with_more_than_two_decimal_places(): void
    {
        $this->assertInvalidMoney(static fn (): Money => Money::fromDecimal('1.234', 'EUR'));
    }

    public function test_it_accepts_the_decimal_19_2_boundaries_and_rejects_range_overflow(): void
    {
        $this->assertSame(
            '99999999999999999.99',
            Money::fromDecimal('99999999999999999.99', 'EUR')->amount(),
        );
        $this->assertSame(
            '-99999999999999999.99',
            Money::fromDecimal('-99999999999999999.99', 'EUR')->amount(),
        );

        foreach (['100000000000000000.00', '-100000000000000000.00'] as $amount) {
            $this->assertInvalidMoney(static fn (): Money => Money::fromDecimal($amount, 'EUR'));
        }
    }

    public function test_it_rejects_malformed_exponent_and_over_scale_decimal_input(): void
    {
        foreach (['', ' 1.00', '1e3', '12.123'] as $amount) {
            $this->assertInvalidMoney(static fn (): Money => Money::fromDecimal($amount, 'EUR'));
        }
    }

    public function test_it_rejects_invalid_currency_codes(): void
    {
        foreach (['', 'EU', 'EURO', 'E1R'] as $currency) {
            $this->assertInvalidMoney(static fn (): Money => Money::fromDecimal('1.00', $currency));
        }
    }

    public function test_money_and_vat_breakdown_are_readonly(): void
    {
        $this->assertTrue((new \ReflectionClass(Money::class))->isReadOnly());
        $this->assertTrue((new \ReflectionClass(VatBreakdown::class))->isReadOnly());
    }

    public function test_addition_subtraction_and_multiplication_return_canonical_money(): void
    {
        $calculator = new MoneyCalculator;
        $first = Money::fromDecimal('12.50', 'EUR');
        $second = Money::fromDecimal('0.20', 'EUR');

        $sum = $calculator->add($first, $second);

        $this->assertSame('12.70', $sum->amount());
        $this->assertSame('12.30', $calculator->subtract($first, $second)->amount());
        $this->assertSame('50.00', $calculator->multiply($first, '4.00')->amount());
        $this->assertSame('EUR', $sum->currency());
        $this->assertNotSame($first, $sum);
        $this->assertSame('12.50', $first->amount());
        $this->assertSame('0.20', $second->amount());
    }

    public function test_multiplication_uses_high_precision_and_rounds_half_up_to_canonical_scale(): void
    {
        $calculator = new MoneyCalculator;

        $this->assertSame('13.19', $calculator->multiply(Money::fromDecimal('10.55', 'EUR'), '1.25')->amount());
        $this->assertSame('-13.19', $calculator->multiply(Money::fromDecimal('-10.55', 'EUR'), '1.25')->amount());
    }

    public function test_mixed_currencies_are_rejected_instead_of_converted(): void
    {
        $calculator = new MoneyCalculator;

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('INVALID_MONEY');

        $calculator->add(
            Money::fromDecimal('1.00', 'EUR'),
            Money::fromDecimal('1.00', 'USD'),
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
