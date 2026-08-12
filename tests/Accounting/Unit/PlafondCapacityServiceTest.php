<?php

namespace Tests\Accounting\Unit;

use App\Domain\Budget\Enums\BudgetState;
use App\Domain\Economics\Data\AnnualEconomicProjection;
use App\Domain\Economics\Data\EconomicMeasure;
use App\Domain\Economics\Data\PlafondEconomicProjection;
use App\Domain\Economics\Data\ProjectedEconomicLine;
use App\Domain\Expenses\Exceptions\PlafondInsufficientException;
use App\Domain\Expenses\Services\PlafondCapacityService;
use App\Domain\Expenses\Services\PlafondLifecycleGuard;
use DomainException;
use PHPUnit\Framework\TestCase;

final class PlafondCapacityServiceTest extends TestCase
{
    public function test_capacity_uses_the_final_projection_and_returns_exact_shortage(): void
    {
        $service = new PlafondCapacityService;
        $current = $this->projection('3000.00', '500.00');
        $proposed = $this->projection('3000.00', '3200.00');
        $impact = $service->impact($current, $proposed, '2700.00');

        $this->assertSame('2500.00', $impact->current->available->official);
        $this->assertSame('-200.00', $impact->proposed->available->official);
        $this->assertSame('200.00', $impact->shortage);
        $this->assertFalse($impact->canConfirm);

        try {
            $service->assertSufficient($impact, 'rows.1.funded_plafond_expense_id');
            $this->fail('An insufficient final projection was accepted.');
        } catch (PlafondInsufficientException $exception) {
            $this->assertSame('PLAFOND_INSUFFICIENT', $exception->getMessage());
            $this->assertSame('3000.00', $exception->insufficiency->allocated);
            $this->assertSame('2500.00', $exception->insufficiency->available);
            $this->assertSame('2700.00', $exception->insufficiency->required);
            $this->assertSame('200.00', $exception->insufficiency->shortage);
        }
    }

    public function test_planning_above_available_and_exact_or_negative_consumption_are_allowed(): void
    {
        $service = new PlafondCapacityService;
        $planning = $this->projection('100.00', '100.00', '500.00');
        $negative = $this->projection('100.00', '-25.00', '500.00');

        $service->assertSufficient($service->impact($planning, $planning, '500.00'), 'row');
        $service->assertSufficient($service->impact($negative, $negative, '-25.00'), 'row');

        $this->assertSame('0.00', $planning->available->official);
        $this->assertSame('125.00', $negative->available->official);
    }

    public function test_lifecycle_guard_is_a_pure_preparation_decision(): void
    {
        $guard = new PlafondLifecycleGuard;
        $guard->assertPreparation(BudgetState::Preparation);
        $guard->assertPreparation('preparation');

        foreach ([BudgetState::Approved, BudgetState::Closed, 'approved'] as $state) {
            try {
                $guard->assertPreparation($state);
                $this->fail('A non-preparation state was accepted.');
            } catch (DomainException $exception) {
                $this->assertSame('BUDGET_STATE_CONFLICT', $exception->getMessage());
            }
        }
    }

    public function test_capacity_rejects_mismatched_projection_contexts(): void
    {
        $service = new PlafondCapacityService;
        $current = $this->projection('100.00', '0.00');
        $mismatches = [
            $this->projection('100.00', '0.00', id: 42),
            $this->projection('100.00', '0.00', currency: 'USD'),
            $this->projection('100.00', '0.00', basis: 'gross'),
        ];

        foreach ($mismatches as $proposed) {
            try {
                $service->impact($current, $proposed, '1.00');
                $this->fail('Mismatched projection contexts were accepted.');
            } catch (DomainException $exception) {
                $this->assertSame('ECONOMIC_RECONCILIATION_FAILED', $exception->getMessage());
            }
        }
    }

    public function test_annual_capacity_collects_deterministic_failures_and_sorts_blocking_actuals(): void
    {
        $service = new PlafondCapacityService;
        $planningLine = $this->projectedLine(8, false);
        $laterActual = $this->projectedLine(9, true);
        $earlierActual = $this->projectedLine(3, true);
        $insufficient = $this->projection(
            '100.00',
            '120.00',
            lines: [$laterActual, $planningLine, $earlierActual],
        );
        $valid = $this->projection('100.00', '50.00', id: 42);
        $annual = new AnnualEconomicProjection(
            1, 25, 2026, 'EUR', 'net', EconomicMeasure::zero('net'),
            EconomicMeasure::zero('net'), [], [], [42 => $valid, 41 => $insufficient],
        );

        $failures = $service->insufficiencies($annual, [41 => $this->projection('100.00', '20.00')]);
        $this->assertCount(1, $failures);
        $this->assertSame(41, $failures[0]->plafondExpenseId);
        $this->assertSame('80.00', $failures[0]->available);
        $this->assertSame([3, 9], array_column($failures[0]->impact->blockingRows, 'rowId'));

        try {
            $service->assertAnnualProjectionSufficient($annual);
            $this->fail('An insufficient annual projection was accepted.');
        } catch (PlafondInsufficientException $exception) {
            $this->assertSame(41, $exception->insufficiency->plafondExpenseId);
        }

        $service->assertAnnualProjectionSufficient(new AnnualEconomicProjection(
            1, 25, 2026, 'EUR', 'net', EconomicMeasure::zero('net'),
            EconomicMeasure::zero('net'), [], [], [42 => $valid],
        ));
    }

    private function projection(
        string $allocation,
        string $consumed,
        string $planned = '0.00',
        int $id = 41,
        string $currency = 'EUR',
        string $basis = 'net',
        array $lines = [],
    ): PlafondEconomicProjection {
        $measure = static fn (string $amount): EconomicMeasure => new EconomicMeasure(
            $amount, '0.00', $amount, $amount,
        );

        return new PlafondEconomicProjection(
            $id, 25, 'Plafond', 9, 'Infrastruttura', $currency, $basis,
            $measure($allocation), $measure($planned), $measure($consumed),
            $measure(bcsub($allocation, $consumed, 2)), [], $lines,
        );
    }

    private function projectedLine(int $rowId, bool $consumed): ProjectedEconomicLine
    {
        return new ProjectedEconomicLine(
            81, $rowId, 25, 2026, $consumed ? 'actual' : 'quote', false, false,
            $consumed ? '2026-01-01' : null, new EconomicMeasure('10.00', '0.00', '10.00', '10.00'),
            contributesToConsumed: $consumed,
        );
    }
}
