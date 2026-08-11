<?php

namespace Tests\Feature\Tenancy;

use App\Domain\Tenancy\Actions\EnterTenantContext;
use App\Domain\Tenancy\Actions\LeaveTenantContext;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Models\AuditEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AdministratorContextAuditTest extends TestCase
{
    use DatabaseTransactions;

    private const SESSION_KEY = 'tenant_context.tenant_id';

    public function test_administrator_enter_and_leave_keep_the_real_global_identity_and_audit_the_selected_tenant(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create();
        $session = $this->app['session.store'];
        $enterCorrelationId = (string) str()->uuid();
        $leaveCorrelationId = (string) str()->uuid();
        $originalTenantId = $administrator->tenant_id;
        $originalPassword = $administrator->password;

        $context = app(EnterTenantContext::class)->execute(
            $administrator,
            $tenant,
            $session,
            $enterCorrelationId,
        );

        $administrator->refresh();
        $this->assertInstanceOf(TenantContext::class, $context);
        $this->assertSame($administrator->getKey(), $context->actor->getKey());
        $this->assertNull($context->actor->tenant_id);
        $this->assertSame($tenant->getKey(), $context->tenantId);
        $this->assertSame($tenant->getKey(), $session->get(self::SESSION_KEY));
        $this->assertSame($originalTenantId, $administrator->tenant_id);
        $this->assertSame($originalPassword, $administrator->password);
        $this->assertContextAudit(
            $administrator,
            $tenant,
            'tenant.context.entered',
            $enterCorrelationId,
            ['selected_tenant_id' => $tenant->getKey(), 'previous_tenant_id' => null],
        );

        app(LeaveTenantContext::class)->execute($administrator, $session, $leaveCorrelationId);

        $administrator->refresh();
        $this->assertFalse($session->has(self::SESSION_KEY));
        $this->assertNull($administrator->tenant_id);
        $this->assertSame($originalPassword, $administrator->password);
        $this->assertContextAudit(
            $administrator,
            $tenant,
            'tenant.context.left',
            $leaveCorrelationId,
            ['selected_tenant_id' => $tenant->getKey()],
        );
    }

    public function test_inactive_tenant_blocks_its_user_but_administrator_keeps_authorized_context_until_reactivation(): void
    {
        $tenant = Tenant::factory()->create(['state' => TenantState::Inactive]);
        $tenantUser = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $administrator = $this->administrator();
        $session = $this->app['session.store'];
        $correlationId = (string) str()->uuid();

        $tenantRequest = Request::create('/tenant-bound', 'GET');
        $tenantRequest->setUserResolver(fn (): User => $tenantUser);
        $tenantRequest->attributes->set(TenantContext::class, new TenantContext($tenant, $tenantUser));

        try {
            app(EnsureTenantIsActive::class)->handle($tenantRequest, fn () => response('unsafe'));
            $this->fail('An inactive tenant user was allowed to continue.');
        } catch (AuthorizationException $exception) {
            $this->assertSame('TENANT_INACTIVE', $exception->getMessage());
        }

        $context = app(EnterTenantContext::class)->execute($administrator, $tenant, $session, $correlationId);
        $administratorRequest = Request::create('/tenant-bound', 'GET');
        $administratorRequest->setUserResolver(fn (): User => $administrator);
        $administratorRequest->attributes->set(TenantContext::class, $context);

        $response = app(EnsureTenantIsActive::class)->handle(
            $administratorRequest,
            fn () => response('administrator-authorized-context'),
        );

        $this->assertSame('administrator-authorized-context', $response->getContent());
        $this->assertSame($administrator->getKey(), $context->actor->getKey());
        $this->assertNull($context->actor->tenant_id);
        $this->assertSame($tenant->getKey(), $context->tenantId);
        $this->assertContextAudit(
            $administrator,
            $tenant,
            'tenant.context.entered',
            $correlationId,
            ['selected_tenant_id' => $tenant->getKey(), 'previous_tenant_id' => null],
        );
    }

    private function administrator(): User
    {
        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);

        $administrator = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($administrator);

        return $administrator;
    }

    /** @param array<string, mixed> $expectedProperties */
    private function assertContextAudit(
        User $actor,
        Tenant $tenant,
        string $eventType,
        string $correlationId,
        array $expectedProperties,
    ): void {
        $event = AuditEvent::query()->where('correlation_id', $correlationId)->sole();
        $persistedActor = User::query()->findOrFail($actor->getKey());

        $this->assertSame($correlationId, $event->correlation_id);
        $this->assertSame($eventType, $event->event_type);
        $this->assertSame($tenant->getKey(), $event->tenant_id);
        $this->assertSame($actor->getKey(), $event->actor_user_id);
        $this->assertSame($persistedActor->name, $event->actor_label);
        $this->assertSame($tenant->getMorphClass(), $event->subject_type);
        $this->assertSame($tenant->getKey(), $event->subject_id);
        $actualProperties = $event->properties;
        ksort($expectedProperties);
        ksort($actualProperties);
        $this->assertSame($expectedProperties, $actualProperties);
    }
}
