<?php

namespace Tests\Feature\Api\Projects;

use App\Domain\Projects\Enums\ProjectStage;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\PlanningYear;
use App\Models\Project;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class ProjectApiHttpTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_authorized_user_can_create_list_show_and_update_project(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $center = CostCenter::factory()->for($tenant)->create();
        $this->actingAs($user, 'web');
        $headers = $this->csrfHeaders();

        $created = $this->withHeaders($headers)->postJson('/api/v1/projects', [
            'title' => 'Rinnovo rete',
            'cost_center_id' => $center->getKey(),
            'stage' => 'approved',
            'deferred_target_planning_year_id' => null,
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Rinnovo rete')
            ->assertJsonPath('data.stage', 'approved')
            ->assertJsonMissingPath('data.deletion_reason');

        $id = (int) $created->json('data.id');
        $this->getJson('/api/v1/projects?per_page=1')
            ->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonStructure(['data', 'meta', 'links']);
        $this->getJson('/api/v1/projects/'.$id)
            ->assertOk()->assertJsonPath('data.cost_center.id', $center->getKey());

        $this->withHeaders($headers)->putJson('/api/v1/projects/'.$id, [
            'title' => 'Rinnovo rete aggiornato',
            'cost_center_id' => $center->getKey(),
            'stage' => 'proposed',
            'deferred_target_planning_year_id' => null,
            'lock_version' => $created->json('data.lock_version'),
        ])->assertOk()->assertJsonPath('data.title', 'Rinnovo rete aggiornato');
    }

    public function test_project_writes_reject_invalid_stage_deferred_without_target_and_stale_version(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $center = CostCenter::factory()->for($tenant)->create();
        $project = Project::factory()->for($tenant)->for($center)->create();
        $this->actingAs($user, 'web');
        $headers = $this->csrfHeaders();

        $this->withHeaders($headers)->postJson('/api/v1/projects', [
            'title' => 'Invalid', 'cost_center_id' => $center->getKey(), 'stage' => 'unknown',
        ])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->withHeaders($headers)->postJson('/api/v1/projects', [
            'title' => 'Deferred', 'cost_center_id' => $center->getKey(), 'stage' => 'deferred',
            'deferred_target_planning_year_id' => null,
        ])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->withHeaders($headers)->putJson('/api/v1/projects/'.$project->getKey(), [
            'title' => $project->title, 'cost_center_id' => $center->getKey(), 'stage' => 'approved',
            'deferred_target_planning_year_id' => null, 'lock_version' => 999,
        ])->assertConflict()->assertJsonPath('error.code', 'STALE_VERSION');
    }

    public function test_project_api_is_permission_and_tenant_scoped_without_disclosure(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $owned = Project::factory()->for($tenant)->create();
        $foreign = Project::factory()->for(Tenant::factory()->create())->create();
        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/projects/'.$owned->getKey())->assertOk();
        $this->getJson('/api/v1/projects/'.$foreign->getKey())
            ->assertNotFound()->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
        $this->withHeaders($this->csrfHeaders())->putJson('/api/v1/projects/'.$foreign->getKey(), [
            'title' => 'Tentativo esterno',
            'cost_center_id' => $owned->cost_center_id,
            'stage' => ProjectStage::Approved->value,
            'deferred_target_planning_year_id' => null,
            'lock_version' => 1,
        ])->assertNotFound()->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($tenant->getKey());
        try {
            Role::query()->where('tenant_id', $tenant->getKey())->where('name', 'Editor')
                ->firstOrFail()->revokePermissionTo('project.view');
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
        $this->getJson('/api/v1/projects/'.$owned->getKey())
            ->assertForbidden()->assertJsonPath('error.code', 'PERMISSION_DENIED');
    }

    public function test_project_deferred_target_must_belong_to_current_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $center = CostCenter::factory()->for($tenant)->create();
        $foreignYear = PlanningYear::factory()->for(Tenant::factory()->create())->create();
        $this->actingAs($user, 'web');

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/projects', [
            'title' => 'Foreign target', 'cost_center_id' => $center->getKey(),
            'stage' => ProjectStage::Deferred->value,
            'deferred_target_planning_year_id' => $foreignYear->getKey(),
        ])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_project_detail_does_not_disclose_revision_activity_without_history_ability(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $project = Project::factory()->for($tenant)->create();
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($tenant->getKey());
        try {
            Role::query()->where('tenant_id', $tenant->getKey())->where('name', 'Editor')
                ->firstOrFail()->revokePermissionTo('project.view-revisions');
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }

        $this->actingAs($user, 'web');
        $this->getJson('/api/v1/projects/'.$project->getKey())
            ->assertOk()
            ->assertJsonPath('data.revision_activity', []);
        $this->getJson('/api/v1/projects/'.$project->getKey().'/history')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PERMISSION_DENIED');
    }

    public function test_history_compare_restore_and_terminal_delete_follow_the_api_contract(): void
    {
        $tenant = Tenant::factory()->create(['deletion_reason_required' => false]);
        $user = $this->tenantUser($tenant);
        $center = CostCenter::factory()->for($tenant)->create();
        $this->actingAs($user, 'web');
        $headers = $this->csrfHeaders();

        $created = $this->withHeaders($headers)->postJson('/api/v1/projects', [
            'title' => 'Versione iniziale',
            'cost_center_id' => $center->getKey(),
            'stage' => ProjectStage::Idea->value,
            'deferred_target_planning_year_id' => null,
        ])->assertCreated();
        $projectId = (int) $created->json('data.id');

        $updated = $this->withHeaders($headers)->putJson('/api/v1/projects/'.$projectId, [
            'title' => 'Versione corrente',
            'cost_center_id' => $center->getKey(),
            'stage' => ProjectStage::Approved->value,
            'deferred_target_planning_year_id' => null,
            'lock_version' => 1,
        ])->assertOk();

        $history = $this->getJson('/api/v1/projects/'.$projectId.'/history?per_page=100')
            ->assertOk()->assertJsonCount(2, 'data');
        $createRevision = collect($history->json('data'))->firstWhere('operation', 'create');
        $revisionId = (int) $createRevision['id'];

        $this->getJson('/api/v1/projects/'.$projectId.'/history/'.$revisionId)
            ->assertOk()
            ->assertJsonPath('data.changes.0.label', 'Titolo')
            ->assertJsonPath('data.changes.0.revision_value', 'Versione iniziale')
            ->assertJsonPath('data.changes.0.current_value', 'Versione corrente')
            ->assertJsonMissingPath('data.snapshot')
            ->assertJsonMissingPath('data.current');

        $restored = $this->withHeaders($headers)->postJson('/api/v1/projects/'.$projectId.'/history/'.$revisionId.'/restore', [
            'lock_version' => $updated->json('data.lock_version'),
        ])->assertOk()->assertJsonPath('data.title', 'Versione iniziale');

        $this->withHeaders($headers)->postJson('/api/v1/projects/'.$projectId.'/history/'.$revisionId.'/restore', [
            'lock_version' => 2,
        ])->assertConflict()->assertJsonPath('error.code', 'STALE_VERSION');

        $this->withHeaders($headers)->deleteJson('/api/v1/projects/'.$projectId, [
            'lock_version' => $restored->json('data.lock_version'),
            'deletion_reason' => '  Chiusura progetto  ',
        ])->assertNoContent();
        $this->assertSoftDeleted('projects', ['id' => $projectId]);
        $this->getJson('/api/v1/projects/'.$projectId)->assertNotFound();
        $this->getJson('/api/v1/projects/'.$projectId.'/history')->assertNotFound();
        $this->withHeaders($headers)->postJson('/api/v1/projects/'.$projectId.'/history/'.$revisionId.'/restore', [
            'lock_version' => 4,
        ])->assertNotFound();
    }

    public function test_linked_expense_delete_conflict_is_stable_and_has_no_partial_side_effects(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $project = Project::factory()->for($tenant)->create();
        $expense = Expense::factory()->for($tenant)->create(['project_id' => $project->getKey()]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->csrfHeaders())->deleteJson('/api/v1/projects/'.$project->getKey(), [
            'lock_version' => 1,
            'deletion_reason' => 'Chiusura',
        ])->assertConflict()->assertJsonPath('error.code', 'PROJECT_HAS_LINKED_EXPENSES');

        $this->assertDatabaseHas('projects', ['id' => $project->getKey(), 'deleted_at' => null, 'lock_version' => 1]);
        $this->assertDatabaseHas('expenses', ['id' => $expense->getKey(), 'project_id' => $project->getKey(), 'deleted_at' => null]);
    }

    public function test_inactive_actor_and_tenant_are_denied_by_the_project_boundary(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $project = Project::factory()->for($tenant)->create();
        $this->actingAs($user, 'web');

        $user->update(['is_active' => false]);
        $this->getJson('/api/v1/projects/'.$project->getKey())
            ->assertForbidden()->assertJsonPath('error.code', 'ACCOUNT_INACTIVE');

        $user->update(['is_active' => true]);
        $tenant->update(['state' => 'inactive']);
        $this->getJson('/api/v1/projects/'.$project->getKey())
            ->assertForbidden()->assertJsonPath('error.code', 'TENANT_INACTIVE');
    }
}
