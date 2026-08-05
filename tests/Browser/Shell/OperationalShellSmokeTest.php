<?php

namespace Tests\Browser\Shell;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\DuskTestCase;

class OperationalShellSmokeTest extends DuskTestCase
{
    public function test_operational_react_shell_is_responsive_and_keyboard_accessible(): void
    {
        app(PermissionCatalogueSeeder::class)->run();
        $tenant = Tenant::factory()->create([
            'name' => 'Browser tenant',
            'code' => 'BOP-'.str()->upper(str()->random(8)),
        ]);
        $actor = User::factory()->create(['tenant_id' => $tenant->getKey(), 'is_active' => true]);
        $this->assignDashboardAbility($actor, $tenant);

        try {
            $this->browse(function (Browser $browser) use ($actor): void {
                foreach ([360, 768, 1280] as $viewport) {
                    $browser
                        ->resize($viewport, 900)
                        ->loginAs($actor)
                        ->visit('/operational')
                        ->waitForText('Dashboard')
                        ->assertSee('Browser tenant')
                        ->assertScript('return document.documentElement.scrollWidth <= document.documentElement.clientWidth;', true);

                    if ($viewport < 1280) {
                        $browser
                            ->keys('button[aria-label="Open navigation"]', '{enter}')
                            ->waitFor('button[aria-label="Close navigation"]')
                            ->assertVisible('button[aria-label="Close navigation"]')
                            ->click('button[aria-label="Close navigation"]');
                    } else {
                        $browser->assertVisible('nav[aria-label="Main navigation"]');
                    }
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
