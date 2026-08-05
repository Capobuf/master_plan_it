<?php

namespace Tests\Livewire\Shell;

use App\Domain\Tenancy\Actions\EnterTenantContext;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OperationalShellTest extends TestCase
{
    use DatabaseTransactions;

    public function test_operational_shell_uses_the_authenticated_tenant_context_and_explicit_ability(): void
    {
        app(PermissionCatalogueSeeder::class)->run();
        $tenant = Tenant::factory()->create(['name' => 'Operational tenant', 'code' => 'OPS-001']);
        $actor = User::factory()->create(['tenant_id' => $tenant->getKey(), 'is_active' => true]);
        $this->assignAbility($actor, $tenant, 'dashboard.view');

        $this->actingAs($actor)
            ->get('/operational')
            ->assertOk()
            ->assertSee('Operational tenant (OPS-001)')
            ->assertSee('Panoramica operativa')
            ->assertSee('Console Filament')
            ->assertSee('Workspace help')
            ->assertDontSee('Open interaction example')
            ->assertDontSee('Submit')
            ->assertSee('data-state="empty"', false);
    }

    public function test_operational_shell_fails_closed_without_context_or_permission(): void
    {
        app(PermissionCatalogueSeeder::class)->run();
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => $tenant->getKey(), 'is_active' => true]);

        $this->actingAs($actor)->get('/operational')->assertForbidden();

        $administrator = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        $this->actingAs($administrator)->get('/operational')->assertForbidden();

        $this->withSession([EnterTenantContext::SESSION_KEY => $tenant->getKey()])
            ->get('/operational')
            ->assertForbidden();
    }

    public function test_shared_operational_components_expose_the_state_and_modal_accessibility_contract(): void
    {
        $root = dirname(__DIR__, 3);
        $layout = (string) file_get_contents($root.'/resources/views/layouts/operational.blade.php');
        $script = (string) file_get_contents($root.'/resources/js/app.js');
        $styles = (string) file_get_contents($root.'/resources/css/app.css');
        $loading = (string) file_get_contents($root.'/resources/views/components/operational/loading-state.blade.php');
        $empty = (string) file_get_contents($root.'/resources/views/components/operational/empty-state.blade.php');
        $error = (string) file_get_contents($root.'/resources/views/components/operational/error-state.blade.php');

        $this->assertStringContainsString('aria-busy', $loading);
        $this->assertStringContainsString('data-state="empty"', $empty);
        $this->assertStringContainsString('PERMISSION_DENIED', $error);
        $this->assertStringContainsString('STALE_VERSION', $error);
        $this->assertStringContainsString('UNEXPECTED_ERROR', $error);
        $this->assertStringContainsString('data-hs-overlay="#operational-workspace-help"', $layout);
        $this->assertStringContainsString('aria-haspopup="dialog"', $layout);
        $this->assertStringNotContainsString('Open interaction example', $layout);
        $this->assertStringNotContainsString('requestLocked = true', $layout);
        $this->assertStringContainsString('requestLocked', $script);
        $this->assertStringContainsString('focusFirstInvalid', $script);
        $this->assertStringContainsString('opener.focus()', $script);
        $this->assertStringContainsString('[x-cloak]', $styles);
    }

    private function assignAbility(User $actor, Tenant $tenant, string $ability): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->getKey());
        $role = Role::query()->create([
            'name' => 'Operational shell '.str()->uuid(),
            'guard_name' => 'web',
            'tenant_id' => $tenant->getKey(),
        ]);
        $role->syncPermissions([$ability]);
        $actor->assignRole($role);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();
    }
}
