<?php

namespace Tests\Feature\Application;

use App\Models\CostCenter;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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
            ->assertViewIs('operational.planning-years.index')
            ->assertViewHas('planningYears', fn (array $years): bool => $years[0]['id'] === $year->getKey()
                && $years[0]['label'] === 2031)
            ->assertViewHas('abilities', fn (array $abilities): bool => $abilities['create']);

        $this->get(route('operational.cost-centers.index'))
            ->assertOk()
            ->assertViewIs('operational.cost-centers.index')
            ->assertViewHas('costCenters', fn (array $costCenters): bool => $costCenters[0]['id'] === $costCenter->getKey()
                && $costCenters[0]['name'] === 'Operations')
            ->assertViewHas('abilities', fn (array $abilities): bool => $abilities['viewRevisions']);

        $this->get(route('operational.vendors.index'))
            ->assertOk()
            ->assertViewIs('operational.vendors.index')
            ->assertViewHas('vendors', fn (array $vendors): bool => $vendors['data'][0]['id'] === $vendor->getKey()
                && $vendors['data'][0]['name'] === 'Acme')
            ->assertViewHas('abilities', fn (array $abilities): bool => $abilities['update']);
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
            ->assertViewIs('operational.cost-centers.create')
            ->assertViewHas('parents', fn (array $parents): bool => count($parents) === 1
                && $parents[0]['value'] === $parent->getKey()
                && $parents[0]['label'] === 'Allowed parent');

        $this->get(route('operational.vendors.create'))
            ->assertOk()
            ->assertViewIs('operational.vendors.create')
            ->assertViewHas('abilities', fn (array $abilities): bool => $abilities['create']);
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
            ->assertViewIs('operational.cost-centers.history')
            ->assertViewHas('costCenter', fn (array $shownCostCenter): bool => $shownCostCenter['id'] === $costCenter->getKey())
            ->assertViewHas('history');

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
            ->assertViewIs('operational.vendors.history')
            ->assertViewHas('vendor', fn (array $shownVendor): bool => $shownVendor['id'] === $vendor->getKey())
            ->assertViewHas('history');

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
            'name' => 'Application master data '.str()->uuid(),
            'guard_name' => 'web',
        ]);
        $role->syncPermissions($abilities);
        $actor->assignRole($role);
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');

        return [$tenant, $actor];
    }
}
