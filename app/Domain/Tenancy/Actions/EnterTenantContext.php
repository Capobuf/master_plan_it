<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\DB;

final class EnterTenantContext
{
    public const string SESSION_KEY = 'tenant_context.tenant_id';

    public function __construct(
        private readonly PlatformAdministrator $platformAdministrator,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function execute(
        User $actor,
        Tenant $target,
        Session $session,
        string $correlationId,
    ): TenantContext {
        if (! $this->platformAdministrator->allows($actor, 'platform.tenants.view')) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        $tenant = $this->persistedTarget($target);
        $previousTenantId = $session->get(self::SESSION_KEY);

        DB::transaction(function () use ($actor, $correlationId, $previousTenantId, $tenant): void {
            $this->auditRecorder->record(
                eventType: 'tenant.context.entered',
                correlationId: $correlationId,
                properties: new AuditProperties([
                    'selected_tenant_id' => $tenant->getKey(),
                    'previous_tenant_id' => is_int($previousTenantId) && $previousTenantId > 0
                        ? $previousTenantId
                        : null,
                ]),
                actor: $actor,
                tenantId: (int) $tenant->getKey(),
                subject: $tenant,
            );
        });

        $session->put(self::SESSION_KEY, (int) $tenant->getKey());

        return new TenantContext($tenant, $actor);
    }

    private function persistedTarget(Tenant $target): Tenant
    {
        if (! $target->exists || $target->getKey() === null) {
            throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
        }

        $tenant = Tenant::query()->find($target->getKey());

        if ($tenant === null) {
            throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
        }

        return $tenant;
    }
}
