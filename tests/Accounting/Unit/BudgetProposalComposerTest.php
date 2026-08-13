<?php

namespace Tests\Accounting\Unit;

use App\Domain\Budget\Data\BudgetSourceAccess;
use App\Domain\Budget\Services\BudgetProposalComposer;
use App\Domain\Economics\Data\EconomicDataset;
use App\Domain\Economics\Data\EconomicLine;
use App\Domain\Economics\Data\EconomicScope;
use App\Domain\Economics\Services\EconomicEngine;
use App\Domain\Tenancy\Enums\BudgetBasis;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class BudgetProposalComposerTest extends TestCase
{
    public function test_it_uses_the_current_ordinary_plan_and_one_aggregate_plafond_allocation(): void
    {
        $proposal = app(BudgetProposalComposer::class)->compose(
            (new EconomicEngine)->project(new EconomicDataset(
                new EconomicScope(10, 20, 2026, 'EUR', BudgetBasis::Net),
                [
                    $this->line(1, 101, 'ordinary', 'estimate', '100.00', false),
                    $this->line(1, 102, 'ordinary', 'quote', '120.00', true),
                    $this->line(2, 201, 'plafond', 'allocation_adjustment', '2000.00', true),
                    $this->line(2, 202, 'plafond', 'allocation_adjustment', '1500.00', true),
                    $this->line(3, 301, 'ordinary', 'quote', '4200.00', true, 2),
                    $this->line(1, 103, 'ordinary', 'actual', '75.00', false),
                ],
            )),
            7,
            $this->fullAccess(),
            $this->metadata(),
        );

        $this->assertSame(['expense-row:102', 'plafond-allocation:2'], array_map(
            static fn ($contributor): string => $contributor->sourceIdentity,
            $proposal->contributors,
        ));
        $this->assertSame('3620.00', $proposal->total->net);
        $this->assertSame('796.40', $proposal->total->vat);
        $this->assertSame('4416.40', $proposal->total->gross);
        $this->assertSame(2, $proposal->composition->contributorCount);

        $reasons = collect($proposal->exclusions)->mapWithKeys(
            static fn ($exclusion): array => [$exclusion->sourceIdentity => $exclusion->reason],
        )->all();
        $this->assertSame('alternative_planning', $reasons['expense-row:101']);
        $this->assertSame('covered_by_plafond', $reasons['expense-row:301']);
        $this->assertSame('actual_not_proposed', $reasons['expense-row:103']);
        $this->assertSame('soft_deleted', $reasons['expense-row:999']);
    }

    #[DataProvider('zeroCompositionProvider')]
    public function test_empty_and_nonempty_zero_compositions_are_distinct(array $lines, int $count): void
    {
        $proposal = app(BudgetProposalComposer::class)->compose(
            (new EconomicEngine)->project(new EconomicDataset(
                new EconomicScope(10, 20, 2026, 'EUR', BudgetBasis::Net),
                $lines,
            )),
            7,
            $this->fullAccess(),
            $this->metadata(),
        );

        $this->assertSame($count, $proposal->composition->contributorCount);
        $this->assertSame('0.00', $proposal->total->official);
        $this->assertSame($count === 0, $proposal->isEmpty());
    }

    /** @return iterable<string, array{list<EconomicLine>, int}> */
    public static function zeroCompositionProvider(): iterable
    {
        $test = new self('test');

        yield 'empty' => [[], 0];
        yield 'zero contributor' => [[$test->line(1, 102, 'ordinary', 'quote', '0.00', true)], 1];
        yield 'offsetting contributors' => [[
            $test->line(1, 102, 'ordinary', 'quote', '100.00', true),
            $test->line(4, 401, 'ordinary', 'quote', '-100.00', true),
        ], 2];
    }

    private function line(
        int $expenseId,
        int $rowId,
        string $kind,
        string $type,
        string $net,
        bool $current,
        ?int $fundedPlafondId = null,
    ): EconomicLine {
        $vat = bcmul($net, '0.22', 2);

        return new EconomicLine(
            expenseId: $expenseId,
            rowId: $rowId,
            expenseKind: $kind,
            type: $type,
            confirmationState: null,
            costCenterId: $expenseId + 10,
            costCenterName: 'Centro '.$expenseId,
            net: $net,
            vat: $vat,
            gross: bcadd($net, $vat, 2),
            fundedPlafondExpenseId: $fundedPlafondId,
            spendDate: $type === 'actual' ? '2026-06-01' : null,
            periodStart: null,
            periodEnd: null,
            distribution: null,
            isCurrentPlanning: $current,
            description: 'Riga '.$rowId,
            vendorId: $kind === 'ordinary' ? 50 + $expenseId : null,
            vendorName: $kind === 'ordinary' ? 'Fornitore '.$expenseId : null,
            expenseTitle: 'Spesa '.$expenseId,
            fundedPlafondTitle: $fundedPlafondId === null ? null : 'Spesa '.$fundedPlafondId,
            fundedPlafondCostCenterId: $fundedPlafondId === null ? null : $fundedPlafondId + 10,
            fundedPlafondCostCenterName: $fundedPlafondId === null ? null : 'Centro '.$fundedPlafondId,
        );
    }

    /** @return array{expenses: array<int, array<string, mixed>>, rows: array<int, array<string, mixed>>} */
    private function metadata(): array
    {
        $expenses = [];
        foreach ([1, 2, 3, 4] as $id) {
            $expenses[$id] = [
                'id' => $id,
                'title' => 'Spesa '.$id,
                'kind' => $id === 2 ? 'plafond' : 'ordinary',
                'lock_version' => $id + 1,
                'cost_center_id' => $id + 10,
                'cost_center_name' => 'Centro '.$id,
                'project_id' => null,
                'project_title' => null,
                'contract_id' => null,
                'contract_title' => null,
                'deleted_at' => null,
            ];
        }

        $rows = [];
        foreach ([101, 102, 103, 201, 202, 301, 401] as $id) {
            $expenseId = intdiv($id, 100);
            $rows[$id] = [
                'id' => $id,
                'expense_id' => $expenseId,
                'lock_version' => $id,
                'type' => match ($id) {
                    101 => 'estimate', 103 => 'actual', 201, 202 => 'allocation_adjustment', default => 'quote'
                },
                'description' => 'Riga '.$id,
                'vendor_id' => $expenseId === 2 ? null : 50 + $expenseId,
                'vendor_name' => $expenseId === 2 ? null : 'Fornitore '.$expenseId,
                'net_amount' => '0.00',
                'vat_amount' => '0.00',
                'gross_amount' => '0.00',
                'deleted_at' => null,
                'expense_deleted_at' => null,
            ];
        }
        $rows[999] = [
            'id' => 999,
            'expense_id' => 1,
            'lock_version' => 1,
            'type' => 'estimate',
            'description' => 'Eliminata',
            'vendor_id' => null,
            'vendor_name' => null,
            'net_amount' => '10.00',
            'vat_amount' => '2.20',
            'gross_amount' => '12.20',
            'deleted_at' => '2026-01-01 00:00:00',
            'expense_deleted_at' => null,
        ];

        return ['expenses' => $expenses, 'rows' => $rows];
    }

    private function fullAccess(): BudgetSourceAccess
    {
        return new BudgetSourceAccess(true, true, true, true, true, true, true);
    }
}
