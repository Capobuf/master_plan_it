<?php

namespace Tests\Accounting\Integration;

use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Reporting\Data\EconomicReportFilterData;
use App\Domain\Reporting\Queries\EconomicDatasetQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\BudgetBasis;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class PlafondEconomicDatasetTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dataset_is_complete_before_cost_center_presentation_filters(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->for($tenant)->create(['is_active' => true]);
        $year = PlanningYear::factory()->for($tenant)->create();
        $plafondCenter = CostCenter::factory()->for($tenant)->create(['name' => 'Infrastruttura']);
        $consumerCenter = CostCenter::factory()->for($tenant)->create(['name' => 'Applicazioni']);
        $plafond = Expense::factory()->for($tenant)->plafond()->create([
            'planning_year_id' => $year->getKey(),
            'cost_center_id' => $plafondCenter->getKey(),
            'title' => 'Plafond Infrastruttura',
        ]);
        ExpenseRow::factory()->for($plafond)->allocationAdjustment($actor)->create([
            'tenant_id' => $tenant->getKey(),
        ]);
        $consumer = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(),
            'cost_center_id' => $consumerCenter->getKey(),
            'title' => 'Licenze',
        ]);
        $covered = ExpenseRow::factory()->for($consumer)->create([
            'tenant_id' => $tenant->getKey(),
            'type' => ExpenseType::Quote,
            'funded_plafond_expense_id' => $plafond->getKey(),
        ]);
        $consumer->forceFill(['current_planning_row_id' => $covered->getKey()])->saveQuietly();

        $dataset = app(EconomicDatasetQuery::class)->execute(
            $actor,
            new TenantContext($tenant, $actor),
            (int) $year->getKey(),
            new EconomicReportFilterData((int) $year->getKey(), costCenterId: (int) $plafondCenter->getKey()),
        );

        $this->assertCount(2, $dataset->lines);
        $line = collect($dataset->lines)->firstWhere('rowId', $covered->getKey());
        $this->assertNotNull($line);
        $this->assertSame($consumerCenter->getKey(), $line->costCenterId);
        $this->assertSame('Applicazioni', $line->costCenterName);
        $this->assertSame($plafondCenter->getKey(), $line->fundedPlafondCostCenterId);
        $this->assertSame('Infrastruttura', $line->fundedPlafondCostCenterName);
        $this->assertSame('Plafond Infrastruttura', $line->fundedPlafondTitle);
    }

    public function test_dataset_uses_persisted_scope_after_a_stale_context_was_created(): void
    {
        $tenant = Tenant::factory()->create(['budget_basis' => BudgetBasis::Net, 'currency_code' => 'EUR']);
        $actor = User::factory()->for($tenant)->create(['is_active' => true]);
        $year = PlanningYear::factory()->for($tenant)->create();
        $context = new TenantContext($tenant, $actor);

        Tenant::query()->whereKey($tenant->getKey())->update([
            'budget_basis' => BudgetBasis::Gross->value,
            'currency_code' => 'USD',
        ]);

        $persisted = app(EconomicDatasetQuery::class)->execute($actor, $context, (int) $year->getKey());
        $override = app(EconomicDatasetQuery::class)->execute(
            $actor,
            $context,
            (int) $year->getKey(),
            basisOverride: BudgetBasis::Net,
        );

        $this->assertSame(BudgetBasis::Gross, $persisted->scope->budgetBasis);
        $this->assertSame('USD', $persisted->scope->currency);
        $this->assertSame(BudgetBasis::Net, $override->scope->budgetBasis);
    }
}
