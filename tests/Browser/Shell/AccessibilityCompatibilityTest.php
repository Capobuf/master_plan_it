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
    public function test_shell_is_keyboard_operable_labelled_contrasted_and_responsive_at_every_approved_viewport(): void
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
                        ->visit('/admin')
                        ->waitFor('.fi-sidebar')
                        ->assertScript(
                            <<<'JS'
                                return document.documentElement.scrollWidth <= document.documentElement.clientWidth;
                            JS,
                            [true],
                        )
                        ->assertScript(
                            <<<'JS'
                                const sidebar = document.querySelector('.fi-sidebar');
                                const breadcrumb = Array.from(document.querySelectorAll('nav[aria-label]'))
                                    .find((node) => /tenant context/i.test(node.getAttribute('aria-label') || ''));
                                const sidebarStatuses = sidebar
                                    ? Array.from(sidebar.querySelectorAll('[role="status"]')).filter((node) => node.textContent.trim() === 'No tenant selected')
                                    : [];
                                const breadcrumbStatuses = breadcrumb
                                    ? Array.from(breadcrumb.querySelectorAll('[role="status"]')).filter((node) => node.textContent.trim() === 'No tenant selected')
                                    : [];

                                return sidebarStatuses.length === 1 && breadcrumbStatuses.length === 1;
                            JS,
                            [true],
                        )
                        ->assertScript(
                            <<<'JS'
                                const sidebar = document.querySelector('.fi-sidebar');
                                const status = sidebar?.querySelector('[role="status"]');
                                const parse = (value) => {
                                    const channels = value.match(/[\d.]+/g)?.slice(0, 3).map(Number);
                                    return channels?.length === 3 ? channels : null;
                                };
                                const luminance = (channels) => channels
                                    .map((channel) => channel / 255)
                                    .map((channel) => channel <= 0.04045 ? channel / 12.92 : Math.pow((channel + 0.055) / 1.055, 2.4))
                                    .reduce((sum, channel, index) => sum + channel * [0.2126, 0.7152, 0.0722][index], 0);
                                const foreground = status ? parse(getComputedStyle(status).color) : null;
                                let backgroundNode = status;
                                let background = null;
                                while (backgroundNode && !background) {
                                    const candidate = getComputedStyle(backgroundNode).backgroundColor;
                                    if (candidate !== 'rgba(0, 0, 0, 0)' && candidate !== 'transparent') {
                                        background = parse(candidate);
                                    }
                                    backgroundNode = backgroundNode.parentElement;
                                }
                                background ??= [255, 255, 255];
                                if (!foreground) return false;
                                const values = [luminance(foreground), luminance(background)].sort((a, b) => b - a);

                                return (values[0] + 0.05) / (values[1] + 0.05) >= 4.5;
                            JS,
                            [true],
                        );

                    if ($viewport < 1280) {
                        $browser->script("window.Alpine?.store('sidebar')?.close()");
                        $browser->waitUntil("! document.querySelector('#fi-main-sidebar')?.classList.contains('fi-sidebar-open')");
                        $browser->script("document.querySelector('.fi-topbar-open-sidebar-btn')?.focus()");
                        $browser
                            ->assertScript(
                                <<<'JS'
                                    const control = document.activeElement;
                                    if (!(control instanceof HTMLButtonElement)) return false;
                                    const style = getComputedStyle(control);

                                    return control.classList.contains('fi-topbar-open-sidebar-btn')
                                        && control.getAttribute('aria-controls') === 'fi-main-sidebar'
                                        && (control.getAttribute('aria-label') || '').trim().length > 0
                                        && (
                                            style.boxShadow !== 'none'
                                            || (style.outlineStyle !== 'none' && parseFloat(style.outlineWidth) > 0 && style.outlineColor !== 'rgba(0, 0, 0, 0)')
                                        );
                                JS,
                                [true],
                            )
                            ->keys('.fi-topbar-open-sidebar-btn', WebDriverKeys::ENTER)
                            ->waitUntil("document.querySelector('#fi-main-sidebar')?.classList.contains('fi-sidebar-open')");
                    }

                    $browser->script("document.querySelector('.fi-sidebar a[href$=\"/admin/tenants\"]')?.focus()");

                    $browser
                        ->assertScript(
                            <<<'JS'
                                const focused = document.activeElement;
                                if (!(focused instanceof HTMLAnchorElement) || !focused.href.endsWith('/admin/tenants')) return false;
                                const style = getComputedStyle(focused);

                                return style.boxShadow !== 'none'
                                    || (style.outlineStyle !== 'none' && parseFloat(style.outlineWidth) > 0 && style.outlineColor !== 'rgba(0, 0, 0, 0)');
                            JS,
                            [true],
                        )
                        ->keys('.fi-sidebar a[href$="/admin/tenants"]', WebDriverKeys::ENTER)
                        ->waitForLocation('/admin/tenants')
                        ->assertPathIs('/admin/tenants');
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
                ->visit('/admin/login')
                ->type('#form\\.email', 'unknown@example.invalid')
                ->type('#form\\.password', 'incorrect-password')
                ->click('button[type="submit"]')
                ->waitFor('[data-validation-error]')
                ->assertScript(
                    <<<'JS'
                        const error = document.querySelector('[data-validation-error]');
                        const wrapper = error?.closest('[data-field-wrapper]');
                        const input = wrapper?.querySelector('input');
                        const label = input?.id ? wrapper.querySelector(`label[for="${CSS.escape(input.id)}"]`) : null;

                        return Boolean(
                            error
                            && error.textContent.trim().length > 0
                            && input
                            && label
                            && label.textContent.trim().length > 0
                        );
                    JS,
                    [true],
                );
        });
    }
}
