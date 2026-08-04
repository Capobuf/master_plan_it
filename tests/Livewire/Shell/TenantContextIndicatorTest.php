<?php

namespace Tests\Livewire\Shell;

use App\Domain\Tenancy\Data\TenantContext;
use App\Filament\Components\TenantContextIndicator;
use App\Models\Tenant;
use App\Models\User;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

class TenantContextIndicatorTest extends TestCase
{
    use DatabaseTransactions;

    public function test_selected_tenant_context_is_rendered_exactly_once_in_sidebar_and_breadcrumbs(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Selected tenant context',
            'code' => 'CTX-SHELL',
        ]);
        $actor = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $request = $this->requestWithContext(new TenantContext($tenant, $actor));
        $label = TenantContextIndicator::fromRequest($request)->label();

        $sidebar = $this->renderHook(PanelsRenderHook::SIDEBAR_NAV_START);
        $breadcrumbs = $this->renderHook(PanelsRenderHook::PAGE_HEADER_HEADING_BEFORE);

        $this->assertSame(1, substr_count($sidebar, e($label)));
        $this->assertSame(1, substr_count($breadcrumbs, e($label)));
        $this->assertSame(1, substr_count($sidebar, 'role="status"'));
        $this->assertSame(1, substr_count($breadcrumbs, 'role="status"'));
        $this->assertMatchesRegularExpression('/<nav\b[^>]*aria-label="[^"]*tenant context[^"]*"/i', $breadcrumbs);
    }

    public function test_global_context_is_distinct_and_rendered_exactly_once_on_each_shell_surface(): void
    {
        $request = Request::create('/admin');
        $this->app->instance('request', $request);
        $label = TenantContextIndicator::fromRequest($request)->label();

        $sidebar = $this->renderHook(PanelsRenderHook::SIDEBAR_NAV_START);
        $breadcrumbs = $this->renderHook(PanelsRenderHook::PAGE_HEADER_HEADING_BEFORE);

        $this->assertSame('No tenant selected', $label);
        $this->assertSame(1, substr_count($sidebar, e($label)));
        $this->assertSame(1, substr_count($breadcrumbs, e($label)));
        $this->assertStringContainsString('aria-live="polite"', $sidebar);
        $this->assertStringContainsString('aria-live="polite"', $breadcrumbs);
    }

    private function requestWithContext(TenantContext $context): Request
    {
        $request = Request::create('/admin');
        $request->attributes->set(TenantContext::class, $context);
        $this->app->instance('request', $request);

        return $request;
    }

    private function renderHook(string $hook): string
    {
        return FilamentView::renderHook($hook)->toHtml();
    }
}
