<?php

namespace Tests\Browser\Shell;

use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class UnifiedApplicationVisualSmokeTest extends DuskTestCase
{
    public function test_unified_application_pages_forms_tables_and_modal_render_without_browser_errors(): void
    {
        app(PermissionCatalogueSeeder::class)->run();
        $administrator = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($administrator);
        $tenant = Tenant::factory()->create([
            'name' => 'Unified visual tenant',
            'code' => 'VIS-'.str()->upper(str()->random(8)),
        ]);
        $tenantUser = User::factory()->for($tenant)->create(['is_active' => true]);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $costCenter = CostCenter::factory()->for($tenant)->create(['name' => 'Unified operations']);
        $vendor = Vendor::factory()->for($tenant)->create(['name' => 'Unified supplier']);
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(),
            'cost_center_id' => $costCenter->getKey(),
            'title' => 'Unified interface verification',
        ]);
        ExpenseRow::factory()->for($expense)->create([
            'position' => 1,
            'vendor_id' => $vendor->getKey(),
        ]);

        try {
            $this->browse(function (Browser $browser) use ($administrator, $tenant): void {
                $browser
                    ->resize(390, 844)
                    ->visit('/login')
                    ->waitForText('Welcome back')
                    ->assertScript('return document.documentElement.scrollWidth <= document.documentElement.clientWidth;', true)
                    ->screenshot('unified-login-mobile')
                    ->resize(1280, 900)
                    ->loginAs($administrator)
                    ->visit('/platform/tenants')
                    ->waitForText('Unified visual tenant')
                    ->assertScript('return document.documentElement.scrollWidth <= document.documentElement.clientWidth;', true)
                    ->screenshot('unified-tenants-desktop');

                $tenantId = (int) $tenant->getKey();
                $browser->script(<<<JS
                    document.querySelector('button[data-enter-tenant="{$tenantId}"]')?.click();
                JS);

                $browser
                    ->waitForText('Dashboard')
                    ->assertPathIs('/operational')
                    ->assertSee('Unified visual tenant')
                    ->screenshot('unified-dashboard-desktop');

                foreach ([
                    '/operational/users' => 'Users',
                    '/operational/roles' => 'Roles',
                    '/operational/planning-years' => 'Planning years',
                    '/operational/vendors' => 'Vendors',
                    '/operational/cost-centers' => 'Cost centers',
                    '/operational/expenses' => 'Expense register',
                ] as $path => $heading) {
                    $browser
                        ->visit($path)
                        ->waitForText($heading)
                        ->assertScript('return document.documentElement.scrollWidth <= document.documentElement.clientWidth;', true);
                }

                $browser
                    ->visit('/operational/expenses')
                    ->waitForText('Unified interface verification')
                    ->click('tbody tr')
                    ->waitForText('Expense detail')
                    ->assertScript('return document.documentElement.scrollWidth <= document.documentElement.clientWidth;', true);

                foreach ([
                    '/operational/users/create' => 'Create user',
                    '/operational/roles/create' => 'Create role',
                    '/operational/vendors/create' => 'Create vendor',
                    '/operational/cost-centers/create' => 'Create cost center',
                ] as $path => $heading) {
                    $browser
                        ->visit($path)
                        ->waitForText($heading)
                        ->assertPresent('form')
                        ->assertScript('return document.documentElement.scrollWidth <= document.documentElement.clientWidth;', true);
                }

                $browser
                    ->visit('/operational/planning-years')
                    ->waitForText('Planning years')
                    ->script(<<<'JS'
                        Array.from(document.querySelectorAll('button'))
                            .find((button) => button.textContent?.includes('Add planning year'))
                            ?.click();
                    JS);

                $browser
                    ->assertPresent('form')
                    ->assertScript('return document.documentElement.scrollWidth <= document.documentElement.clientWidth;', true);

                $browser
                    ->visit('/operational/vendors')
                    ->waitForText('Unified supplier')
                    ->click('button[data-deactivate-vendor]');

                $browser
                    ->waitForText('Deactivate vendor')
                    ->assertVisible('[role="dialog"]')
                    ->screenshot('unified-confirmation-modal')
                    ->press('Cancel')
                    ->resize(390, 844)
                    ->visit('/profile')
                    ->waitForText('Profile')
                    ->assertPresent('form')
                    ->assertScript('return document.documentElement.scrollWidth <= document.documentElement.clientWidth;', true)
                    ->screenshot('unified-profile-mobile');

                $errors = $browser->driver->manage()->getLog('browser');
                $severeErrors = array_filter(
                    $errors,
                    static fn (array $entry): bool => ($entry['level'] ?? '') === 'SEVERE',
                );
                $this->assertSame([], array_values($severeErrors));
            });
        } finally {
            $administrator->delete();
            $tenantUser->delete();
        }
    }
}
