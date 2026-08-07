<?php

namespace Tests\Feature\Api\Auth;

use App\Domain\Tenancy\Enums\TenantState;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class ApiAuthenticationHttpTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_login_returns_a_safe_resource_and_regenerates_the_session(): void
    {
        $password = 'api-login-password';
        $user = $this->administrator($password);
        $sessionId = session()->getId();

        $response = $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $user->getKey())
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($sessionId, session()->getId());
    }

    public function test_invalid_credentials_use_the_uniform_error_envelope(): void
    {
        $user = $this->administrator('correct-password');

        $response = $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ], ['X-Correlation-ID' => '6f2c58f1-37d3-4bb5-9e69-97b7b4b0b7f2']);

        $response->assertStatus(422)
            ->assertJsonStructure(['error' => ['code', 'message', 'fields', 'correlation_id']])
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.correlation_id', '6f2c58f1-37d3-4bb5-9e69-97b7b4b0b7f2')
            ->assertJsonMissingPath('error.fields.password');
        $this->assertSame('6f2c58f1-37d3-4bb5-9e69-97b7b4b0b7f2', $response->headers->get('X-Correlation-ID'));
        $this->assertGuest();
    }

    public function test_inactive_actor_and_inactive_tenant_are_denied_without_disclosure(): void
    {
        $inactiveActor = User::factory()->inactive()->create(['password' => Hash::make('inactive-password')]);
        $this->seedApiPermissions();

        $inactiveActorResponse = $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/auth/login', [
            'email' => $inactiveActor->email,
            'password' => 'inactive-password',
        ]);
        $inactiveActorResponse->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->assertGuest();

        $tenant = Tenant::factory()->create(['state' => TenantState::Inactive]);
        $tenantUser = User::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'password' => Hash::make('inactive-tenant-password'),
        ]);

        $inactiveTenantResponse = $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/auth/login', [
            'email' => $tenantUser->email,
            'password' => 'inactive-tenant-password',
        ]);
        $inactiveTenantResponse->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->assertGuest();
    }

    public function test_sanctum_protected_me_requires_authentication_and_never_exposes_secrets(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTHENTICATION_REQUIRED')
            ->assertJsonStructure(['error' => ['code', 'message', 'fields', 'correlation_id']]);

        $user = $this->administrator();
        $this->actingAs($user, 'web');
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.id', $user->getKey())
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');
    }

    public function test_logout_invalidates_the_current_session_and_returns_no_content(): void
    {
        $user = $this->administrator();
        $this->actingAs($user, 'web');

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTHENTICATION_REQUIRED');
    }

    public function test_authenticated_password_change_mutates_only_the_server_side_password(): void
    {
        $user = $this->administrator('old-password');
        $this->actingAs($user, 'web');

        $response = $this->withHeaders($this->csrfHeaders())->putJson('/api/v1/auth/password', [
            'current_password' => 'old-password',
            'password' => 'new-api-password',
            'password_confirmation' => 'new-api-password',
        ]);

        $response->assertNoContent()->assertDontSee('new-api-password');
        $this->assertTrue(Hash::check('new-api-password', (string) User::query()->findOrFail($user->getKey())->password));
    }

    public function test_csrf_cookie_path_protects_stateful_mutations(): void
    {
        $this->get('/sanctum/csrf-cookie')->assertNoContent();

        $originalEnvironment = (string) app()->environment();

        try {
            app()->instance('env', 'local');
            $response = $this->withHeaders([
                'Origin' => 'http://localhost',
                'Referer' => 'http://localhost/',
            ])->postJson('/api/v1/auth/login', [
                'email' => 'missing@example.test',
                'password' => 'password',
            ]);
        } finally {
            app()->instance('env', $originalEnvironment);
        }

        $response->assertStatus(419)
            ->assertJsonPath('error.code', 'CSRF_TOKEN_MISMATCH')
            ->assertJsonStructure(['error' => ['correlation_id']]);
    }
}
