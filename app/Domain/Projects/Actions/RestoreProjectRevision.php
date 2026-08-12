<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Projects\Actions\Concerns\ManagesProjects;
use App\Domain\Projects\Data\SaveProjectData;
use App\Domain\Projects\Enums\ProjectStage;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Revisions\Queries\OperationalRevisionQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Project;
use App\Models\RevisionBatch;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class RestoreProjectRevision
{
    use ManagesProjects;

    public function execute(User $actor, TenantContext $context, Project $target, RevisionBatch $source, int $expectedLockVersion, string $correlationId): Project
    {
        $this->projectPolicy($context)->restoreRevision($actor, $target)->authorize();
        [$actor, $tenant] = $this->persistedProjectContext($actor, $context);

        return DB::transaction(function () use ($actor, $context, $correlationId, $expectedLockVersion, $source, $target, $tenant): Project {
            $this->lockProjectEconomicYears((int) $tenant->getKey(), [(int) $target->getKey()]);
            $project = Project::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->find($target->getKey());
            if (! $project instanceof Project || $project->lock_version !== $expectedLockVersion) {
                throw new DomainException('STALE_VERSION');
            }
            $logical = app(OperationalRevisionQuery::class);
            $batch = $logical->findVisibleBatch($context, $project, (int) $source->getKey());
            $state = $logical->snapshot($context, $project, $batch);
            $contents = $state[$project->getMorphClass()][(int) $project->getKey()] ?? null;
            if (! is_array($contents)) {
                throw new DomainException('REVISION_RESTORE_INVALID');
            }
            $stageValue = $contents['stage'] ?? null;
            $stage = $stageValue instanceof ProjectStage ? $stageValue : ProjectStage::tryFrom((string) $stageValue);
            if ($stage === null) {
                throw new DomainException('REVISION_RESTORE_INVALID');
            }
            $data = new SaveProjectData(
                (string) ($contents['title'] ?? ''),
                (int) ($contents['cost_center_id'] ?? 0),
                $stage,
                isset($contents['deferred_target_planning_year_id']) ? (int) $contents['deferred_target_planning_year_id'] : null,
                $expectedLockVersion,
            );
            $project->fill($this->validatedProjectAttributes($tenant, $data));
            if (! $project->isDirty()) {
                throw new DomainException('REVISION_RESTORE_INVALID');
            }
            $project->forceFill(['lock_version' => $project->lock_version + 1])->save();
            $this->projectRevision(
                $actor,
                $context,
                RevisionOperation::Restore,
                $correlationId,
                $project,
                restoredFromBatchId: (int) $batch->getKey(),
            );
            $this->projectAudit('project.restored', $correlationId, $actor, $tenant, $project, ['restored_from_batch_id' => (int) $batch->getKey()]);

            return $project->fresh(['costCenter', 'deferredTargetPlanningYear']);
        });
    }
}
