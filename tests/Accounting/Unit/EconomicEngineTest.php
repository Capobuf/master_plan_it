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

    public function test_planning_and_actual_are_immediately_effective_on_gross_basis_without_confirmation_buckets(): void
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
        $this->assertSame('366.00', $summary->amounts['planned']);
        $this->assertSame('0.00', $summary->amounts['actualToConfirm']);
        $this->assertSame('0.00', $summary->amounts['actualConfirmed']);
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
        $this->assertSame('1700.00', $summary->amounts['primary']);
        $this->assertSame('1700.00', $summary->amounts['potential']);
    }

    public function test_project_stage_never_reclassifies_current_economic_lines(): void
    {
        $lines = [];
        $rowId = 1;
        foreach (['estimate', 'quote'] as $type) {
            $lines[] = $this->line($rowId, $rowId++, $type, null, '10.00', '2.20', '12.20');
            foreach ([
                'approved' => 'primary', 'proposed' => 'primary', 'idea' => 'primary',
                'deferred' => 'primary', 'rejected' => 'primary',
            ] as $stage => $bucket) {
                $line = $this->line($rowId, $rowId++, $type, null, '10.00', '2.20', '12.20', projectStage: $stage);
                $this->assertSame($bucket, app(EconomicEngine::class)->classify($line));
                $lines[] = $line;
            }
        }
        foreach ([null, 'approved', 'proposed', 'idea', 'deferred', 'rejected'] as $stage) {
            $line = $this->line($rowId, $rowId++, 'actual', 'to_confirm', '10.00', '2.20', '12.20', projectStage: $stage);
            $this->assertSame('primary', app(EconomicEngine::class)->classify($line));
            $lines[] = $line;
        }

        $amounts = app(EconomicEngine::class)->calculate(new EconomicDataset(
            new EconomicScope(1, 1, 2026, 'EUR', BudgetBasis::Net), $lines,
        ))['summary']->amounts;

        $this->assertSame('180.00', $amounts['primary']);
        $this->assertSame('0.00', $amounts['proposed']);
        $this->assertSame('0.00', $amounts['idea']);
        $this->assertSame('0.00', $amounts['excluded']);
        $this->assertSame('180.00', $amounts['potential']);
        $this->assertSame($amounts['primary'], $amounts['officialCurrentPosition']);
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
        ?string $projectStage = null,
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
            false,
            $projectStage === null ? null : 99,
            $projectStage,
        );
    }
}
