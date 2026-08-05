<?php

namespace Tests\Feature\Auth;

use App\Domain\Tenancy\Enums\TenantState;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_active_administrator_authenticates_with_local_credentials(): void
    {
        $password = 'local-administrator-password';
        $administrator = $this->administrator($password);
        $sessionIdBeforeAuthentication = session()->getId();

        $this->authenticate($administrator, $password)
            ->assertRedirect(route('home'))
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($administrator);
        $this->assertNull(auth()->user()?->tenant_id);
        $this->assertNotSame($sessionIdBeforeAuthentication, session()->getId());
    }

    public function test_active_tenant_user_authenticates_with_local_credentials_for_their_tenant(): void
    {
        $password = 'local-tenant-user-password';
        $tenant = Tenant::factory()->create(['state' => TenantState::Active]);
        $tenantUser = User::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'is_active' => true,
            'password' => Hash::make($password),
        ]);
        $sessionIdBeforeAuthentication = session()->getId();

        $this->authenticate($tenantUser, $password)
            ->assertRedirect(route('home'))
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($tenantUser);
        $this->assertSame($tenant->getKey(), auth()->user()?->tenant_id);
        $this->assertNotSame($sessionIdBeforeAuthentication, session()->getId());
        $this->assertFalse(auth()->user()?->can('dashboard.view') ?? true);
    }

    public function test_invalid_credentials_are_rejected_without_authenticating_a_user(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-local-password'),
        ]);

        $this->authenticate($user, 'incorrect-local-password')
            ->assertSessionHasErrors(['email']);

        $this->assertGuest();
    }

    public function test_inactive_user_is_rejected_without_authenticating_a_user(): void
    {
        $password = 'inactive-user-password';
        $user = User::factory()->inactive()->create([
            'password' => Hash::make($password),
        ]);

        $this->authenticate($user, $password)
            ->assertSessionHasErrors(['email']);

        $this->assertGuest();
    }

    public function test_active_tenant_user_in_an_inactive_tenant_is_rejected_without_authenticating(): void
    {
        $password = 'inactive-tenant-user-password';
        $tenant = Tenant::factory()->create(['state' => TenantState::Inactive]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'is_active' => true,
            'password' => Hash::make($password),
        ]);

        $this->authenticate($user, $password)
            ->assertSessionHasErrors(['email']);

        $this->assertGuest();
    }

    private function administrator(string $password): User
    {
        app(PermissionCatalogueSeeder::class)->run();

        $administrator = User::factory()->create([
            'tenant_id' => null,
            'is_active' => true,
            'password' => Hash::make($password),
        ]);
        app(PlatformAdministrator::class)->assign($administrator);

        return $administrator;
    }

    private function authenticate(User $user, string $password): TestResponse
    {
        return $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => $password,
        ]);
    }
}
