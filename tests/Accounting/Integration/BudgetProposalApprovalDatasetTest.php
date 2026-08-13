<?php

namespace Tests\Accounting\Integration;

use App\Domain\Budget\Queries\BudgetApprovalPreviewQuery;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class BudgetProposalApprovalDatasetTest extends TestCase
{
    use DatabaseTransactions;

    public function test_net_and_gross_previews_use_the_complete_dataset_and_count_plafond_once(): void
    {
        foreach (['net' => '3620.00', 'gross' => '4416.40'] as $basis => $official) {
            [$actor, $context, $year] = $this->fixture($basis);

            $preview = app(BudgetApprovalPreviewQuery::class)->execute($actor, $context, (int) $year->getKey());

            $this->assertSame('3620.00', $preview->proposal->total->net);
            $this->assertSame('796.40', $preview->proposal->total->vat);
            $this->assertSame('4416.40', $preview->proposal->total->gross);
            $this->assertSame($official, $preview->proposal->total->official);
            $this->assertSame(2, $preview->proposal->composition->contributorCount);
            $this->assertSame(
                ['expense-row:'.$preview->proposal->contributors[0]->rowId, 'plafond-allocation:'.$preview->proposal->contributors[1]->expenseId],
                array_map(static fn ($item): string => $item->sourceIdentity, $preview->proposal->contributors),
            );
            $this->assertContains('covered_by_plafond', array_map(static fn ($item): string => $item->reason, $preview->proposal->exclusions));
            $this->assertContains('alternative_planning', array_map(static fn ($item): string => $item->reason, $preview->proposal->exclusions));
            $this->assertContains('soft_deleted', array_map(static fn ($item): string => $item->reason, $preview->proposal->exclusions));
        }
    }

    /** @return array{User, TenantContext, PlanningYear} */
    private function fixture(string $basis): array
    {
        app(PermissionCatalogueSeeder::class)->run();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        $tenant = Tenant::factory()->create(['budget_basis' => $basis]);
        $actor = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($actor);
        $context = new TenantContext($tenant, $actor);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $center = CostCenter::factory()->for($tenant)->create(['name' => 'Operations']);
        $plafondCenter = CostCenter::factory()->for($tenant)->create(['name' => 'Plafond']);

        $ordinary = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(), 'title' => 'Licenze',
        ]);
        ExpenseRow::factory()->for($ordinary)->create([
            'tenant_id' => $tenant->getKey(), 'position' => 1, 'type' => ExpenseType::Estimate,
            'description' => 'Stima', 'entered_amount' => '100.00', 'net_amount' => '100.00',
            'vat_amount' => '22.00', 'gross_amount' => '122.00',
        ]);
        $quote = ExpenseRow::factory()->for($ordinary)->create([
            'tenant_id' => $tenant->getKey(), 'position' => 2, 'type' => ExpenseType::Quote,
            'description' => 'Preventivo corrente', 'entered_amount' => '120.00', 'net_amount' => '120.00',
            'vat_amount' => '26.40', 'gross_amount' => '146.40',
        ]);
        $ordinary->forceFill(['current_planning_row_id' => $quote->getKey()])->saveQuietly();

        $plafond = Expense::factory()->for($tenant)->plafond()->create([
            'planning_year_id' => $year->getKey(), 'cost_center_id' => $plafondCenter->getKey(), 'title' => 'Plafond annuale',
        ]);
        ExpenseRow::factory()->for($plafond)->allocationAdjustment($actor)->create([
            'tenant_id' => $tenant->getKey(), 'position' => 1, 'entered_amount' => '3500.00',
            'net_amount' => '3500.00', 'vat_amount' => '770.00', 'gross_amount' => '4270.00',
        ]);

        $covered = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(), 'title' => 'Coperta',
        ]);
        $coveredRow = ExpenseRow::factory()->for($covered)->create([
            'tenant_id' => $tenant->getKey(), 'position' => 1, 'type' => ExpenseType::Quote,
            'funded_plafond_expense_id' => $plafond->getKey(), 'entered_amount' => '4200.00',
            'net_amount' => '4200.00', 'vat_amount' => '924.00', 'gross_amount' => '5124.00',
        ]);
        $covered->forceFill(['current_planning_row_id' => $coveredRow->getKey()])->saveQuietly();

        $deleted = ExpenseRow::factory()->for($ordinary)->create([
            'tenant_id' => $tenant->getKey(), 'position' => 3, 'type' => ExpenseType::Estimate,
        ]);
        $deleted->delete();

        return [$actor, $context, $year];
    }
}
