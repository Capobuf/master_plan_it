<?php

namespace Tests\Accounting\Unit;

use App\Domain\Money\Money;
use App\Domain\Money\Services\MonthlyAllocator;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;

class MonthlyAllocatorTest extends TestCase
{
    public function test_all_distribution_splits_months_and_assigns_the_residual_to_the_last_eligible_month(): void
    {
        $allocation = (new MonthlyAllocator)->allocate(
            Money::fromDecimal('10.000000', 'EUR'),
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-03-31'),
            'all',
        );

        $this->assertAllocation($allocation, [
            '2026-01' => '3.33',
            '2026-02' => '3.33',
            '2026-03' => '3.34',
        ]);
    }

    public function test_start_distribution_assigns_the_full_amount_to_the_first_eligible_month(): void
    {
        $allocation = (new MonthlyAllocator)->allocate(
            Money::fromDecimal('10.000000', 'EUR'),
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-03-31'),
            'start',
        );

        $this->assertAllocation($allocation, [
            '2026-01' => '10.00',
        ]);
    }

    public function test_end_distribution_assigns_the_full_amount_to_the_last_eligible_month(): void
    {
        $allocation = (new MonthlyAllocator)->allocate(
            Money::fromDecimal('10.000000', 'EUR'),
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-03-31'),
            'end',
        );

        $this->assertAllocation($allocation, [
            '2026-03' => '10.00',
        ]);
    }

    public function test_all_distribution_assigns_a_negative_residual_to_the_last_eligible_month(): void
    {
        $allocation = (new MonthlyAllocator)->allocate(
            Money::fromDecimal('10.010000', 'EUR'),
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-03-31'),
            'all',
        );

        $this->assertAllocation($allocation, [
            '2026-01' => '3.34',
            '2026-02' => '3.34',
            '2026-03' => '3.33',
        ], '10.01');
    }

    public function test_all_distribution_assigns_a_minimal_cent_residual_to_the_last_eligible_month(): void
    {
        $allocation = (new MonthlyAllocator)->allocate(
            Money::fromDecimal('0.010000', 'EUR'),
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-03-31'),
            'all',
        );

        $this->assertAllocation($allocation, [
            '2026-01' => '0.00',
            '2026-02' => '0.00',
            '2026-03' => '0.01',
        ], '0.01');
    }

    public function test_inverted_period_is_rejected_with_the_stable_date_error(): void
    {
        $this->assertAllocationRejected(
            new DateTimeImmutable('2026-03-31'),
            new DateTimeImmutable('2026-01-01'),
            'all',
        );
    }

    public function test_unknown_distribution_is_rejected_with_the_stable_date_error(): void
    {
        $this->assertAllocationRejected(
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-03-31'),
            'middle',
        );
    }

    /**
     * @param  array<string, Money>  $allocation
     * @param  array<string, string>  $expectedAmounts
     */
    private function assertAllocation(
        array $allocation,
        array $expectedAmounts,
        string $expectedTotal = '10.00',
    ): void {
        $this->assertSame(array_keys($expectedAmounts), array_keys($allocation));

        foreach ($expectedAmounts as $month => $amount) {
            $this->assertInstanceOf(Money::class, $allocation[$month]);
            $this->assertSame($amount, $allocation[$month]->amount());
            $this->assertSame('EUR', $allocation[$month]->currency());
        }

        $total = '0.00';
        foreach ($allocation as $money) {
            $total = bcadd($total, $money->amount(), 2);
        }

        $this->assertSame($expectedTotal, $total);
    }

    private function assertAllocationRejected(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        string $distribution,
    ): void {
        try {
            (new MonthlyAllocator)->allocate(
                Money::fromDecimal('10.000000', 'EUR'),
                $start,
                $end,
                $distribution,
            );
            $this->fail('Invalid monthly allocation input was accepted.');
        } catch (DomainException $exception) {
            $this->assertSame('DATE_MODE_CONFLICT', $exception->getMessage());
        }
    }
}
