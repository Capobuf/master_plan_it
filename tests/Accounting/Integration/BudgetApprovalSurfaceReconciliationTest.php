<?php

namespace Tests\Accounting\Integration;

use App\Domain\Budget\Queries\AnnualBudgetQuery;
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

final class BudgetApprovalSurfaceReconciliationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_preparation_overview_and_approval_preview_share_exact_composition(): void
    {
        app(PermissionCatalogueSeeder::class)->run();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        $tenant = Tenant::factory()->create(['budget_basis' => 'gross']);
        $actor = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($actor);
        $context = new TenantContext($tenant, $actor);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $center = CostCenter::factory()->for($tenant)->create();
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(),
        ]);
        $row = ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(), 'type' => ExpenseType::Quote,
            'net_amount' => '120.00', 'vat_amount' => '26.40', 'gross_amount' => '146.40',
        ]);
        $expense->forceFill(['current_planning_row_id' => $row->getKey()])->saveQuietly();

        $overview = app(AnnualBudgetQuery::class)->execute($actor, $context, (int) $year->getKey());
        $preview = app(BudgetApprovalPreviewQuery::class)->execute($actor, $context, (int) $year->getKey());

        $this->assertSame($overview['proposal']['composition']['fingerprint'], $preview->proposal->composition->fingerprint);
        $this->assertSame($overview['proposal']['total'], [
            'net' => $preview->proposal->total->net,
            'vat' => $preview->proposal->total->vat,
            'gross' => $preview->proposal->total->gross,
            'official' => $preview->proposal->total->official,
        ]);
        $this->assertSame('146.40', $overview['proposal']['total']['official']);
        $this->assertSame([
            'planning_year', 'currency', 'basis', 'economic_base', 'proposal', 'approved_snapshot',
            'informative_evaluations', 'actuals', 'actions',
        ], array_keys($overview));
        foreach (['summary', 'expenses', 'mode', 'budget', 'totals', 'plafonds'] as $legacyKey) {
            $this->assertArrayNotHasKey($legacyKey, $overview);
        }
    }
}
