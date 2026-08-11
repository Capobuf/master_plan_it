<?php

namespace App\Providers;

use App\Models\Tenant;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Policies\TenantPolicy;
use App\Policies\UserPolicy;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

final class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Tenant::class => TenantPolicy::class,
        Role::class => RolePolicy::class,
        User::class => UserPolicy::class,
    ];

    public function boot(PlatformAdministrator $platformAdministrator): void
    {
        Gate::define(
            'dashboard.view',
            static fn (User $user): bool => $user->tenant_id === null
                && $platformAdministrator->allows($user, 'dashboard.view'),
        );
    }
}
