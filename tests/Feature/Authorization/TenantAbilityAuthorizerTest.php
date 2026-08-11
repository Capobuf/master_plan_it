<?php

namespace Tests\Feature\Authorization;

use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use App\Support\Authorization\TenantAbilityAuthorizer;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class TenantAbilityAuthorizerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    protected function tearDown(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_same_tenant_update_grant_authorizes_update_and_implied_view_and_restores_team(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->tenantActor($tenant, ['tenant-settings.update']);
        $context = new TenantContext($tenant, $actor);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(812);

        [$persistedActor, $persistedTenant] = app(TenantAbilityAuthorizer::class)
            ->authorize($actor, $context, 'tenant-settings.view');

        $this->assertSame($actor->getKey(), $persistedActor->getKey());
        $this->assertSame($tenant->getKey(), $persistedTenant->getKey());
        $this->assertSame(812, $registrar->getPermissionsTeamId());
        $this->assertTrue(app(TenantAbilityAuthorizer::class)->allows($actor, $context, 'tenant-settings.update'));
        $this->assertSame(812, $registrar->getPermissionsTeamId());
    }

    public function test_missing_ability_and_cross_tenant_context_are_denied_without_leaking_team_scope(): void
    {
        $tenant = Tenant::factory()->create();
        $foreign = Tenant::factory()->create();
        $actor = $this->tenantActor($tenant, ['tenant-settings.view']);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(813);

        $this->assertAuthorizationFailure(
            fn () => app(TenantAbilityAuthorizer::class)->authorize(
                $actor,
                new TenantContext($tenant, $actor),
                'tenant-settings.update',
            ),
            'PERMISSION_DENIED',
        );
        $this->assertSame(813, $registrar->getPermissionsTeamId());
        $this->assertAuthorizationFailure(
            fn () => app(TenantAbilityAuthorizer::class)->authorize(
                $actor,
                new TenantContext($foreign, $actor),
                'tenant-settings.view',
            ),
            'PERMISSION_DENIED',
        );
        $this->assertSame(813, $registrar->getPermissionsTeamId());
    }

    public function test_actor_context_identity_spoof_and_force_filled_models_are_denied(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->tenantActor($tenant, ['tenant-settings.update']);
        $differentActor = $this->tenantActor($tenant, ['tenant-settings.update']);
        $authorizer = app(TenantAbilityAuthorizer::class);

        $this->assertAuthorizationFailure(
            fn () => $authorizer->authorize(
                $actor,
                new TenantContext($tenant, $differentActor),
                'tenant-settings.update',
            ),
            'PERMISSION_DENIED',
        );

        $tenant->forceFill(['id' => $tenant->getKey() + 10_000]);
        $this->assertAuthorizationFailure(
            fn () => $authorizer->authorize(
                $actor,
                new TenantContext($tenant, $actor),
                'tenant-settings.update',
            ),
            'TENANT_CONTEXT_REQUIRED',
        );
    }

    public function test_inactive_actor_and_inactive_tenant_user_are_denied(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->tenantActor($tenant, ['tenant-settings.update']);
        $context = new TenantContext($tenant, $actor);
        $authorizer = app(TenantAbilityAuthorizer::class);

        $actor->forceFill(['is_active' => false])->save();
        $this->assertAuthorizationFailure(
            fn () => $authorizer->authorize($actor, $context, 'tenant-settings.update'),
            'PERMISSION_DENIED',
        );

        $actor->forceFill(['is_active' => true])->save();
        $tenant->forceFill(['state' => TenantState::Inactive])->save();
        $inactiveContext = new TenantContext($tenant->refresh(), $actor->refresh());
        $this->assertAuthorizationFailure(
            fn () => $authorizer->authorize($actor->refresh(), $inactiveContext, 'tenant-settings.update'),
            'TENANT_INACTIVE',
        );
    }

    public function test_administrator_can_authorize_selected_inactive_tenant_without_membership(): void
    {
        $tenant = Tenant::factory()->create(['state' => TenantState::Inactive]);
        $administrator = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($administrator);
        $context = new TenantContext($tenant, $administrator);

        [$actor, $selectedTenant] = app(TenantAbilityAuthorizer::class)
            ->authorize($administrator, $context, 'tenant-settings.update');

        $this->assertNull($actor->tenant_id);
        $this->assertSame($tenant->getKey(), $selectedTenant->getKey());
    }

    /** @param list<string> $abilities */
    private function tenantActor(Tenant $tenant, array $abilities): User
    {
        $role = Role::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Settings '.str()->uuid(),
            'guard_name' => 'web',
        ]);
        $role->syncPermissions($abilities);
        $actor = User::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'is_active' => true,
        ]);
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId((int) $tenant->getKey());

        try {
            $actor->assignRole($role);
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
        }

        return $actor;
    }

    /** @param callable(): mixed $operation */
    private function assertAuthorizationFailure(callable $operation, string $message): void
    {
        try {
            $operation();
            $this->fail('Authorization unexpectedly succeeded.');
        } catch (AuthorizationException $exception) {
            $this->assertSame($message, $exception->getMessage());
        }
    }
}
