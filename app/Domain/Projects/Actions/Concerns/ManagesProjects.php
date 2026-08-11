<?php

namespace App\Domain\Projects\Actions\Concerns;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\Projects\Data\SaveProjectData;
use App\Domain\Projects\Enums\ProjectStage;
use App\Domain\Revisions\Actions\BeginRevisionBatch;
use App\Domain\Revisions\Actions\LinkVersionToRevisionBatch;
use App\Domain\Revisions\Data\RevisionActorKind;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Models\CostCenter;
use App\Models\PlanningYear;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Version;
use App\Policies\ProjectPolicy;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

trait ManagesProjects
{
    private function projectPolicy(TenantContext $context): ProjectPolicy
    {
        return new ProjectPolicy($context, app(PermissionRegistrar::class), app(PlatformAdministrator::class));
    }

    /** @return array{User,Tenant} */
    private function persistedProjectContext(User $actor, TenantContext $context): array
    {
        $actorKey = $actor->getKey();
        $tenantKey = $context->tenant->getKey();
        if (! $actor->exists || $actorKey === null || $actorKey !== $actor->getRawOriginal($actor->getKeyName())
            || ! $context->actor->exists || $context->actor->getKey() !== $actorKey
            || $context->actor->getRawOriginal($context->actor->getKeyName()) !== $actorKey
            || ! $context->tenant->exists || $tenantKey === null
            || $tenantKey !== $context->tenant->getRawOriginal($context->tenant->getKeyName())
            || (int) $tenantKey !== $context->tenantId) {
            throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
        }

        $persistedActor = User::query()->whereKey($actorKey)->where('is_active', true)->first();
        $tenant = Tenant::query()->whereKey($context->tenantId)->first();
        if (! $persistedActor instanceof User || ! $tenant instanceof Tenant) {
            throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
        }
        if ($tenant->state !== TenantState::Active) {
            throw new AuthorizationException('TENANT_INACTIVE');
        }
        if ($persistedActor->tenant_id !== null && (int) $persistedActor->tenant_id !== (int) $tenant->getKey()) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        return [$persistedActor, $tenant];
    }

    /** @return array<string, mixed> */
    private function validatedProjectAttributes(Tenant $tenant, SaveProjectData $data, ?Project $current = null): array
    {
        $title = trim($data->title);
        if ($title === '' || mb_strlen($title) > 255) {
            $this->projectFail('title', 'The project title must be between 1 and 255 characters.');
        }

        $center = CostCenter::query()->where('tenant_id', $tenant->getKey())->whereKey($data->costCenterId)->first();
        if (! $center instanceof CostCenter || (! $center->active && (int) $current?->cost_center_id !== $data->costCenterId)) {
            $this->projectFail('cost_center_id', 'The selected cost center is invalid.');
        }

        $targetId = null;
        if ($data->stage === ProjectStage::Deferred) {
            if ($data->deferredTargetPlanningYearId === null) {
                $this->projectFail('deferred_target_planning_year_id', 'A target planning year is required for a deferred project.');
            }
            $target = PlanningYear::query()
                ->where('tenant_id', $tenant->getKey())
                ->whereKey($data->deferredTargetPlanningYearId)
                ->first();
            if (! $target instanceof PlanningYear
                || (! $target->active && (int) $current?->deferred_target_planning_year_id !== $data->deferredTargetPlanningYearId)) {
                $this->projectFail('deferred_target_planning_year_id', 'The target planning year is invalid.');
            }
            $targetId = (int) $target->getKey();
        }

        return [
            'cost_center_id' => $data->costCenterId,
            'title' => $title,
            'stage' => $data->stage,
            'deferred_target_planning_year_id' => $targetId,
        ];
    }

    private function projectRevision(
        User $actor,
        TenantContext $context,
        RevisionOperation $operation,
        string $correlationId,
        Project $project,
        ?string $reason = null,
        ?int $restoredFromVersionId = null,
        ?int $restoredFromBatchId = null,
        RevisionActorKind $actorKind = RevisionActorKind::Human,
    ): void {
        $batch = app(BeginRevisionBatch::class)->execute(
            $actor, $context, $operation, $reason, $correlationId, $project,
            $restoredFromVersionId, $restoredFromBatchId, $actorKind,
        );
        $version = $project->versions()->orderByDesc('id')->first();
        if ($version instanceof Version) {
            app(LinkVersionToRevisionBatch::class)->execute($batch, $version, 1);
        }
    }

    /** @param array<string, mixed> $properties */
    private function projectAudit(string $event, string $correlationId, User $actor, Tenant $tenant, Project $project, array $properties = []): void
    {
        app(AuditRecorder::class)->record(
            $event, $correlationId, new AuditProperties($properties), $actor, (int) $tenant->getKey(), $project,
        );
    }

    private function projectDeletionReason(Tenant $tenant, ?string $reason): ?string
    {
        $normalized = trim((string) $reason);
        if (mb_strlen($normalized) > 500) {
            $this->projectFail('deletion_reason', 'The deletion reason may not exceed 500 characters.');
        }
        if ($tenant->deletion_reason_required && $normalized === '') {
            $this->projectFail('deletion_reason', 'A deletion reason is required.');
        }

        return $normalized === '' ? null : $normalized;
    }

    private function projectFail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
