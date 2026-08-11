<?php

namespace App\Providers;

use App\Domain\Tenancy\Data\TenantContext;
use App\Support\Diagnostics\CorrelationId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

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
    public function boot(): void {}
}
