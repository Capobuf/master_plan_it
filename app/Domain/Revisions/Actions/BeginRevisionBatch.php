<?php

namespace App\Domain\Revisions\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\RevisionBatch;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Version as ApplicationVersion;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Overtrue\LaravelVersionable\Versionable;

final class BeginRevisionBatch
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function execute(
        User $actor,
        TenantContext $context,
        RevisionOperation $operation,
        ?string $reason,
        string $correlationId,
        Model $root,
        ?int $restoredFromVersionId,
    ): RevisionBatch {
        [$persistedActor, $tenant] = $this->verifyPersistedContext($actor, $context);
        $persistedRoot = $this->persistedSameTenantRoot($root, $tenant);
        $restoreSource = $this->persistedRestoreSource($operation, $restoredFromVersionId, $persistedRoot);
        $occurredAt = CarbonImmutable::now('UTC');

        return DB::transaction(function () use ($correlationId, $occurredAt, $operation, $persistedActor, $persistedRoot, $reason, $restoreSource, $tenant): RevisionBatch {
            $batch = RevisionBatch::query()->create([
                'tenant_id' => $tenant->getKey(),
                'actor_user_id' => $persistedActor->getKey(),
                'root_subject_type' => $persistedRoot->getMorphClass(),
                'root_subject_id' => $persistedRoot->getKey(),
                'operation' => $operation,
                'reason' => $reason,
                'correlation_id' => $correlationId,
                'restored_from_batch_id' => null,
                'restored_from_version_id' => $restoreSource?->getKey(),
                'occurred_at' => $occurredAt,
            ]);

            $this->auditRecorder->record(
                eventType: 'revision.batch.begin',
                correlationId: $correlationId,
                properties: new AuditProperties(['operation' => $operation->value]),
                actor: $persistedActor,
                tenantId: (int) $tenant->getKey(),
                subject: $batch,
                occurredAt: $occurredAt,
            );

            return $batch;
        });
    }

    /** @return array{User, Tenant} */
    private function verifyPersistedContext(User $actor, TenantContext $context): array
    {
        $persistedActor = $this->persistedActor($actor);

        if ($persistedActor === null || $this->persistedActor($context->actor)?->getKey() !== $persistedActor->getKey()) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        $tenant = $this->persistedContextTenant($context);

        if ($tenant === null) {
            throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
        }

        return [$persistedActor, $tenant];
    }

    private function persistedActor(User $user): ?User
    {
        $keyName = $user->getKeyName();
        $key = $user->getKey();
        $originalKey = $user->getRawOriginal($keyName);

        if (! $user->exists || $key === null || $key !== $originalKey) {
            return null;
        }

        $persistedUser = User::query()->whereKey($originalKey)->first();

        if ($persistedUser === null || ! $persistedUser->is_active) {
            return null;
        }

        return $persistedUser;
    }

    private function persistedContextTenant(TenantContext $context): ?Tenant
    {
        $keyName = $context->tenant->getKeyName();
        $key = $context->tenant->getKey();
        $originalKey = $context->tenant->getRawOriginal($keyName);

        if (! $context->tenant->exists || $key === null || $key !== $originalKey || (int) $key !== $context->tenantId) {
            return null;
        }

        return Tenant::query()->whereKey($originalKey)->first();
    }

    private function persistedSameTenantRoot(Model $root, Tenant $tenant): Model
    {
        $key = $root->getKey();
        $originalKey = $root->getRawOriginal($root->getKeyName());

        if (! $root->exists || $key === null || $originalKey === null || $key !== $originalKey) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        $persistedRoot = $root->newQuery()->whereKey($originalKey)->first();
        $persistedTenantId = $persistedRoot?->getAttribute('tenant_id');

        if (! $persistedRoot instanceof Model
            || ! in_array(Versionable::class, class_uses_recursive($persistedRoot), true)
            || $persistedTenantId === null
            || (int) $persistedTenantId !== (int) $tenant->getKey()) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        return $persistedRoot;
    }

    private function persistedRestoreSource(
        RevisionOperation $operation,
        ?int $restoredFromVersionId,
        Model $persistedRoot,
    ): ?ApplicationVersion {
        if ($operation !== RevisionOperation::Restore) {
            if ($restoredFromVersionId !== null) {
                throw new DomainException('TENANT_RELATION_MISMATCH');
            }

            return null;
        }

        if ($restoredFromVersionId === null) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        $source = ApplicationVersion::query()->whereKey($restoredFromVersionId)->first();

        if (! $source instanceof ApplicationVersion
            || $source->getAttribute('versionable_type') !== $persistedRoot->getMorphClass()
            || (int) $source->getAttribute('versionable_id') !== (int) $persistedRoot->getKey()) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        return $source;
    }
}
