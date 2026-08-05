<?php

namespace Tests\Browser\Shell;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Facebook\WebDriver\WebDriverKeys;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\DuskTestCase;

class OperationalShellSmokeTest extends DuskTestCase
{
    public function test_operational_shell_is_keyboard_accessible_responsive_and_reinitializes_preline(): void
    {
        app(PermissionCatalogueSeeder::class)->run();
        $tenant = Tenant::factory()->create([
            'name' => 'Browser tenant',
            'code' => 'BOP-'.str()->upper(str()->random(8)),
        ]);
        $actor = User::factory()->create(['tenant_id' => $tenant->getKey(), 'is_active' => true]);
        $this->assignDashboardAbility($actor, $tenant);

        try {
            $this->browse(function (Browser $browser) use ($actor, $tenant): void {
                foreach ([360, 768, 1280] as $viewport) {
                    $browser
                        ->resize($viewport, 900)
                        ->loginAs($actor)
                        ->visit('/operational')
                        ->waitFor('@operational-shell')
                        ->assertSee("Browser tenant ({$tenant->code})")
                        ->assertSee('Workspace help')
                        ->assertDontSee('Open interaction example')
                        ->assertDontSee('Submit')
                        ->assertScript('return document.documentElement.scrollWidth <= document.documentElement.clientWidth;', true)
                        ->assertScript('return Boolean(window.__operationalPrelineLifecycle?.initialized);', true)
                        ->click('@workspace-help-open');

                    $browser
                        ->waitFor('@workspace-help')
                        ->keys('@workspace-help-close', WebDriverKeys::ESCAPE)
                        ->waitUntil("document.querySelector('[dusk=workspace-help]')?.classList.contains('hidden')")
                        ->assertScript('return document.activeElement?.matches("[dusk=workspace-help-open]");', true);
                }
            });
        } finally {
            $actor->delete();
        }
    }

    private function assignDashboardAbility(User $actor, Tenant $tenant): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->getKey());
        $role = Role::query()->create([
            'name' => 'Browser operational '.str()->uuid(),
            'guard_name' => 'web',
            'tenant_id' => $tenant->getKey(),
        ]);
        $role->syncPermissions(['dashboard.view']);
        $actor->assignRole($role);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();
    }
}
