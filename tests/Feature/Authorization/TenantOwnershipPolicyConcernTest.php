<?php

namespace Tests\Feature\Authorization;

use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantOwnership;
use App\Providers\AuthServiceProvider;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TenantOwnershipPolicyConcernTest extends TestCase
{
    use DatabaseTransactions;

    private const string ABILITY = 'vendor.view';

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        parent::tearDown();
    }

    public function test_same_tenant_user_with_an_exact_custom_role_ability_is_allowed(): void
    {
        [$tenant, $actor] = $this->tenantActorWithAbility(self::ABILITY);
        $resource = User::factory()->create(['tenant_id' => $tenant->getKey()]);

        $response = $this->policy()->check($actor, self::ABILITY, new TenantContext($tenant, $actor), $resource);

        $this->assertTrue($response->allowed());
    }

    public function test_missing_ability_denies_even_for_a_same_tenant_resource(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $resource = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());

        $response = $this->policy()->check($actor, self::ABILITY, new TenantContext($tenant, $actor), $resource);

        $this->assertDenied($response, 'PERMISSION_DENIED');
    }

    public function test_exact_ability_is_checked_before_a_missing_context(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $resource = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());

        $response = $this->policy()->check($actor, self::ABILITY, null, $resource);

        $this->assertDenied($response, 'PERMISSION_DENIED');
    }

    public function test_a_persisted_actor_that_was_deactivated_after_loading_is_denied(): void
    {
        [$tenant, $actor] = $this->tenantActorWithAbility(self::ABILITY);
        $resource = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        User::query()->whereKey($actor->getKey())->update(['is_active' => false]);

        $response = $this->policy()->check($actor, self::ABILITY, new TenantContext($tenant, $actor), $resource);

        $this->assertDenied($response, 'PERMISSION_DENIED');
    }

    public function test_missing_context_fails_closed_with_the_stable_context_code(): void
    {
        [$tenant, $actor] = $this->tenantActorWithAbility(self::ABILITY);
        $resource = User::factory()->create(['tenant_id' => $tenant->getKey()]);

        $response = $this->policy()->check($actor, self::ABILITY, null, $resource);

        $this->assertDenied($response, 'TENANT_CONTEXT_REQUIRED');
    }

    public function test_context_identity_and_tenant_membership_mismatches_are_denied(): void
    {
        [$tenant, $actor] = $this->tenantActorWithAbility(self::ABILITY);
        $otherActor = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $resource = User::factory()->create(['tenant_id' => $tenant->getKey()]);

        $identityMismatch = $this->policy()->check(
            $actor,
            self::ABILITY,
            new TenantContext($tenant, $otherActor),
            $resource,
        );
        $this->assertDenied($identityMismatch, 'PERMISSION_DENIED');

        $otherTenant = Tenant::factory()->create();
        $this->grantRole($actor, $otherTenant, self::ABILITY, 'Mismatched membership role');
        $membershipMismatch = $this->policy()->check(
            $actor,
            self::ABILITY,
            new TenantContext($otherTenant, $actor),
            User::factory()->create(['tenant_id' => $otherTenant->getKey()]),
        );
        $this->assertDenied($membershipMismatch, 'PERMISSION_DENIED');
    }

    public function test_an_actor_current_key_spoofed_to_a_persisted_administrator_is_denied_without_changing_the_team(): void
    {
        [$tenant, $actor] = $this->tenantActorWithAbility(self::ABILITY);
        $resource = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $administrator = User::factory()->create(['tenant_id' => null]);
        app(PlatformAdministrator::class)->assign($administrator);
        $actor->forceFill([$actor->getKeyName() => $administrator->getKey()]);
        $registrar = app(PermissionRegistrar::class);

        $response = $this->policy()->check(
            $actor,
            self::ABILITY,
            new TenantContext($tenant, $actor),
            $resource,
        );

        $this->assertDenied($response, 'PERMISSION_DENIED');
        $this->assertNotSame($actor->getRawOriginal($actor->getKeyName()), $actor->getKey());
        $this->assertSame($tenant->getKey(), $registrar->getPermissionsTeamId());
    }

    public function test_a_context_actor_current_key_spoofed_to_the_real_actor_is_denied_without_changing_the_team(): void
    {
        [$tenant, $actor] = $this->tenantActorWithAbility(self::ABILITY);
        $resource = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $contextActor = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $contextActor->forceFill([$contextActor->getKeyName() => $actor->getKey()]);
        $registrar = app(PermissionRegistrar::class);

        $response = $this->policy()->check(
            $actor,
            self::ABILITY,
            new TenantContext($tenant, $contextActor),
            $resource,
        );

        $this->assertDenied($response, 'PERMISSION_DENIED');
        $this->assertNotSame(
            $contextActor->getRawOriginal($contextActor->getKeyName()),
            $contextActor->getKey(),
        );
        $this->assertSame($tenant->getKey(), $registrar->getPermissionsTeamId());
    }

    public function test_a_cross_team_ability_cannot_authorize_an_actor_context_and_resource_from_another_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => $tenantA->getKey()]);
        $resource = User::factory()->create(['tenant_id' => $tenantA->getKey()]);
        $this->grantRole($actor, $tenantB, self::ABILITY, 'Tenant B only role');
        $registrar = app(PermissionRegistrar::class);
        $this->assertSame($tenantB->getKey(), $registrar->getPermissionsTeamId());

        $response = $this->policy()->check(
            $actor,
            self::ABILITY,
            new TenantContext($tenantA, $actor),
            $resource,
        );

        $this->assertDenied($response, 'PERMISSION_DENIED');
        $this->assertSame($tenantB->getKey(), $registrar->getPermissionsTeamId());
    }

    public function test_nonpersisted_and_foreign_resources_have_the_same_safe_not_found_response(): void
    {
        [$tenant, $actor] = $this->tenantActorWithAbility(self::ABILITY);
        $context = new TenantContext($tenant, $actor);
        $nonPersisted = new User(['tenant_id' => $tenant->getKey()]);
        $foreignTenant = Tenant::factory()->create();
        $foreign = User::factory()->create(['tenant_id' => $foreignTenant->getKey()]);

        $missingResponse = $this->policy()->check($actor, self::ABILITY, $context, $nonPersisted);
        $foreignResponse = $this->policy()->check($actor, self::ABILITY, $context, $foreign);

        $this->assertNotFound($missingResponse);
        $this->assertNotFound($foreignResponse);
        $this->assertSame($missingResponse->message(), $foreignResponse->message());
        $this->assertSame($missingResponse->status(), $foreignResponse->status());
    }

    public function test_resource_lookup_scopes_the_persisted_tenant_before_its_key_and_ignores_tampered_attributes(): void
    {
        [$tenant, $actor] = $this->tenantActorWithAbility(self::ABILITY);
        $resource = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $queries = [];
        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query;
        });

        $response = $this->policy()->check($actor, self::ABILITY, new TenantContext($tenant, $actor), $resource);

        $this->assertTrue($response->allowed());
        $lookup = collect($queries)->first(
            fn (QueryExecuted $query): bool => str_starts_with(strtolower($query->sql), 'select exists')
                && str_contains($query->sql, '`users`.`tenant_id`'),
        );
        $this->assertInstanceOf(QueryExecuted::class, $lookup);
        $this->assertMatchesRegularExpression(
            '/`users`\.`tenant_id` = \? and `users`\.`id` = \?/i',
            $lookup->sql,
        );
        $this->assertSame([(int) $tenant->getKey(), (int) $resource->getKey()], $lookup->bindings);

        $foreignTenant = Tenant::factory()->create();
        $foreignResource = User::factory()->create(['tenant_id' => $foreignTenant->getKey()]);
        $foreignResource->tenant_id = $tenant->getKey();

        $tamperedResponse = $this->policy()->check(
            $actor,
            self::ABILITY,
            new TenantContext($tenant, $actor),
            $foreignResource,
        );

        $this->assertNotFound($tamperedResponse);
    }

    public function test_a_foreign_resource_with_a_current_key_mutated_to_a_same_tenant_resource_is_safely_not_found(): void
    {
        [$tenant, $actor] = $this->tenantActorWithAbility(self::ABILITY);
        $sameTenantResource = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $foreignTenant = Tenant::factory()->create();
        $foreignResource = User::factory()->create(['tenant_id' => $foreignTenant->getKey()]);
        $foreignOriginalKey = $foreignResource->getRawOriginal($foreignResource->getKeyName());
        $foreignResource->forceFill([$foreignResource->getKeyName() => $sameTenantResource->getKey()]);
        $registrar = app(PermissionRegistrar::class);

        $response = $this->policy()->check(
            $actor,
            self::ABILITY,
            new TenantContext($tenant, $actor),
            $foreignResource,
        );

        $this->assertNotFound($response);
        $this->assertNotSame($foreignOriginalKey, $foreignResource->getKey());
        $this->assertSame($tenant->getKey(), $registrar->getPermissionsTeamId());
    }

    public function test_a_persisted_selected_tenant_with_a_mutated_current_key_fails_closed_without_changing_the_team(): void
    {
        $originalTenant = Tenant::factory()->create();
        $selectedTenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => $selectedTenant->getKey()]);
        $this->grantRole($actor, $selectedTenant, self::ABILITY, 'Selected tenant reader');
        $resource = User::factory()->create(['tenant_id' => $selectedTenant->getKey()]);
        $originalTenant->forceFill([$originalTenant->getKeyName() => $selectedTenant->getKey()]);
        $context = new TenantContext($originalTenant, $actor);
        $registrar = app(PermissionRegistrar::class);

        $response = $this->policy()->check($actor, self::ABILITY, $context, $resource);

        $this->assertDenied($response, 'TENANT_CONTEXT_REQUIRED');
        $this->assertNotSame(
            $originalTenant->getRawOriginal($originalTenant->getKeyName()),
            $originalTenant->getKey(),
        );
        $this->assertSame($selectedTenant->getKey(), $registrar->getPermissionsTeamId());
    }

    public function test_unknown_abilities_fail_closed_for_tenant_users_and_administrators_without_leaking_team_scope(): void
    {
        [$tenant, $actor] = $this->tenantActorWithAbility(self::ABILITY);
        $resource = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $tenantResponse = $this->policy()->check(
            $actor,
            'unknown.ability',
            new TenantContext($tenant, $actor),
            $resource,
        );
        $this->assertDenied($tenantResponse, 'PERMISSION_DENIED');

        $administrator = User::factory()->create(['tenant_id' => null]);
        app(PlatformAdministrator::class)->assign($administrator);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(992);

        $administratorResponse = $this->policy()->check(
            $administrator,
            'unknown.ability',
            new TenantContext($tenant, $administrator),
            $resource,
        );

        $this->assertDenied($administratorResponse, 'PERMISSION_DENIED');
        $this->assertSame(992, $registrar->getPermissionsTeamId());
    }

    public function test_a_context_with_a_nonpersisted_tenant_fails_closed(): void
    {
        $administrator = User::factory()->create(['tenant_id' => null]);
        app(PlatformAdministrator::class)->assign($administrator);
        $missingTenant = new Tenant;
        $missingTenant->forceFill([
            'id' => 987654321,
            'language_code' => 'it',
            'timezone' => 'Europe/Rome',
            'currency_code' => 'EUR',
            'default_vat_rate' => '22.000000',
        ]);
        $resourceTenant = Tenant::factory()->create();
        $resource = User::factory()->create(['tenant_id' => $resourceTenant->getKey()]);

        $response = $this->policy()->check(
            $administrator,
            self::ABILITY,
            new TenantContext($missingTenant, $administrator),
            $resource,
        );

        $this->assertDenied($response, 'TENANT_CONTEXT_REQUIRED');
    }

    public function test_a_force_filled_nonpersisted_context_tenant_cannot_reuse_a_real_tenant_id(): void
    {
        [$tenant, $actor] = $this->tenantActorWithAbility(self::ABILITY);
        $resource = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $forgedTenant = new Tenant;
        $forgedTenant->forceFill($tenant->getAttributes());
        $this->assertFalse($forgedTenant->exists);
        $this->assertSame($tenant->getKey(), $forgedTenant->getKey());

        $response = $this->policy()->check(
            $actor,
            self::ABILITY,
            new TenantContext($forgedTenant, $actor),
            $resource,
        );

        $this->assertDenied($response, 'TENANT_CONTEXT_REQUIRED');
    }

    public function test_the_editor_role_name_has_no_authorization_magic_without_the_exact_ability(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $resource = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->getKey());
        $editor = Role::query()->where([
            'name' => 'Editor',
            'guard_name' => 'web',
            'tenant_id' => null,
        ])->firstOrFail();
        $editor->syncPermissions([]);
        $actor->assignRole($editor);
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');

        $response = $this->policy()->check($actor, self::ABILITY, new TenantContext($tenant, $actor), $resource);

        $this->assertDenied($response, 'PERMISSION_DENIED');
    }

    public function test_administrator_uses_scope_zero_but_still_cannot_observe_a_foreign_resource(): void
    {
        $tenant = Tenant::factory()->create();
        $foreignTenant = Tenant::factory()->create();
        $administrator = User::factory()->create(['tenant_id' => null]);
        app(PlatformAdministrator::class)->assign($administrator);
        $context = new TenantContext($tenant, $administrator);
        $sameTenantResource = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $foreignResource = User::factory()->create(['tenant_id' => $foreignTenant->getKey()]);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(991);

        $sameTenantResponse = $this->policy()->check($administrator, self::ABILITY, $context, $sameTenantResource);
        $foreignResponse = $this->policy()->check($administrator, self::ABILITY, $context, $foreignResource);

        $this->assertTrue($sameTenantResponse->allowed());
        $this->assertNotFound($foreignResponse);
        $this->assertSame(991, $registrar->getPermissionsTeamId());
    }

    public function test_an_owning_policy_can_apply_its_current_state_invariant_after_administrator_authorization(): void
    {
        $tenant = Tenant::factory()->create();
        $administrator = User::factory()->create(['tenant_id' => null]);
        app(PlatformAdministrator::class)->assign($administrator);
        $resource = User::factory()->create(['tenant_id' => $tenant->getKey()]);

        $response = $this->policy()->checkWithInvariant(
            $administrator,
            self::ABILITY,
            new TenantContext($tenant, $administrator),
            $resource,
        );

        $this->assertDenied($response, 'CURRENT_STATE_INVALID');
    }

    public function test_the_auth_provider_is_loaded_and_does_not_add_an_application_gate_before_hook(): void
    {
        $providers = app()->getProviders(AuthServiceProvider::class);

        $this->assertCount(1, $providers);
        $this->assertStringNotContainsString(
            'Gate::before',
            (string) file_get_contents(app_path('Providers/AuthServiceProvider.php')),
        );
    }

    /** @return array{Tenant, User} */
    private function tenantActorWithAbility(string $ability): array
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $this->grantRole($actor, $tenant, $ability, 'Custom '.str()->uuid());

        return [$tenant, $actor];
    }

    private function grantRole(User $actor, Tenant $tenant, ?string $ability, string $name): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->getKey());
        $role = Role::create([
            'name' => $name,
            'guard_name' => 'web',
            'tenant_id' => $tenant->getKey(),
        ]);

        if ($ability !== null) {
            $role->givePermissionTo(Permission::findOrCreate($ability, 'web'));
        }

        $actor->assignRole($role);
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');
    }

    private function policy(): object
    {
        return new class(app(PermissionRegistrar::class), app(PlatformAdministrator::class))
        {
            use AuthorizesTenantOwnership;

            public function __construct(
                private readonly PermissionRegistrar $permissionRegistrar,
                private readonly PlatformAdministrator $platformAdministrator,
            ) {}

            public function check(User $actor, string $ability, ?TenantContext $context, Model $resource): Response
            {
                return $this->authorizeTenantOwnership(
                    $actor,
                    $ability,
                    $context,
                    $resource,
                    $this->permissionRegistrar,
                    $this->platformAdministrator,
                );
            }

            public function checkWithInvariant(User $actor, string $ability, ?TenantContext $context, Model $resource): Response
            {
                $response = $this->check($actor, $ability, $context, $resource);

                return $response->allowed()
                    ? Response::deny('CURRENT_STATE_INVALID')
                    : $response;
            }
        };
    }

    private function assertDenied(Response $response, string $message): void
    {
        $this->assertTrue($response->denied());
        $this->assertSame($message, $response->message());
        $this->assertNull($response->status());
    }

    private function assertNotFound(Response $response): void
    {
        $this->assertTrue($response->denied());
        $this->assertSame('RESOURCE_NOT_FOUND', $response->message());
        $this->assertSame(404, $response->status());
    }
}
