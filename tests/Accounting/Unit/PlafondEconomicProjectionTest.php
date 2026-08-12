<?php

namespace Tests\Accounting\Unit;

use App\Domain\Economics\Data\EconomicDataset;
use App\Domain\Economics\Data\EconomicLine;
use App\Domain\Economics\Data\EconomicScope;
use App\Domain\Economics\Services\EconomicEngine;
use App\Domain\Tenancy\Enums\BudgetBasis;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlafondEconomicProjectionTest extends TestCase
{
    #[DataProvider('bases')]
    public function test_projection_classifies_allocation_planning_consumption_and_annual_totals_once(
        BudgetBasis $basis,
        string $allocation,
        string $planned,
        string $consumed,
        string $available,
    ): void {
        $projection = (new EconomicEngine)->project(new EconomicDataset(
            new EconomicScope(1, 10, 2026, 'EUR', $basis),
            [
                $this->line(41, 1, 'plafond', 'allocation_adjustment', false, '3000.00', '660.00', '3660.00'),
                $this->line(41, 2, 'plafond', 'allocation_adjustment', false, '1000.00', '220.00', '1220.00'),
                $this->line(41, 3, 'plafond', 'allocation_adjustment', false, '-500.00', '-110.00', '-610.00'),
                $this->line(81, 4, 'ordinary', 'quote', true, '4200.00', '924.00', '5124.00', 41, 12, 'Applicazioni'),
                $this->line(81, 5, 'ordinary', 'actual', false, '2500.00', '550.00', '3050.00', 41, 12, 'Applicazioni'),
            ],
        ));

        $plafond = $projection->plafonds[41];
        $this->assertSame($allocation, $plafond->allocation->official);
        $this->assertSame($planned, $plafond->coveragePlanned->official);
        $this->assertSame($consumed, $plafond->consumed->official);
        $this->assertSame($available, $plafond->available->official);
        $this->assertSame($allocation, $projection->currentPlanning->official);
        $this->assertSame($consumed, $projection->actual->official);
        $this->assertCount(3, $plafond->allocationLines);
        $this->assertCount(2, $plafond->coveredLines);
        $this->assertSame(12, $plafond->coveredLines[0]->costCenterId);
        $this->assertTrue($plafond->coveredLines[0]->contributesToCoveragePlanned);
        $this->assertTrue($plafond->coveredLines[1]->contributesToConsumed);
    }

    /** @return iterable<string, array{BudgetBasis, string, string, string, string}> */
    public static function bases(): iterable
    {
        yield 'net' => [BudgetBasis::Net, '3500.00', '4200.00', '2500.00', '1000.00'];
        yield 'gross' => [BudgetBasis::Gross, '4270.00', '5124.00', '3050.00', '1220.00'];
    }

    public function test_negative_actual_is_not_clamped_and_planning_above_capacity_is_informational(): void
    {
        $projection = (new EconomicEngine)->project(new EconomicDataset(
            new EconomicScope(1, 10, 2026, 'EUR', BudgetBasis::Net),
            [
                $this->line(41, 1, 'plafond', 'allocation_adjustment', false, '100.00', '22.00', '122.00'),
                $this->line(81, 2, 'ordinary', 'estimate', true, '500.00', '110.00', '610.00', 41),
                $this->line(81, 3, 'ordinary', 'actual', false, '-25.00', '-5.50', '-30.50', 41),
            ],
        ));

        $this->assertSame('500.00', $projection->plafonds[41]->coveragePlanned->official);
        $this->assertSame('-25.00', $projection->plafonds[41]->consumed->official);
        $this->assertSame('125.00', $projection->plafonds[41]->available->official);
    }

    public function test_projection_rejects_every_invalid_kind_or_funding_shape(): void
    {
        $invalidDatasets = [
            [$this->line(41, 1, 'plafond', 'actual', false, '1.00', '0.00', '1.00')],
            [$this->line(81, 1, 'ordinary', 'allocation_adjustment', false, '1.00', '0.00', '1.00')],
            [$this->line(81, 1, 'ordinary', 'quote', true, '1.00', '0.00', '1.00', 999)],
        ];

        foreach ($invalidDatasets as $lines) {
            try {
                (new EconomicEngine)->project(new EconomicDataset(
                    new EconomicScope(1, 10, 2026, 'EUR', BudgetBasis::Net),
                    $lines,
                ));
                $this->fail('An invalid Plafond projection shape was accepted.');
            } catch (DomainException $exception) {
                $this->assertSame('ECONOMIC_RECONCILIATION_FAILED', $exception->getMessage());
            }
        }
    }

    private function line(
        int $expenseId,
        int $rowId,
        string $kind,
        string $type,
        bool $selected,
        string $net,
        string $vat,
        string $gross,
        ?int $plafondId = null,
        int $costCenterId = 9,
        string $costCenterName = 'Infrastruttura',
    ): EconomicLine {
        return new EconomicLine(
            $expenseId, $rowId, $kind, $type, null, $costCenterId, $costCenterName,
            $net, $vat, $gross, $plafondId, $type === 'actual' || $type === 'allocation_adjustment' ? '2026-08-12' : null,
            null, null, null, false, null, null, null, $selected,
            expenseTitle: $kind === 'plafond' ? 'Plafond Infrastruttura' : 'Licenze',
            fundedPlafondTitle: $plafondId === null ? null : 'Plafond Infrastruttura',
            fundedPlafondCostCenterId: $plafondId === null ? null : 9,
            fundedPlafondCostCenterName: $plafondId === null ? null : 'Infrastruttura',
        );
    }
}
