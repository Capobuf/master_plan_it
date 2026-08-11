<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;

final class TenantPolicy
{
    public function __construct(private readonly PlatformAdministrator $platformAdministrator) {}

    public function viewAny(User $user): bool
    {
        return $this->platformAdministrator->allows($user, 'platform.tenants.view');
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $this->platformAdministrator->allows($user, 'platform.tenants.view');
    }

    public function create(User $user): bool
    {
        return $this->platformAdministrator->allows($user, 'platform.tenants.create');
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $this->platformAdministrator->allows($user, 'platform.tenants.update');
    }

    public function deactivate(User $user, Tenant $tenant): bool
    {
        return $this->platformAdministrator->allows($user, 'platform.tenants.deactivate');
    }

    public function reactivate(User $user, Tenant $tenant): bool
    {
        return $this->platformAdministrator->allows($user, 'platform.tenants.reactivate');
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Tenant $tenant): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Tenant $tenant): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, Tenant $tenant): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }
}
