<?php

namespace Tests\Feature\Api\Concerns;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

trait InteractsWithApiFoundation
{
    protected function seedApiPermissions(): void
    {
        app(PermissionCatalogueSeeder::class)->run();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    protected function administrator(string $password = 'password'): User
    {
        $this->seedApiPermissions();

        $administrator = User::factory()->create([
            'tenant_id' => null,
            'is_active' => true,
            'password' => Hash::make($password),
        ]);

        app(PlatformAdministrator::class)->assign($administrator);

        return $administrator;
    }

    protected function tenantUser(Tenant $tenant, string $password = 'password'): User
    {
        $this->seedApiPermissions();

        $globalEditor = Role::query()->where([
            'name' => 'Editor',
            'guard_name' => 'web',
            'tenant_id' => null,
        ])->firstOrFail();
        $role = Role::query()->create([
            'name' => 'Editor',
            'guard_name' => 'web',
            'tenant_id' => $tenant->getKey(),
        ]);
        $role->syncPermissions($globalEditor->permissions()->get());

        $user = User::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'is_active' => true,
            'password' => Hash::make($password),
        ]);

        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId((int) $tenant->getKey());

        try {
            $user->assignRole($role);
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
        }

        return $user;
    }

    /** @return array<string, string> */
    protected function csrfHeaders(): array
    {
        $response = $this->get('/sanctum/csrf-cookie');
        $csrfCookie = $response->getCookie('XSRF-TOKEN');

        self::assertNotNull($csrfCookie);

        foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
            $cookie = $response->getCookie((string) $cookieName);

            if ($cookie !== null) {
                $this->withUnencryptedCookie((string) $cookieName, $cookie->getValue());
            }
        }

        $this->withCredentials();

        $token = urldecode($csrfCookie->getValue());

        return [
            'Origin' => 'http://localhost',
            'Referer' => 'http://localhost/',
            'X-XSRF-TOKEN' => $token,
        ];
    }
}
