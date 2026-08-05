<?php

namespace App\Providers;

use App\Domain\Tenancy\Data\TenantContext;
use App\Http\Middleware\ApplyOptionalTenantContext;
use App\Support\Diagnostics\CorrelationId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(
            CorrelationId::class,
            fn (): CorrelationId => CorrelationId::resolveFor(request()),
        );

        $this->app->scoped(TenantContext::class, function (Application $app): TenantContext {
            $request = $app->make('request');
            $context = $request instanceof Request
                ? $request->attributes->get(TenantContext::class)
                : null;

            if (! $context instanceof TenantContext) {
                throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
            }

            return $context;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Livewire::setUpdateRoute(
            fn ($handle, string $path) => Route::post($path, $handle)
                ->middleware(['web', ApplyOptionalTenantContext::class]),
        );
    }
}
