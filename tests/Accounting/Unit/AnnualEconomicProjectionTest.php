<?php

namespace Tests\Accounting\Unit;

use App\Domain\Economics\Data\EconomicDataset;
use App\Domain\Economics\Data\EconomicLine;
use App\Domain\Economics\Data\EconomicMeasure;
use App\Domain\Economics\Data\EconomicScope;
use App\Domain\Economics\Services\EconomicEngine;
use App\Domain\Tenancy\Enums\BudgetBasis;
use PHPUnit\Framework\TestCase;

final class AnnualEconomicProjectionTest extends TestCase
{
    public function test_target_projection_keeps_only_selected_planning_and_all_current_actuals(): void
    {
        $engine = new EconomicEngine;
        $scope = new EconomicScope(1, 2, 2025, 'EUR', BudgetBasis::Net);
        $line = static fn (int $row, string $type, bool $selected, string $net, string $vat, string $gross): EconomicLine => new EconomicLine(1, $row, 'ordinary', $type, null, 1, 'Centro', $net, $vat, $gross, null, $type === 'actual' ? '2026-02-10' : null, null, null, null, false, null, null, null, $selected);
        $projection = $engine->project(new EconomicDataset($scope, [
            $line(1, 'estimate', false, '100.00', '22.00', '122.00'),
            $line(2, 'quote', true, '110.00', '24.20', '134.20'),
            $line(3, 'actual', false, '40.00', '8.80', '48.80'),
            $line(4, 'actual', false, '-5.00', '-1.10', '-6.10'),
        ]));

        $this->assertSame('110.00', $projection->currentPlanning->official);
        $this->assertSame('35.00', $projection->actual->official);
    }

    public function test_reconciliation_invariant_has_a_dedicated_non_fallback_failure_code(): void
    {
        try {
            EconomicMeasure::fromAmounts('1.00', '1.00', '3.00', 'net');
            $this->fail('An inconsistent economic measure was accepted.');
        } catch (\DomainException $exception) {
            $this->assertSame('ECONOMIC_RECONCILIATION_FAILED', $exception->getMessage());
        }
    }

    public function test_measure_selects_the_official_basis_and_rejects_unknown_bases(): void
    {
        $gross = EconomicMeasure::fromAmounts('100.00', '22.00', '122.00', 'gross');

        $this->assertSame('122.00', $gross->official);
        $this->assertSame('0.00', EconomicMeasure::zero('net')->official);

        try {
            EconomicMeasure::fromAmounts('100.00', '22.00', '122.00', 'unsupported');
            $this->fail('An unsupported basis was accepted.');
        } catch (\DomainException $exception) {
            $this->assertSame('ECONOMIC_RECONCILIATION_FAILED', $exception->getMessage());
        }
    }
}
