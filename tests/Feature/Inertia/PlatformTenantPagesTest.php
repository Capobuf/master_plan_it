<?php

namespace Tests\Feature\Inertia;

use App\Domain\Tenancy\Actions\EnterTenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PlatformTenantPagesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionCatalogueSeeder::class)->run();
    }

    public function test_tenant_index_and_edit_expose_domain_records_and_explicit_abilities(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create([
            'name' => 'Inertia tenant',
            'code' => 'INERTIA-01',
        ]);

        $this->actingAs($administrator)
            ->get(route('platform.tenants.index'))
            ->assertOk()
            ->assertViewIs('platform.tenants.index')
            ->assertViewHas('tenants', fn (array $tenants): bool => collect($tenants)->contains(
                fn (array $item): bool => $item['id'] === $tenant->getKey()
                    && $item['name'] === 'Inertia tenant',
            ))
            ->assertViewHas('abilities', fn (array $abilities): bool => $abilities['create']
                && $abilities['update'] && $abilities['deactivate'] && $abilities['reactivate'] && $abilities['enter']);

        $this->get(route('platform.tenants.edit', $tenant))
            ->assertOk()
            ->assertViewIs('platform.tenants.edit')
            ->assertViewHas('record', fn (array $record): bool => $record['id'] === $tenant->getKey()
                && $record['code'] === 'INERTIA-01');
    }

    public function test_create_and_enter_routes_delegate_to_tenant_actions(): void
    {
        $administrator = $this->administrator();

        $response = $this->actingAs($administrator)->post(route('platform.tenants.store'), [
            'name' => 'Created through Inertia',
            'code' => 'INERTIA-NEW',
            'currency_code' => 'EUR',
            'language_code' => 'it',
            'timezone' => 'Europe/Rome',
            'default_vat_rate' => '22.000000',
        ]);

        $tenant = Tenant::query()->where('code', 'INERTIA-NEW')->firstOrFail();
        $response->assertRedirect(route('platform.tenants.edit', $tenant));

        $this->post(route('platform.tenants.enter', $tenant))
            ->assertRedirect(route('home'))
            ->assertSessionHas(EnterTenantContext::SESSION_KEY, $tenant->getKey());
    }

    public function test_update_and_lifecycle_routes_delegate_lock_versions_and_confirmation(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create(['code' => 'LIFECYCLE-01']);

        $this->actingAs($administrator)
            ->put(route('platform.tenants.update', $tenant), [
                'name' => 'Updated tenant',
                'code' => 'LIFECYCLE-01',
                'currency_code' => 'EUR',
                'language_code' => 'it',
                'timezone' => 'Europe/Rome',
                'default_vat_rate' => '20.000000',
                'lock_version' => $tenant->lock_version,
            ])
            ->assertRedirect(route('platform.tenants.edit', $tenant));

        $tenant->refresh();
        $this->assertSame('Updated tenant', $tenant->name);

        $this->post(route('platform.tenants.deactivate', $tenant), [
            'confirmation_code' => $tenant->code,
            'lock_version' => $tenant->lock_version,
        ])->assertRedirect(route('platform.tenants.index'));

        $tenant->refresh();
        $this->assertSame(TenantState::Inactive, $tenant->state);

        $this->post(route('platform.tenants.reactivate', $tenant), [
            'lock_version' => $tenant->lock_version,
        ])->assertRedirect(route('platform.tenants.index'));

        $this->assertSame(TenantState::Active, $tenant->fresh()?->state);
    }

    public function test_platform_routes_fail_closed_for_a_tenant_user(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'is_active' => true,
        ]);

        $this->actingAs($actor)
            ->get(route('platform.tenants.index'))
            ->assertForbidden();
    }

    private function administrator(): User
    {
        $administrator = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($administrator);

        return $administrator;
    }
}
