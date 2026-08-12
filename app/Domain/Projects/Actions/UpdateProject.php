<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Projects\Actions\Concerns\ManagesProjects;
use App\Domain\Projects\Data\SaveProjectData;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Project;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class UpdateProject
{
    use ManagesProjects;

    public function execute(User $actor, TenantContext $context, Project $target, SaveProjectData $data, string $correlationId): Project
    {
        $this->projectPolicy($context)->update($actor, $target)->authorize();
        [$actor, $tenant] = $this->persistedProjectContext($actor, $context);

        return DB::transaction(function () use ($actor, $context, $correlationId, $data, $target, $tenant): Project {
            $this->lockProjectEconomicYears((int) $tenant->getKey(), [(int) $target->getKey()]);
            $project = Project::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->find($target->getKey());
            if (! $project instanceof Project || $data->expectedLockVersion === null || $project->lock_version !== $data->expectedLockVersion) {
                throw new DomainException('STALE_VERSION');
            }
            $project->fill($this->validatedProjectAttributes($tenant, $data, $project));
            if (! $project->isDirty()) {
                return $project->fresh(['costCenter', 'deferredTargetPlanningYear']);
            }
            $project->forceFill(['lock_version' => $project->lock_version + 1])->save();
            $this->projectRevision($actor, $context, RevisionOperation::Update, $correlationId, $project);
            $this->projectAudit('project.updated', $correlationId, $actor, $tenant, $project);

            return $project->fresh(['costCenter', 'deferredTargetPlanningYear']);
        });
    }
}
