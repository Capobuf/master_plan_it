<?php

namespace Tests\Feature\Expenses;

use App\Console\Commands\TestResetGreenfield;
use App\Domain\Economics\Services\EconomicEngine;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Reporting\Queries\EconomicDatasetQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Expense;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class AnnualExpenseSeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_demo_seed_data_declares_canonical_net_gross_signed_actual_and_out_of_year_date_scenarios(): void
    {
        $contents = file_get_contents(base_path('database/seeders/DemoDataSeeder.php'));
        $rootSeeder = file_get_contents(base_path('database/seeders/DatabaseSeeder.php'));
        $this->assertStringContainsString("'budget_basis' => 'net'", $contents);
        $this->assertStringContainsString("'budget_basis' => 'gross'", $contents);
        $this->assertStringContainsString('-5.00', $contents);
        $this->assertStringContainsString('2026-02-10', $contents);
        $this->assertStringNotContainsString("'state' => 'open'", $contents);
        $this->assertStringContainsString('ExpenseKind::Plafond', $contents);
        $this->assertStringContainsString('ExpenseType::AllocationAdjustment', $contents);
        $this->assertStringContainsString('DEMO_SEED_BINDING', $rootSeeder);
        $this->assertStringContainsString('DemoDataSeeder::class', $rootSeeder);
    }

    public function test_demo_seed_builds_the_canonical_plafond_projection_with_cross_cost_center_coverage(): void
    {
        Storage::fake('attachments');
        app()->instance(TestResetGreenfield::DEMO_SEED_BINDING, true);
        $this->seed(DatabaseSeeder::class);

        $tenant = Tenant::query()->where('code', 'demo')->firstOrFail();
        $actor = User::query()->where('email', 'slice-023-admin@example.test')->firstOrFail();
        $planningYear = PlanningYear::query()
            ->where('tenant_id', $tenant->id)
            ->where('year_label', now('Europe/Rome')->year)
            ->firstOrFail();
        $plafond = Expense::query()
            ->with(['rows' => fn ($query) => $query->orderBy('position')])
            ->where('tenant_id', $tenant->id)
            ->where('title', 'DEMO — Plafond Infrastructure')
            ->firstOrFail();

        $this->assertSame(ExpenseKind::Plafond, $plafond->kind);
        $this->assertNull($plafond->current_planning_row_id);
        $this->assertCount(3, $plafond->rows);
        $this->assertSame(
            ['3000.00', '1000.00', '-500.00'],
            $plafond->rows->pluck('entered_amount')->all(),
        );
        $this->assertTrue($plafond->rows->every(
            fn ($row): bool => $row->type === ExpenseType::AllocationAdjustment
                && (int) $row->created_by_user_id === (int) $actor->id
                && $row->spend_date !== null
                && $row->vendor_id === null
                && $row->funded_plafond_expense_id === null
                && ! $row->is_extra,
        ));

        $coveredPlanning = Expense::query()
            ->with('rows')
            ->where('tenant_id', $tenant->id)
            ->where('title', 'DEMO — Pianificazione coperta Plafond')
            ->firstOrFail();
        $coveredActual = Expense::query()
            ->with(['rows' => fn ($query) => $query->orderBy('position')])
            ->where('tenant_id', $tenant->id)
            ->where('title', 'DEMO — Effettivo coperto Plafond')
            ->firstOrFail();

        $this->assertNotSame((int) $plafond->cost_center_id, (int) $coveredPlanning->cost_center_id);
        $this->assertNotSame((int) $plafond->cost_center_id, (int) $coveredActual->cost_center_id);
        $this->assertSame((int) $coveredPlanning->rows->firstOrFail()->id, (int) $coveredPlanning->current_planning_row_id);
        $this->assertSame((int) $plafond->id, (int) $coveredPlanning->rows->firstOrFail()->funded_plafond_expense_id);
        $this->assertFalse((bool) $coveredPlanning->rows->firstOrFail()->is_extra);
        $this->assertCount(2, $coveredActual->rows);
        $this->assertSame(['1500.00', '1000.00'], $coveredActual->rows->pluck('entered_amount')->all());
        $this->assertTrue($coveredActual->rows->every(
            fn ($row): bool => (int) $row->funded_plafond_expense_id === (int) $plafond->id
                && ! $row->is_extra,
        ));

        $dataset = app(EconomicDatasetQuery::class)->execute(
            $actor,
            new TenantContext($tenant, $actor),
            (int) $planningYear->id,
        );
        $projection = app(EconomicEngine::class)->project($dataset);
        $plafondProjection = $projection->plafonds[(int) $plafond->id];

        $this->assertSame('3500.00', $plafondProjection->allocation->official);
        $this->assertSame('4200.00', $plafondProjection->coveragePlanned->official);
        $this->assertSame('2500.00', $plafondProjection->consumed->official);
        $this->assertSame('1000.00', $plafondProjection->available->official);
        $this->assertCount(3, $plafondProjection->allocationLines);
        $this->assertCount(3, $plafondProjection->coveredLines);
        $this->assertTrue(collect($plafondProjection->coveredLines)->every(
            fn ($line): bool => $line->costCenterId !== $plafondProjection->costCenterId,
        ));
    }
}
