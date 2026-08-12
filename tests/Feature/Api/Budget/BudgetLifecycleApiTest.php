<?php

namespace Tests\Feature\Api\Budget;

use App\Models\PlanningYear;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class BudgetLifecycleApiTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_close_endpoint_rejects_stale_version_without_side_effects(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/budget/'.$year->getKey().'/close', [
            'lock_version' => 99,
        ])->assertConflict()->assertJsonPath('error.code', 'STALE_VERSION');
        $this->assertDatabaseHas('planning_years', [
            'id' => $year->getKey(), 'budget_state' => 'preparation', 'lock_version' => 1,
        ]);
    }

    public function test_close_endpoint_does_not_disclose_foreign_planning_year(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $foreignYear = PlanningYear::factory()->for(Tenant::factory()->create())->create();
        $this->actingAs($user, 'web');

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/budget/'.$foreignYear->getKey().'/close', [
            'lock_version' => 1,
        ])->assertNotFound()->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_close_endpoint_requires_planning_year_update_and_not_expense_update(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create();
        $editor = Role::query()->where('tenant_id', $tenant->getKey())->where('name', 'Editor')->firstOrFail();
        $editor->revokePermissionTo('planning-year.update');
        $this->actingAs($user, 'web');

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/budget/'.$year->getKey().'/close', [
            'lock_version' => 1,
        ])->assertForbidden();

        $editor->givePermissionTo('planning-year.update');
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');
        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/budget/'.$year->getKey().'/close', [
            'lock_version' => 1,
        ])->assertOk()->assertJsonPath('data.budget.state', 'closed');
    }
}
