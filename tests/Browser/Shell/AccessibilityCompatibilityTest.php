<?php

namespace Tests\Browser\Shell;

use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use Facebook\WebDriver\WebDriverKeys;
use Laravel\Dusk\Browser;
use Tests\Browser\Support\BrowserMatrix;
use Tests\DuskTestCase;

class AccessibilityCompatibilityTest extends DuskTestCase
{
    public function test_shell_is_keyboard_operable_and_responsive_at_every_approved_viewport(): void
    {
        app(PermissionCatalogueSeeder::class)->run();
        $administrator = User::factory()->create([
            'tenant_id' => null,
            'is_active' => true,
        ]);
        app(PlatformAdministrator::class)->assign($administrator);

        try {
            $this->browse(function (Browser $browser) use ($administrator): void {
                $this->assertSame([360, 768, 1280], BrowserMatrix::viewports());

                foreach (BrowserMatrix::viewports() as $viewport) {
                    $browser
                        ->resize($viewport, 900)
                        ->loginAs($administrator)
                        ->visit('/platform/tenants')
                        ->waitForText('Tenants')
                        ->assertScript(
                            'return document.documentElement.scrollWidth <= document.documentElement.clientWidth;',
                            true,
                        );

                    if ($viewport < 1280) {
                        $browser
                            ->script('document.querySelector(\'button[aria-label="Open navigation"]\')?.focus()');
                        $browser
                            ->assertScript(
                                <<<'JS'
                                    (() => {
                                        const control = document.activeElement;
                                        if (!(control instanceof HTMLButtonElement)) return false;
                                        const style = getComputedStyle(control);

                                        return control.getAttribute('aria-label') === 'Open navigation'
                                            && (
                                                style.boxShadow !== 'none'
                                                || (style.outlineStyle !== 'none' && parseFloat(style.outlineWidth) > 0)
                                            );
                                    })()
                                JS,
                                true,
                            )
                            ->keys('button[aria-label="Open navigation"]', WebDriverKeys::ENTER)
                            ->waitFor('button[aria-label="Close navigation"]')
                            ->assertVisible('div.fixed.inset-0 nav[aria-label="Main navigation"]');
                    } else {
                        $browser->assertVisible('nav[aria-label="Main navigation"]');
                    }

                    $browser->assertPresent('a[href$="/platform/tenants"]');
                }
            });
        } finally {
            $administrator->delete();
        }
    }

    public function test_login_error_is_textually_identified_beside_a_programmatically_labelled_field(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser
                ->resize(360, 900)
                ->visit('/login')
                ->type('input[type="email"]', 'unknown@example.invalid')
                ->type('input[type="password"]', 'incorrect-password')
                ->click('button[type="submit"]')
                ->waitUntil(
                    "document.querySelector('input[type=email]')?.closest('label')?.querySelector('.text-red-600')?.textContent.trim().length > 0",
                )
                ->assertScript(
                    <<<'JS'
                        (() => {
                            const input = document.querySelector('input[type=email]');
                            const label = input?.closest('label');
                            const labelText = label?.querySelector('.mp-label');
                            const error = label?.querySelector('.text-red-600');

                            return Boolean(
                                input
                                && label
                                && label.contains(input)
                                && labelText?.textContent.trim().length
                                && error?.textContent.trim().length
                            );
                        })()
                    JS,
                    true,
                );
        });
    }
}
