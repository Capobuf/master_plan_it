<?php

namespace App\Domain\MasterData\Actions\Concerns;

use App\Domain\Revisions\Actions\BeginRevisionBatch;
use App\Domain\Revisions\Actions\LinkVersionToRevisionBatch;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Models\CostCenter;
use App\Models\RevisionBatch;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Version;
use App\Policies\CostCenterPolicy;
use App\Support\Authorization\PlatformAdministrator;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

trait ManagesCostCenterMutation
{
    private function policy(TenantContext $context): CostCenterPolicy
    {
        return new CostCenterPolicy(
            $context,
            app(PermissionRegistrar::class),
            app(PlatformAdministrator::class),
        );
    }

    private function activeContextTenant(TenantContext $context): Tenant
    {
        $key = $context->tenant->getKey();
        $originalKey = $context->tenant->getRawOriginal($context->tenant->getKeyName());

        if (! $context->tenant->exists
            || $key === null
            || $originalKey === null
            || $key !== $originalKey
            || (int) $originalKey !== $context->tenantId) {
            throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
        }

        $tenant = Tenant::query()->whereKey($originalKey)->first();
        if ($tenant === null) {
            throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
        }

        if ($tenant->state !== TenantState::Active) {
            throw new AuthorizationException('TENANT_INACTIVE');
        }

        return $tenant;
    }

    private function persistedActiveActor(User $actor): User
    {
        $keyName = $actor->getKeyName();
        $key = $actor->getKey();
        $originalKey = $actor->getRawOriginal($keyName);

        if (! $actor->exists || $key === null || $key !== $originalKey) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        $persistedActor = User::query()
            ->whereKey($originalKey)
            ->where('is_active', true)
            ->first();

        if ($persistedActor === null) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        return $persistedActor;
    }

