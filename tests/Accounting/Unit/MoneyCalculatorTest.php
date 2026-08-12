<?php

namespace Tests\Accounting\Unit;

use App\Domain\Economics\Services\MoneyCalculator;
use DomainException;
use PHPUnit\Framework\TestCase;

final class MoneyCalculatorTest extends TestCase
{
    public function test_target_money_calculator_enforces_the_closed_decimal_grammar_and_direct_calculated_xor(): void
    {
        $class = 'App\\Domain\\Economics\\Services\\MoneyCalculator';
        $this->assertTrue(class_exists($class));

        $calculator = app($class);
        $this->assertSame('0.00', $calculator->normalize('-0.00'));
        foreach (['+1.00', ' 1.00', '01.00', '1,00', '1e2', '1.001'] as $invalid) {
            try {
                $calculator->normalize($invalid);
                $this->fail($invalid.' must be rejected.');
            } catch (DomainException $exception) {
                $this->assertSame('INVALID_DECIMAL', $exception->getMessage());
            }
        }
        $this->assertSame('0.01', $calculator->multiply('0.01', '0.50'));
        $this->assertSame('-0.01', $calculator->multiply('-0.01', '0.50'));
    }

    public function test_exact_arithmetic_covers_sign_rounding_division_and_overflow_boundaries(): void
    {
        $calculator = new MoneyCalculator;

        $this->assertSame('1.00', $calculator->normalize('1'));
        $this->assertSame('1.20', $calculator->normalize('1.2'));
        $this->assertSame('0.00', $calculator->rounded('-0.004'));
        $this->assertSame('0.01', $calculator->rounded('0.005'));
        $this->assertSame('-0.01', $calculator->rounded('-0.005'));
        $this->assertSame('3.00', $calculator->add('1.00', '2.00'));
        $this->assertSame('-1.00', $calculator->subtract('1.00', '2.00'));
        $this->assertSame('0.33', $calculator->divide('1.00', '3.00'));

        $this->assertDomainFailure(
            static fn (): string => $calculator->normalize('-1.00', false),
            'INVALID_DECIMAL',
        );
        $this->assertDomainFailure(
            static fn (): string => $calculator->divide('1.00', '0.00'),
            'INVALID_DECIMAL',
        );
        $this->assertDomainFailure(
            static fn (): string => $calculator->normalize('100000000000000000.00'),
            'DECIMAL_OVERFLOW',
        );
        $this->assertDomainFailure(
            static fn (): string => $calculator->rounded('100000000000000000.005'),
            'DECIMAL_OVERFLOW',
        );
    }

    /** @param callable(): string $operation */
    private function assertDomainFailure(callable $operation, string $code): void
    {
        try {
            $operation();
            $this->fail("Expected {$code}.");
        } catch (DomainException $exception) {
            $this->assertSame($code, $exception->getMessage());
        }
    }
}
