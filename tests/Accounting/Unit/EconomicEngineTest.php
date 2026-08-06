<?php

namespace Tests\Accounting\Unit;

use App\Domain\Economics\Data\EconomicDataset;
use App\Domain\Economics\Data\EconomicLine;
use App\Domain\Economics\Data\EconomicScope;
use App\Domain\Economics\Services\EconomicEngine;
use App\Domain\Tenancy\Enums\BudgetBasis;
use Tests\TestCase;

class EconomicEngineTest extends TestCase
{
    public function test_extra_is_a_server_calculated_official_basis_component(): void
    {
        $dataset = new EconomicDataset(
            new EconomicScope(1, 1, 2026, 'EUR', BudgetBasis::Net),
            [new EconomicLine(1, 1, 'ordinary', 'actual', 'confirmed', 1, 'Operations', '100.00', '22.00', '122.00', null, '2026-05-01', null, null, null, true)],
        );

        $summary = app(EconomicEngine::class)->calculate($dataset)['summary'];

        $this->assertSame('100.00', $summary->amounts['extra']);
        $this->assertSame('100.00', $summary->amounts['actual']);
        $this->assertSame('100.00', $summary->amounts['officialCurrentPosition']);
    }

    public function test_components_and_actual_confirmation_states_remain_independent_on_gross_basis(): void
    {
        $dataset = new EconomicDataset(
            new EconomicScope(1, 1, 2026, 'EUR', BudgetBasis::Gross),
            [
                $this->line(1, 1, 'estimate', null, '100.00', '22.00', '122.00'),
                $this->line(2, 2, 'quote', null, '200.00', '44.00', '244.00'),
                $this->line(3, 3, 'actual', 'to_confirm', '50.00', '11.00', '61.00'),
                $this->line(4, 4, 'actual', 'confirmed', '30.00', '6.60', '36.60'),
            ],
        );

        $summary = app(EconomicEngine::class)->calculate($dataset)['summary'];

        $this->assertSame('gross', $summary->officialBasis);
        $this->assertSame('122.00', $summary->amounts['estimate']);
        $this->assertSame('244.00', $summary->amounts['quote']);
        $this->assertSame('97.60', $summary->amounts['actual']);
        $this->assertSame('61.00', $summary->amounts['actualToConfirm']);
        $this->assertSame('36.60', $summary->amounts['actualConfirmed']);
        $this->assertSame('463.60', $summary->amounts['officialCurrentPosition']);
    }

    public function test_plafond_residual_and_overrun_reconcile_without_counting_covered_consumption_twice(): void
    {
        $dataset = new EconomicDataset(
            new EconomicScope(1, 1, 2026, 'EUR', BudgetBasis::Net),
            [
                $this->line(10, 10, 'estimate', null, '1000.00', '220.00', '1220.00', 'plafond'),
                $this->line(11, 11, 'actual', 'confirmed', '400.00', '88.00', '488.00', fundedPlafondExpenseId: 10),
                $this->line(20, 20, 'estimate', null, '500.00', '110.00', '610.00', 'plafond'),
                $this->line(21, 21, 'actual', 'confirmed', '700.00', '154.00', '854.00', fundedPlafondExpenseId: 20),
            ],
        );

        $summary = app(EconomicEngine::class)->calculate($dataset)['summary'];

        $this->assertSame('1500.00', $summary->amounts['plafondAllocated']);
        $this->assertSame('1100.00', $summary->amounts['plafondConsumed']);
        $this->assertSame('600.00', $summary->amounts['plafondResidual']);
        $this->assertSame('200.00', $summary->amounts['plafondOverrun']);
        $this->assertSame('1700.00', $summary->amounts['officialCurrentPosition']);
        $this->assertSame('1700.00', $summary->amounts['net']);
    }

    private function line(
        int $expenseId,
        int $rowId,
        string $type,
        ?string $confirmationState,
        string $net,
        string $vat,
        string $gross,
        string $expenseKind = 'ordinary',
        ?int $fundedPlafondExpenseId = null,
    ): EconomicLine {
        return new EconomicLine(
            $expenseId,
            $rowId,
            $expenseKind,
            $type,
            $confirmationState,
            1,
            'Operations',
            $net,
            $vat,
            $gross,
            $fundedPlafondExpenseId,
            '2026-05-01',
            null,
            null,
            null,
        );
    }
}
