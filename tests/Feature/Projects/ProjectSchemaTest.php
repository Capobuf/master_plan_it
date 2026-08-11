<?php

namespace Tests\Feature\Projects;

use App\Domain\Projects\Enums\ProjectStage;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\PlanningYear;
use App\Models\Project;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ProjectSchemaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_project_schema_contains_only_the_approved_aggregate_fields(): void
    {
        $this->assertTrue(Schema::hasColumns('projects', [
            'id', 'tenant_id', 'cost_center_id', 'title', 'stage',
            'deferred_target_planning_year_id', 'lock_version',
            'deleted_by_user_id', 'deleted_by_at', 'deletion_reason',
            'created_at', 'updated_at', 'deleted_at',
        ]));
        foreach (['amount', 'budget_amount', 'forecast_amount', 'vendor_id', 'owner_id', 'project_manager_id'] as $forbidden) {
            $this->assertFalse(Schema::hasColumn('projects', $forbidden));
        }
    }

    public function test_database_closes_stage_and_enforces_tenant_owned_references(): void
    {
        $tenant = Tenant::factory()->create();
        $foreignTenant = Tenant::factory()->create();
        $center = CostCenter::factory()->for($tenant)->create();
        $foreignCenter = CostCenter::factory()->for($foreignTenant)->create();
        $foreignYear = PlanningYear::factory()->for($foreignTenant)->create();

        $this->expectQueryException(fn () => $this->insertProject((int) $tenant->getKey(), (int) $center->getKey(), 'unsupported', null));
        $this->expectQueryException(fn () => $this->insertProject((int) $tenant->getKey(), (int) $foreignCenter->getKey(), ProjectStage::Idea->value, null));
        $this->expectQueryException(fn () => $this->insertProject((int) $tenant->getKey(), (int) $center->getKey(), ProjectStage::Deferred->value, (int) $foreignYear->getKey()));
        $this->assertDatabaseMissing('projects', ['title' => 'Invalid project']);
    }

    public function test_expense_project_foreign_key_rejects_cross_tenant_links(): void
    {
        $tenant = Tenant::factory()->create();
        $foreignTenant = Tenant::factory()->create();
        $project = Project::factory()->for($tenant)->create();
        $expense = Expense::factory()->for($foreignTenant)->create();

        $this->expectQueryException(fn () => DB::table('expenses')->whereKey($expense->getKey())->update([
            'project_id' => $project->getKey(),
        ]));
        $this->assertDatabaseHas('expenses', ['id' => $expense->getKey(), 'project_id' => null]);
        $this->assertDatabaseHas('projects', ['id' => $project->getKey(), 'deleted_at' => null]);
    }

    private function insertProject(int $tenantId, int $centerId, string $stage, ?int $targetYearId): void
    {
        DB::table('projects')->insert([
            'tenant_id' => $tenantId,
            'cost_center_id' => $centerId,
            'title' => 'Invalid project',
            'stage' => $stage,
            'deferred_target_planning_year_id' => $targetYearId,
            'lock_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function expectQueryException(\Closure $operation): void
    {
        try {
            $operation();
            $this->fail('The database accepted an invalid Project row.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('projects', ['title' => 'Invalid project']);
        }
    }
}
