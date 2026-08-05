<?php

namespace Tests\Feature\Inertia;

use App\Models\CostCenter;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MasterDataPagesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionCatalogueSeeder::class)->run();
    }

    protected function tearDown(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_planning_year_cost_center_and_vendor_pages_render_tenant_scoped_props(): void
    {
        [$tenant, $actor] = $this->actorWithAbilities([
            'planning-year.view',
            'planning-year.create',
            'planning-year.deactivate',
            'planning-year.reactivate',
            'cost-center.view',
            'cost-center.create',
            'cost-center.update',
            'cost-center.delete',
            'cost-center.deactivate',
            'cost-center.reactivate',
            'cost-center.view-revisions',
            'cost-center.restore-revision',
            'vendor.view',
            'vendor.create',
            'vendor.update',
            'vendor.delete',
            'vendor.deactivate',
            'vendor.reactivate',
            'vendor.view-revisions',
            'vendor.restore-revision',
        ]);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2031]);
        $costCenter = CostCenter::factory()->for($tenant)->create(['name' => 'Operations']);
        $vendor = Vendor::factory()->for($tenant)->create(['name' => 'Acme']);

        $this->actingAs($actor)
            ->get(route('operational.planning-years.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Operational/PlanningYears/Index')
                ->where('planningYears.0.id', $year->getKey())
                ->where('planningYears.0.label', 2031)
                ->where('abilities.create', true));

        $this->get(route('operational.cost-centers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Operational/CostCenters/Index')
                ->where('costCenters.0.id', $costCenter->getKey())
                ->where('costCenters.0.name', 'Operations')
                ->where('abilities.viewRevisions', true));

        $this->get(route('operational.vendors.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Operational/Vendors/Index')
                ->where('vendors.data.0.id', $vendor->getKey())
                ->where('vendors.data.0.name', 'Acme')
                ->where('abilities.update', true));
    }

    public function test_create_pages_offer_only_same_tenant_relationship_options(): void
    {
        [$tenant, $actor] = $this->actorWithAbilities([
            'cost-center.view',
            'cost-center.create',
            'vendor.create',
        ]);
        $parent = CostCenter::factory()->for($tenant)->create(['name' => 'Allowed parent']);
        CostCenter::factory()->for(Tenant::factory())->create(['name' => 'Foreign parent']);

        $this->actingAs($actor)
            ->get(route('operational.cost-centers.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Operational/CostCenters/Create')
                ->has('parents', 1)
                ->where('parents.0.value', $parent->getKey())
                ->where('parents.0.label', 'Allowed parent'));

        $this->get(route('operational.vendors.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Operational/Vendors/Create')
                ->where('abilities.create', true));
    }

    public function test_planning_year_submit_routes_delegate_create_and_lifecycle_actions(): void
    {
        [$tenant, $actor] = $this->actorWithAbilities([
            'planning-year.view',
            'planning-year.create',
            'planning-year.deactivate',
            'planning-year.reactivate',
        ]);

        $this->actingAs($actor)
            ->post(route('operational.planning-years.store'), ['year_label' => 2033])
            ->assertRedirect(route('operational.planning-years.index'));

        $year = PlanningYear::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('year_label', 2033)
            ->firstOrFail();

        $this->post(route('operational.planning-years.deactivate', $year->getKey()), [
            'lock_version' => $year->lock_version,
        ])->assertRedirect(route('operational.planning-years.index'));

        $year->refresh();
        $this->assertFalse($year->active);

        $this->post(route('operational.planning-years.reactivate', $year->getKey()), [
            'lock_version' => $year->lock_version,
        ])->assertRedirect(route('operational.planning-years.index'));

        $this->assertTrue($year->fresh()?->active);
    }

    public function test_cost_center_submit_routes_delegate_hierarchy_lifecycle_and_deletion_actions(): void
    {
        [$tenant, $actor] = $this->actorWithAbilities([
            'cost-center.view',
            'cost-center.create',
            'cost-center.update',
            'cost-center.delete',
            'cost-center.deactivate',
            'cost-center.reactivate',
            'cost-center.view-revisions',
        ]);
        $parent = CostCenter::factory()->for($tenant)->create(['name' => 'Parent']);

        $this->actingAs($actor)
            ->post(route('operational.cost-centers.store'), [
                'name' => 'Created cost center',
                'parent_id' => $parent->getKey(),
            ])
            ->assertRedirect();

        $costCenter = CostCenter::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('name', 'Created cost center')
            ->firstOrFail();

        $this->put(route('operational.cost-centers.update', $costCenter->getKey()), [
            'name' => 'Updated cost center',
            'parent_id' => null,
            'lock_version' => $costCenter->lock_version,
        ])->assertRedirect(route('operational.cost-centers.edit', $costCenter->getKey()));

        $costCenter->refresh();
        $this->get(route('operational.cost-centers.history', $costCenter->getKey()))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Operational/CostCenters/History')
                ->where('costCenter.id', $costCenter->getKey())
                ->has('history'));

        $this->post(route('operational.cost-centers.deactivate', $costCenter->getKey()), [
            'lock_version' => $costCenter->lock_version,
        ])->assertRedirect(route('operational.cost-centers.index'));

        $costCenter->refresh();
        $this->post(route('operational.cost-centers.reactivate', $costCenter->getKey()), [
            'lock_version' => $costCenter->lock_version,
        ])->assertRedirect(route('operational.cost-centers.index'));

        $costCenter->refresh();
        $this->delete(route('operational.cost-centers.destroy', $costCenter->getKey()), [
            'lock_version' => $costCenter->lock_version,
        ])->assertRedirect(route('operational.cost-centers.index'));

        $this->assertSoftDeleted('cost_centers', ['id' => $costCenter->getKey()]);
    }

    public function test_vendor_submit_routes_delegate_contact_lifecycle_and_deletion_actions(): void
    {
        [$tenant, $actor] = $this->actorWithAbilities([
            'vendor.view',
            'vendor.create',
            'vendor.update',
            'vendor.delete',
            'vendor.deactivate',
            'vendor.reactivate',
            'vendor.view-revisions',
        ]);

        $this->actingAs($actor)
            ->post(route('operational.vendors.store'), [
                'name' => 'Created vendor',
                'vat_number' => 'IT12345678901',
                'email' => 'vendor@example.test',
                'phone' => '+39 001 002',
                'address' => 'Via Roma 1',
            ])
            ->assertRedirect();

        $vendor = Vendor::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('name', 'Created vendor')
            ->firstOrFail();

        $this->put(route('operational.vendors.update', $vendor->getKey()), [
            'name' => 'Updated vendor',
            'vat_number' => 'IT12345678901',
            'email' => 'updated-vendor@example.test',
            'phone' => '+39 003 004',
            'address' => 'Via Milano 2',
            'lock_version' => $vendor->lock_version,
        ])->assertRedirect(route('operational.vendors.edit', $vendor->getKey()));

        $vendor->refresh();
        $this->get(route('operational.vendors.history', $vendor->getKey()))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Operational/Vendors/History')
                ->where('vendor.id', $vendor->getKey())
                ->has('history'));

        $this->post(route('operational.vendors.deactivate', $vendor->getKey()), [
            'lock_version' => $vendor->lock_version,
        ])->assertRedirect(route('operational.vendors.index'));

        $vendor->refresh();
        $this->post(route('operational.vendors.reactivate', $vendor->getKey()), [
            'lock_version' => $vendor->lock_version,
        ])->assertRedirect(route('operational.vendors.index'));

        $vendor->refresh();
        $this->delete(route('operational.vendors.destroy', $vendor->getKey()), [
            'lock_version' => $vendor->lock_version,
        ])->assertRedirect(route('operational.vendors.index'));

        $this->assertSoftDeleted('vendors', ['id' => $vendor->getKey()]);
    }

    public function test_direct_routes_deny_missing_permissions_and_hide_foreign_identifiers(): void
    {
        [$tenant, $actor] = $this->actorWithAbilities(['cost-center.view', 'cost-center.update']);
        $foreign = CostCenter::factory()->for(Tenant::factory())->create();

        $this->actingAs($actor)
            ->get(route('operational.cost-centers.edit', $foreign))
            ->assertNotFound();

        $unauthorized = User::factory()->for($tenant)->create(['is_active' => true]);
        $this->actingAs($unauthorized)
            ->get(route('operational.vendors.index'))
            ->assertForbidden();
    }

    /**
     * @param  list<string>  $abilities
     * @return array{Tenant, User}
     */
    private function actorWithAbilities(array $abilities): array
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->for($tenant)->create(['is_active' => true]);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->getKey());
        $role = Role::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Inertia master data '.str()->uuid(),
            'guard_name' => 'web',
        ]);
        $role->syncPermissions($abilities);
        $actor->assignRole($role);
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');

        return [$tenant, $actor];
    }
}
