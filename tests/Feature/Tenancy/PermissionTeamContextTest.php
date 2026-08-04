<?php

namespace Tests\Feature\Tenancy;

use App\Domain\Tenancy\Data\TenantContext;
use App\Http\Middleware\SetPermissionTeamContext;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PermissionTeamContextTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        parent::tearDown();
    }

    public function test_team_context_is_set_before_authorization_and_prior_state_is_restored_after_success(): void
    {
        [$tenant, $actor] = $this->tenantActorWithPermission('dashboard.view');
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(901);
        $actor->setRelation('roles', collect());
        $actor->setRelation('permissions', collect());
        $request = $this->requestWithContext(new TenantContext($tenant, $actor));

        $response = app(SetPermissionTeamContext::class)->handle($request, function () use ($actor, $registrar, $tenant) {
            $this->assertSame($tenant->getKey(), $registrar->getPermissionsTeamId());
            $this->assertFalse($actor->relationLoaded('roles'));
            $this->assertFalse($actor->relationLoaded('permissions'));
            $this->assertTrue($actor->checkPermissionTo('dashboard.view'));

            return response('authorized');
        });

        $this->assertSame('authorized', $response->getContent());
        $this->assertSame(901, $registrar->getPermissionsTeamId());
        $this->assertFalse($actor->relationLoaded('roles'));
        $this->assertFalse($actor->relationLoaded('permissions'));
    }

    public function test_team_context_and_loaded_permission_relations_are_restored_after_exception(): void
    {
        [$tenant, $actor] = $this->tenantActorWithPermission('dashboard.view');
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(902);
        $actor->setRelation('roles', collect());
        $actor->setRelation('permissions', collect());
        $request = $this->requestWithContext(new TenantContext($tenant, $actor));

        try {
            app(SetPermissionTeamContext::class)->handle($request, function (): never {
                throw new RuntimeException('downstream failure');
            });
            $this->fail('Downstream exception was swallowed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('downstream failure', $exception->getMessage());
            $this->assertSame(902, $registrar->getPermissionsTeamId());
            $this->assertFalse($actor->relationLoaded('roles'));
            $this->assertFalse($actor->relationLoaded('permissions'));
        }
    }

    public function test_repeated_tenant_iterations_do_not_leak_team_or_permissions(): void
    {
        [$tenantA, $actorA] = $this->tenantActorWithPermission('dashboard.view');
        [$tenantB, $actorB] = $this->tenantActorWithPermission('audit.view');
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);

        foreach ([[$tenantA, $actorA, 'dashboard.view'], [$tenantB, $actorB, 'audit.view']] as [$tenant, $actor, $ability]) {
            $request = $this->requestWithContext(new TenantContext($tenant, $actor));

            app(SetPermissionTeamContext::class)->handle($request, function () use ($ability, $actor, $registrar, $tenant) {
                $this->assertSame($tenant->getKey(), $registrar->getPermissionsTeamId());
                $this->assertTrue($actor->checkPermissionTo($ability));

                return response('iteration');
            });

            $this->assertNull($registrar->getPermissionsTeamId());
            $this->assertFalse($actor->relationLoaded('roles'));
            $this->assertFalse($actor->relationLoaded('permissions'));
        }
    }

    public function test_missing_request_context_fails_closed_without_changing_team(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(903);

        try {
            app(SetPermissionTeamContext::class)->handle(Request::create('/missing-context'), fn () => response('unsafe'));
            $this->fail('Permission middleware accepted a missing TenantContext.');
        } catch (AuthorizationException $exception) {
            $this->assertSame('TENANT_CONTEXT_REQUIRED', $exception->getMessage());
            $this->assertSame(903, $registrar->getPermissionsTeamId());
        }
    }

    /** @return array{Tenant, User} */
    private function tenantActorWithPermission(string $ability): array
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->getKey());
        $permission = Permission::findOrCreate($ability, 'web');
        $role = Role::create([
            'name' => 'Role '.str()->uuid(),
            'guard_name' => 'web',
            'tenant_id' => $tenant->getKey(),
        ]);
        $role->givePermissionTo($permission);
        $actor->assignRole($role);
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');

        return [$tenant, $actor];
    }

    private function requestWithContext(TenantContext $context): Request
    {
        $request = Request::create('/permission-context', 'GET');
        $request->attributes->set(TenantContext::class, $context);
        $request->setUserResolver(fn (): User => $context->actor);

        return $request;
    }
}
