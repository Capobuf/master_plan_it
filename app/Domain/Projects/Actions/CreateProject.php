<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Projects\Actions\Concerns\ManagesProjects;
use App\Domain\Projects\Data\SaveProjectData;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateProject
{
    use ManagesProjects;

    public function execute(User $actor, TenantContext $context, SaveProjectData $data, string $correlationId): Project
    {
        $this->projectPolicy($context)->create($actor)->authorize();
        [$actor, $tenant] = $this->persistedProjectContext($actor, $context);

        return DB::transaction(function () use ($actor, $context, $correlationId, $data, $tenant): Project {
            $this->lockProjectEconomicYears((int) $tenant->getKey(), []);
            $project = new Project;
            $project->fill($this->validatedProjectAttributes($tenant, $data));
            $project->tenant_id = $tenant->getKey();
            $project->save();
            $this->projectRevision($actor, $context, RevisionOperation::Create, $correlationId, $project);
            $this->projectAudit('project.created', $correlationId, $actor, $tenant, $project);

            return $project->fresh(['costCenter', 'deferredTargetPlanningYear']);
        });
    }
}
