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
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class BudgetProposalSurfaceQueryCountTest extends TestCase
{
    use DatabaseTransactions;

    private const PREVIEW_QUERY_BUDGET = 19;

    private const OVERVIEW_QUERY_BUDGET = 20;

    public function test_query_counts_are_fixed_as_contributors_exclusions_and_dimensions_grow(): void
    {
        [$actor, $context, $small] = $this->fixture(1);
        [, , $large] = $this->fixture(24, $context->tenant, $actor);

        app(BudgetApprovalPreviewQuery::class)->execute($actor, $context, (int) $small->getKey());
        app(AnnualBudgetQuery::class)->execute($actor, $context, (int) $small->getKey());

        $smallPreview = $this->queries(fn () => app(BudgetApprovalPreviewQuery::class)->execute($actor, $context, (int) $small->getKey()));
        $largePreview = $this->queries(fn () => app(BudgetApprovalPreviewQuery::class)->execute($actor, $context, (int) $large->getKey()));
        $smallOverview = $this->queries(fn () => app(AnnualBudgetQuery::class)->execute($actor, $context, (int) $small->getKey()));
        $largeOverview = $this->queries(fn () => app(AnnualBudgetQuery::class)->execute($actor, $context, (int) $large->getKey()));

        $this->assertSame($smallPreview, $largePreview);
        $this->assertSame(self::PREVIEW_QUERY_BUDGET, $largePreview);
        $this->assertSame($smallOverview, $largeOverview);
        $this->assertSame(self::OVERVIEW_QUERY_BUDGET, $largeOverview);
    }

    /** @return array{User, TenantContext, PlanningYear} */
    private function fixture(int $count, ?Tenant $tenant = null, ?User $actor = null): array
    {
        if ($tenant === null || $actor === null) {
            app(PermissionCatalogueSeeder::class)->run();
            app(PermissionRegistrar::class)->setPermissionsTeamId(null);
            $tenant = Tenant::factory()->create();
            $actor = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
            app(PlatformAdministrator::class)->assign($actor);
        }
        $context = new TenantContext($tenant, $actor);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026 + $count]);
        $center = CostCenter::factory()->for($tenant)->create();
        for ($index = 1; $index <= $count; $index++) {
            $expense = Expense::factory()->for($tenant)->create([
                'planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(),
            ]);
            ExpenseRow::factory()->for($expense)->create([
                'tenant_id' => $tenant->getKey(), 'position' => 1, 'type' => ExpenseType::Estimate,
            ]);
            $current = ExpenseRow::factory()->for($expense)->create([
                'tenant_id' => $tenant->getKey(), 'position' => 2, 'type' => ExpenseType::Quote,
            ]);
            $expense->forceFill(['current_planning_row_id' => $current->getKey()])->saveQuietly();
        }

        return [$actor, $context, $year];
    }

    private function queries(callable $callback): int
    {
        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();
        $callback();
        $count = count(DB::connection()->getQueryLog());
        DB::connection()->disableQueryLog();

        return $count;
    }
}
