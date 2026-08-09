<?php

namespace Tests\Feature\MasterData;

use App\Domain\Tenancy\Data\TenantContext;
use App\Models\CostCenter;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Policies\CostCenterPolicy;
use App\Policies\PlanningYearPolicy;
use App\Policies\VendorPolicy;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MasterDataAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);
    }

    protected function tearDown(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_each_master_data_operation_allows_only_its_exact_same_tenant_ability(): void
    {
        $tenant = Tenant::factory()->create();
        $context = new TenantContext($tenant, $actor = User::factory()->create(['tenant_id' => $tenant->getKey()]));
        $planningYear = PlanningYear::factory()->for($tenant)->create();
        $costCenter = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();

        foreach ([
            [PlanningYearPolicy::class, 'viewAny', 'planning-year.view', null],
            [PlanningYearPolicy::class, 'view', 'planning-year.view', $planningYear],
            [PlanningYearPolicy::class, 'create', 'planning-year.create', null],
            [PlanningYearPolicy::class, 'update', 'planning-year.update', $planningYear],
            [PlanningYearPolicy::class, 'deactivate', 'planning-year.deactivate', $planningYear],
            [PlanningYearPolicy::class, 'reactivate', 'planning-year.reactivate', $planningYear],
            [CostCenterPolicy::class, 'viewAny', 'cost-center.view', null],
            [CostCenterPolicy::class, 'view', 'cost-center.view', $costCenter],
            [CostCenterPolicy::class, 'create', 'cost-center.create', null],
            [CostCenterPolicy::class, 'update', 'cost-center.update', $costCenter],
            [CostCenterPolicy::class, 'delete', 'cost-center.delete', $costCenter],
            [CostCenterPolicy::class, 'deactivate', 'cost-center.deactivate', $costCenter],
            [CostCenterPolicy::class, 'reactivate', 'cost-center.reactivate', $costCenter],
            [CostCenterPolicy::class, 'viewRevisions', 'cost-center.view-revisions', $costCenter],
            [CostCenterPolicy::class, 'restoreRevision', 'cost-center.restore-revision', $costCenter],
            [VendorPolicy::class, 'viewAny', 'vendor.view', null],
            [VendorPolicy::class, 'view', 'vendor.view', $vendor],
            [VendorPolicy::class, 'create', 'vendor.create', null],
            [VendorPolicy::class, 'update', 'vendor.update', $vendor],
            [VendorPolicy::class, 'delete', 'vendor.delete', $vendor],
            [VendorPolicy::class, 'deactivate', 'vendor.deactivate', $vendor],
            [VendorPolicy::class, 'reactivate', 'vendor.reactivate', $vendor],
            [VendorPolicy::class, 'viewRevisions', 'vendor.view-revisions', $vendor],
            [VendorPolicy::class, 'restoreRevision', 'vendor.restore-revision', $vendor],
        ] as [$policyClass, $method, $ability, $resource]) {
            $this->grant($actor, $tenant, $ability, 'Exact '.$ability);
            $response = $resource === null
                ? $this->policy($policyClass, $context)->{$method}($actor)
                : $this->policy($policyClass, $context)->{$method}($actor, $resource);

            $this->assertTrue($response->allowed(), "{$policyClass}::{$method} did not allow [{$ability}].");

            $this->revoke($actor, $tenant, $ability);
            $response = $resource === null
                ? $this->policy($policyClass, $context)->{$method}($actor)
                : $this->policy($policyClass, $context)->{$method}($actor, $resource);

            $this->assertDenied($response, 'PERMISSION_DENIED');
        }
    }

    public function test_missing_inactive_and_other_tenant_actors_are_denied_without_record_disclosure(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $vendor = Vendor::factory()->for($tenant)->create();
        $foreignVendor = Vendor::factory()->for($otherTenant)->create();
        $context = new TenantContext($tenant, $actor);
        $policy = $this->policy(VendorPolicy::class, $context);

        $this->assertDenied($policy->view($actor, $vendor), 'PERMISSION_DENIED');

        $this->grant($actor, $tenant, 'vendor.view', 'Vendor reader');
        User::query()->whereKey($actor->getKey())->update(['is_active' => false]);
        $this->assertDenied($policy->view($actor, $vendor), 'PERMISSION_DENIED');

        User::query()->whereKey($actor->getKey())->update(['is_active' => true]);
        $this->assertNotFound($policy->view($actor, $foreignVendor));

        $otherActor = User::factory()->create(['tenant_id' => $otherTenant->getKey()]);
        $this->grant($otherActor, $otherTenant, 'vendor.view', 'Other tenant vendor reader');
        $this->assertDenied($policy->view($otherActor, $vendor), 'PERMISSION_DENIED');
    }

    public function test_record_policy_rejects_a_tenant_actor_tampered_to_a_persisted_administrator_primary_key(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $vendor = Vendor::factory()->for($tenant)->create();
        $administrator = User::factory()->create(['tenant_id' => null]);
        app(PlatformAdministrator::class)->assign($administrator);
        $actor->forceFill([$actor->getKeyName() => $administrator->getKey()]);

        $response = $this->policy(VendorPolicy::class, new TenantContext($tenant, $actor))->view($actor, $vendor);

        $this->assertDenied($response, 'PERMISSION_DENIED');
    }

    public function test_seeded_editor_can_close_budgets_but_cannot_manage_planning_year_lifecycle(): void
    {
        $tenant = Tenant::factory()->create();
        $editor = Role::query()->where('name', 'Editor')->whereNull('tenant_id')->firstOrFail();
        $editorAbilities = $editor->permissions()->pluck('name')->all();

        $this->assertContains('planning-year.view', $editorAbilities);
        $this->assertContains('planning-year.update', $editorAbilities);
        $this->assertSame([], array_values(array_intersect([
            'planning-year.create',
            'planning-year.deactivate',
            'planning-year.reactivate',
        ], $editorAbilities)));

        $actor = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $planningYear = PlanningYear::factory()->for($tenant)->create();
        $this->grant($actor, $tenant, 'planning-year.create', 'Calendar Lifecycle');
        $this->grant($actor, $tenant, 'planning-year.update', 'Calendar Lifecycle');
        $this->grant($actor, $tenant, 'planning-year.deactivate', 'Calendar Lifecycle');
        $this->grant($actor, $tenant, 'planning-year.reactivate', 'Calendar Lifecycle');
        $policy = $this->policy(PlanningYearPolicy::class, new TenantContext($tenant, $actor));

        $this->assertTrue($policy->create($actor)->allowed());
        $this->assertTrue($policy->update($actor, $planningYear)->allowed());
        $this->assertTrue($policy->deactivate($actor, $planningYear)->allowed());
        $this->assertTrue($policy->reactivate($actor, $planningYear)->allowed());
    }

    public function test_planning_year_has_no_delete_or_revision_policy_surface(): void
    {
        $reflection = new \ReflectionClass(PlanningYearPolicy::class);

        foreach (['delete', 'viewRevisions', 'restoreRevision'] as $method) {
            $this->assertFalse($reflection->hasMethod($method), "Planning-year policy must not expose [{$method}].");
        }
    }

    /** @param class-string $policyClass */
    private function policy(string $policyClass, TenantContext $context): object
    {
        return new $policyClass(
            $context,
            app(PermissionRegistrar::class),
            app(PlatformAdministrator::class),
        );
    }

    private function grant(User $actor, Tenant $tenant, string $ability, string $roleName): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->getKey());
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');

        $role = Role::query()->firstOrCreate([
            'tenant_id' => $tenant->getKey(),
            'name' => $roleName,
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($ability);
        $actor->assignRole($role);
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');
    }

    private function revoke(User $actor, Tenant $tenant, string $ability): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->getKey());
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');

        $role = Role::query()->where('tenant_id', $tenant->getKey())->where('name', 'Exact '.$ability)->firstOrFail();
        $role->revokePermissionTo($ability);
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');
    }

    private function assertDenied(Response $response, string $message): void
    {
        $this->assertTrue($response->denied());
        $this->assertSame($message, $response->message());
    }

    private function assertNotFound(Response $response): void
    {
        $this->assertTrue($response->denied());
        $this->assertSame(404, $response->status());
        $this->assertSame('RESOURCE_NOT_FOUND', $response->message());
    }
}
