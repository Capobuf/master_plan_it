<?php

namespace App\Providers;

use App\Models\Tenant;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Policies\TenantPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
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
}
