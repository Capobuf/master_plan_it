<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Projects\Actions\Concerns\ManagesProjects;
use App\Domain\Projects\Data\SaveProjectData;
use App\Domain\Projects\Enums\ProjectStage;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Project;
use App\Models\User;
use App\Models\Version;
use DomainException;
use Illuminate\Support\Facades\DB;

final class RestoreProjectRevision
{
    use ManagesProjects;

    public function execute(User $actor, TenantContext $context, Project $target, Version $source, int $expectedLockVersion, string $correlationId): Project
    {
        $this->projectPolicy($context)->restoreRevision($actor, $target)->authorize();
        [$actor, $tenant] = $this->persistedProjectContext($actor, $context);

        return DB::transaction(function () use ($actor, $context, $correlationId, $expectedLockVersion, $source, $target, $tenant): Project {
            $project = Project::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->find($target->getKey());
            if (! $project instanceof Project || $project->lock_version !== $expectedLockVersion) {
                throw new DomainException('STALE_VERSION');
            }
            $version = Version::query()
                ->whereKey($source->getRawOriginal($source->getKeyName()))
                ->where('versionable_type', $project->getMorphClass())
                ->where('versionable_id', $project->getKey())
                ->first();
            if (! $version instanceof Version) {
                throw new DomainException('TENANT_RELATION_MISMATCH');
            }
            $contents = $version->contents;
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
            $project->fill([
                ...$this->validatedProjectAttributes($tenant, $data),
                'lock_version' => $project->lock_version + 1,
            ])->save();
            $this->projectRevision($actor, $context, RevisionOperation::Restore, $correlationId, $project, null, (int) $version->getKey());
            $this->projectAudit('project.restored', $correlationId, $actor, $tenant, $project, ['restored_from_version_id' => (int) $version->getKey()]);

            return $project->fresh(['costCenter', 'deferredTargetPlanningYear']);
        });
    }
}
