<?php

namespace App\Providers\Filament;

use App\Filament\Components\TenantContextIndicator;
use App\Filament\Resources\Tenants\TenantResource;
use App\Http\Controllers\Auth\LogoutController as ApplicationLogoutController;
use App\Http\Middleware\ApplyTenantPresentationContext;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\ResolveTenantContext;
use App\Http\Middleware\SetPermissionTeamContext;
use Filament\Auth\Http\Controllers\LogoutController as FilamentLogoutController;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

final class AdminPanelProvider extends PanelProvider
{
    public function register(): void
    {
        parent::register();

        $this->app->bind(FilamentLogoutController::class, ApplicationLogoutController::class);
    }

    public function boot(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::SIDEBAR_NAV_START,
            fn (): View => $this->tenantContextView('sidebar'),
        );
        FilamentView::registerRenderHook(
            PanelsRenderHook::PAGE_HEADER_HEADING_BEFORE,
            fn (): View => $this->tenantContextView('breadcrumb'),
        );
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->pages([
                Dashboard::class,
            ])
            ->resources([
                TenantResource::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureActiveUser::class,
            ], isPersistent: true)
            ->persistentMiddleware([
                ResolveTenantContext::class,
                SetPermissionTeamContext::class,
                EnsureTenantIsActive::class,
                ApplyTenantPresentationContext::class,
            ]);
    }

    private function tenantContextView(string $surface): View
    {
        return view('filament.components.global-context', [
            'indicator' => TenantContextIndicator::fromRequest(request()),
            'surface' => $surface,
        ]);
    }
}