    /** @return Collection<int, CostCenter> */
    private function lockTenantCostCenters(Tenant $tenant): Collection
    {
        return CostCenter::query()
            ->where('tenant_id', $tenant->getKey())
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (CostCenter $costCenter): int => (int) $costCenter->getKey());
    }

    /** @param Collection<int, CostCenter> $costCenters */
    private function lockedTarget(CostCenter $target, Collection $costCenters): CostCenter
    {
        $keyName = $target->getKeyName();
        $key = $target->getKey();
        $originalKey = $target->getRawOriginal($keyName);

        if (! $target->exists || $key === null || $key !== $originalKey) {
            throw new DomainException('STALE_VERSION');
        }

        $costCenter = $costCenters->get((int) $originalKey);

        if (! $costCenter instanceof CostCenter) {
            throw new DomainException('STALE_VERSION');
        }

        return $costCenter;
    }

    /** @param Collection<int, CostCenter> $costCenters */
    private function parentId(?CostCenter $parent, Collection $costCenters): ?int
    {
        if ($parent === null) {
            return null;
        }

        $keyName = $parent->getKeyName();
        $key = $parent->getKey();
        $originalKey = $parent->getRawOriginal($keyName);

        if (! $parent->exists || $key === null || $key !== $originalKey) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        if (! $costCenters->get((int) $originalKey) instanceof CostCenter) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        return (int) $originalKey;
    }

    /** @param Collection<int, CostCenter> $costCenters */
    private function assertValidHierarchy(Collection $costCenters, ?int $targetId, ?int $parentId): void
    {
        $depth = 1;
        $visited = [];
        $nextParentId = $parentId;

        while ($nextParentId !== null) {
            if ($nextParentId === $targetId || isset($visited[$nextParentId])) {
                throw new DomainException('COST_CENTER_CYCLE');
            }

            $visited[$nextParentId] = true;
            $parent = $costCenters->get($nextParentId);
            if (! $parent instanceof CostCenter) {
                throw new DomainException('TENANT_RELATION_MISMATCH');
            }

            $depth++;
            if ($depth > 3) {
                throw new DomainException('COST_CENTER_DEPTH_EXCEEDED');
            }

            $nextParentId = $parent->parent_id;
        }

        if ($targetId !== null && $depth + $this->deepestDescendantDistance($targetId, $costCenters) > 3) {
            throw new DomainException('COST_CENTER_DEPTH_EXCEEDED');
        }
    }

    /** @param Collection<int, CostCenter> $costCenters */
    private function deepestDescendantDistance(int $targetId, Collection $costCenters): int
    {
        $deepest = 0;
        $frontier = [$targetId => 0];
        $visited = [$targetId => true];

        while ($frontier !== []) {
            $next = [];
            foreach ($frontier as $parentId => $distance) {
                foreach ($costCenters as $costCenter) {
                    if ((int) $costCenter->parent_id !== $parentId) {
                        continue;
                    }

                    $childId = (int) $costCenter->getKey();
                    if (isset($visited[$childId])) {
                        throw new DomainException('COST_CENTER_CYCLE');
                    }

                    $visited[$childId] = true;
                    $childDistance = $distance + 1;
                    $deepest = max($deepest, $childDistance);
                    $next[$childId] = $childDistance;
                }
            }
            $frontier = $next;
        }

        return $deepest;
    }

    /** @param Collection<int, CostCenter> $costCenters */
    private function assertNoActiveDescendants(CostCenter $target, Collection $costCenters): void
    {
        foreach ($this->descendantsOf((int) $target->getKey(), $costCenters) as $descendant) {
            if ($descendant->active) {
                throw new DomainException('ACTIVE_DESCENDANT_EXISTS');
            }
        }
    }

    /** @param Collection<int, CostCenter> $costCenters */
    private function assertNoDescendants(CostCenter $target, Collection $costCenters): void
    {
        if ($this->descendantsOf((int) $target->getKey(), $costCenters) !== []) {
            throw new DomainException('REFERENCED_RECORD_DELETE_DENIED');
        }
    }

    /** @param Collection<int, CostCenter> $costCenters
     * @return list<CostCenter>
     */
    private function descendantsOf(int $targetId, Collection $costCenters): array
    {
        $descendants = [];
        $pendingParentIds = [$targetId];

        while ($pendingParentIds !== []) {
            $parentIds = $pendingParentIds;
            $pendingParentIds = [];

            foreach ($costCenters as $costCenter) {
                if ($costCenter->parent_id === null || ! in_array((int) $costCenter->parent_id, $parentIds, true)) {
                    continue;
                }

                $descendants[] = $costCenter;
                $pendingParentIds[] = (int) $costCenter->getKey();
            }
        }

        return $descendants;
    }

    private function validatedName(string $name): string
    {
        if (trim($name) === '' || mb_strlen($name) > 255) {
            throw ValidationException::withMessages([
                'name' => 'The cost center name must be between 1 and 255 characters.',
            ]);
        }

        return $name;
    }

    private function assertExpectedVersion(CostCenter $costCenter, int $expectedLockVersion): void
    {
        if ($costCenter->lock_version !== $expectedLockVersion) {
            throw new DomainException('STALE_VERSION');
        }
    }

    private function recordRevision(
        User $actor,
        TenantContext $context,
        RevisionOperation $operation,
        string $correlationId,
        CostCenter $costCenter,
        ?int $restoredFromVersionId = null,
    ): void {
        $batch = $this->beginRevisionBatch(
            $actor,
            $context,
            $operation,
            $correlationId,
            $costCenter,
            $restoredFromVersionId,
        );
        $version = $costCenter->versions()->orderByDesc('id')->firstOrFail();

        if (! $version instanceof Version) {
            throw new DomainException('REVISION_RESTORE_INVALID');
        }

        $this->linkRevisionSnapshot($batch, $version);
    }

    private function beginRevisionBatch(
        User $actor,
        TenantContext $context,
        RevisionOperation $operation,
        string $correlationId,
        CostCenter $costCenter,
        ?int $restoredFromVersionId = null,
    ): RevisionBatch {
        return app(BeginRevisionBatch::class)->execute(
            $actor,
            $context,
            $operation,
            null,
            $correlationId,
            $costCenter,
            $restoredFromVersionId,
        );
    }

    private function linkRevisionSnapshot(RevisionBatch $batch, Version $version): void
    {
        app(LinkVersionToRevisionBatch::class)->execute($batch, $version, 1);
    }
}
