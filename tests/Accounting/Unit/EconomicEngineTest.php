<?php

namespace Tests\Accounting\Unit;

use App\Domain\Economics\Data\EconomicDataset;
use App\Domain\Economics\Data\EconomicLine;
use App\Domain\Economics\Data\EconomicScope;
use App\Domain\Economics\Services\EconomicEngine;
use App\Domain\Tenancy\Enums\BudgetBasis;
use PHPUnit\Framework\TestCase;

final class EconomicEngineTest extends TestCase
{
    public function test_projection_uses_one_selected_planning_row_and_all_actuals_on_the_official_basis(): void
    {
        $projection = (new EconomicEngine)->project(new EconomicDataset(
            new EconomicScope(7, 9, 2026, 'EUR', BudgetBasis::Gross),
            [
                $this->line(1, 10, 'estimate', false, '100.00', '22.00', '122.00'),
                $this->line(1, 11, 'quote', true, '110.00', '24.20', '134.20'),
                $this->line(1, 12, 'actual', false, '40.00', '8.80', '48.80', '2027-01-05'),
                $this->line(1, 13, 'actual', false, '-5.00', '-1.10', '-6.10', '2025-12-31'),
                $this->line(2, 20, 'actual', false, '10.00', '2.20', '12.20', '2026-03-01'),
            ],
        ));

        $this->assertSame('gross', $projection->basis);
        $this->assertSame('134.20', $projection->currentPlanning->official);
        $this->assertSame('54.90', $projection->actual->official);
        $this->assertSame(11, $projection->expenses[1]->currentPlanningRowId);
        $this->assertNull($projection->expenses[2]->currentPlanningRowId);
        $this->assertSame('12.20', $projection->expenses[2]->actual->official);
        $this->assertCount(5, $projection->lines);
        $this->assertFalse($projection->lines[0]->contributesToCurrentPlanning);
        $this->assertTrue($projection->lines[1]->contributesToCurrentPlanning);
        $this->assertSame('2027-01-05', $projection->lines[2]->spendDate);
    }

    public function test_projection_preserves_line_context_without_using_project_stage_as_money(): void
    {
        $line = new EconomicLine(
            1,
            2,
            'ordinary',
            'quote',
            null,
            3,
            'Operations',
            '10.00',
            '2.20',
            '12.20',
            null,
            null,
            null,
            null,
            null,
            false,
            4,
            'rejected',
            'Project',
            true,
            'Licenza',
            'Rinnovo',
            5,
            'Vendor',
            6,
        );

        $projection = (new EconomicEngine)->project(new EconomicDataset(
            new EconomicScope(7, 9, 2026, 'EUR', BudgetBasis::Net),
            [$line],
        ));

        $this->assertSame('10.00', $projection->currentPlanning->official);
        $this->assertSame('0.00', $projection->actual->official);
        $this->assertSame('Licenza', $projection->lines[0]->description);
        $this->assertSame('Rinnovo', $projection->lines[0]->notes);
        $this->assertSame(4, $projection->lines[0]->projectId);
        $this->assertSame(5, $projection->lines[0]->vendorId);
        $this->assertSame(6, $projection->lines[0]->contractId);
    }

    private function line(
        int $expenseId,
        int $rowId,
        string $type,
        bool $selected,
        string $net,
        string $vat,
        string $gross,
        ?string $spendDate = null,
    ): EconomicLine {
        return new EconomicLine(
            $expenseId,
            $rowId,
            'ordinary',
            $type,
            null,
            1,
            'Operations',
            $net,
            $vat,
            $gross,
            null,
            $spendDate,
            null,
            null,
            null,
            false,
            null,
            null,
            null,
            $selected,
        );
    }
}
