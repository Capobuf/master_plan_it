<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\DB;

final class LeaveTenantContext
{
    public function __construct(
        private readonly PlatformAdministrator $platformAdministrator,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function execute(User $actor, Session $session, string $correlationId): void
    {
        if (! $this->platformAdministrator->allows($actor, 'platform.tenants.view')) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        $selectedTenantId = $session->get(EnterTenantContext::SESSION_KEY);

        if (! is_int($selectedTenantId) || $selectedTenantId < 1) {
            throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
        }

        $tenant = Tenant::query()->find($selectedTenantId);

        if ($tenant === null) {
            throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
        }

        DB::transaction(function () use ($actor, $correlationId, $tenant): void {
            $this->auditRecorder->record(
                eventType: 'tenant.context.left',
                correlationId: $correlationId,
                properties: new AuditProperties([
                    'selected_tenant_id' => $tenant->getKey(),
                ]),
                actor: $actor,
                tenantId: (int) $tenant->getKey(),
                subject: $tenant,
            );
        });

        $session->forget(EnterTenantContext::SESSION_KEY);
    }
}
