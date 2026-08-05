<?php

namespace App\Domain\MasterData\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\MasterData\Actions\Concerns\ManagesCostCenterMutation;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\CostCenter;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateCostCenter
{
    use ManagesCostCenterMutation;

    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function execute(User $actor, TenantContext $context, string $name, ?CostCenter $parent, string $correlationId): CostCenter
    {
        $this->policy($context)->create($actor)->authorize();
        $tenant = $this->activeContextTenant($context);
        $persistedActor = $this->persistedActiveActor($actor);
        $name = $this->validatedName($name);

        return DB::transaction(function () use ($actor, $context, $correlationId, $name, $parent, $persistedActor, $tenant): CostCenter {
            $costCenters = $this->lockTenantCostCenters($tenant);
            $parentId = $this->parentId($parent, $costCenters);
            $this->assertValidHierarchy($costCenters, null, $parentId);

            try {
                $costCenter = CostCenter::query()->create([
                    'tenant_id' => $tenant->getKey(),
                    'parent_id' => $parentId,
                    'name' => $name,
                    'active' => true,
                    'lock_version' => 1,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'name' => 'The cost center name already exists for the current tenant.',
                ]);
            }

            $this->recordRevision($actor, $context, RevisionOperation::Create, $correlationId, $costCenter);
            $this->auditRecorder->record(
                eventType: 'cost-center.created',
                correlationId: $correlationId,
                properties: new AuditProperties(['name' => $costCenter->name, 'parent_id' => $parentId, 'active' => true]),
                actor: $persistedActor,
                tenantId: $context->tenantId,
                subject: $costCenter,
            );

            return $costCenter;
        });
    }
}
