<?php

namespace Tests\Accounting\Integration;

use App\Domain\Budget\Queries\AnnualBudgetQuery;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Reporting\Data\EconomicReportFilterData;
use App\Domain\Reporting\Queries\AnnualEconomicReportQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class AnnualBudgetDatasetTest extends TestCase
{
    use DatabaseTransactions;

    public function test_budget_and_all_five_report_groupings_share_exact_plafond_reconciled_totals(): void
    {
        app(PermissionCatalogueSeeder::class)->run();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($actor);
        $context = new TenantContext($tenant, $actor);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $center = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();

        $plafond = $this->expense($tenant, $year, $center, $vendor, ExpenseKind::Plafond, 'Plafond', '100.00', '100.00');
        $consumer = $this->expense($tenant, $year, $center, $vendor, ExpenseKind::Ordinary, 'Consumer', '130.00', '130.00', (int) $plafond->getKey());
        ExpenseRow::factory()->for($consumer)->create([
            'tenant_id' => $tenant->getKey(),
            'vendor_id' => $vendor->getKey(),
            'position' => 2,
            'type' => ExpenseType::Actual,
            'spend_date' => '2026-05-01',
            'entered_amount' => '140.00',
            'net_amount' => '140.00',
            'vat_amount' => '30.80',
            'gross_amount' => '170.80',
        ]);

        $budget = app(AnnualBudgetQuery::class)->execute($actor, $context, (int) $year->getKey());
        $this->assertSame('130.00', $budget['summary']['proposed']);
        $this->assertSame('130.00', $budget['summary']['approved_current']);
        $this->assertSame('140.00', $budget['summary']['actual']);
        $this->assertSame('40.00', $budget['summary']['plafond_overrun']);

        foreach (['cost_center', 'project', 'contract', 'vendor', 'expense'] as $groupBy) {
            $report = app(AnnualEconomicReportQuery::class)->execute(
                $actor,
                $context,
                new EconomicReportFilterData(
                    planningYearId: (int) $year->getKey(),
                    groupBy: $groupBy,
                    perPage: 100,
                ),
            );

            $this->assertSame($budget['summary']['proposed'], $report['summary']['proposed']);
            $this->assertSame($budget['summary']['approved_current'], $report['summary']['approved_current']);
            $this->assertSame($budget['summary']['actual'], $report['summary']['actual']);
            $this->assertSame($budget['summary']['plafond_overrun'], $report['global_plafond_overrun']);
            $this->assertSame('130.00', $this->sum($report['data'], 'proposed'), "Proposed mismatch for {$groupBy}.");
            $this->assertSame('130.00', $this->sum($report['data'], 'approved'), "Approved mismatch for {$groupBy}.");
            $this->assertSame('140.00', $this->sum($report['data'], 'actual'), "Actual mismatch for {$groupBy}.");
        }
    }

    private function expense(
        Tenant $tenant,
        PlanningYear $year,
        CostCenter $center,
        Vendor $vendor,
        ExpenseKind $kind,
        string $title,
        string $planned,
        string $approved,
        ?int $fundedPlafondId = null,
    ): Expense {
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(),
            'cost_center_id' => $center->getKey(),
            'kind' => $kind,
            'title' => $title,
            'approved_amount' => $approved,
            'approved_basis' => 'net',
        ]);
        $row = ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(),
            'vendor_id' => $vendor->getKey(),
            'type' => ExpenseType::Quote,
            'spend_date' => null,
            'entered_amount' => $planned,
            'net_amount' => $planned,
            'vat_amount' => bcmul($planned, '0.22', 2),
            'gross_amount' => bcmul($planned, '1.22', 2),
            'funded_plafond_expense_id' => $fundedPlafondId,
        ]);
        $expense->forceFill(['current_planning_row_id' => $row->getKey()])->saveQuietly();

        return $expense;
    }

    /** @param list<array<string, mixed>> $groups */
    private function sum(array $groups, string $field): string
    {
        return array_reduce($groups, static fn (string $sum, array $group): string => bcadd($sum, (string) $group[$field], 2), '0.00');
    }
}
