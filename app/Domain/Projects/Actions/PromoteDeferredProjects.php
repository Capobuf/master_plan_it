<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Projects\Actions\Concerns\ManagesProjects;
use App\Domain\Projects\Enums\ProjectStage;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PromoteDeferredProjects
{
    use ManagesProjects;

    public function execute(User $actor, TenantContext $context): int
    {
        $this->projectPolicy($context)->updateAny($actor)->authorize();
        [$actor, $tenant] = $this->persistedProjectContext($actor, $context);
        $currentYear = (int) now((string) $tenant->timezone)->year;

        return DB::transaction(function () use ($actor, $context, $currentYear, $tenant): int {
            $projects = Project::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('stage', ProjectStage::Deferred->value)
                ->whereHas('deferredTargetPlanningYear', fn ($query) => $query
                    ->where('tenant_id', $tenant->getKey())
                    ->where('year_label', '<=', $currentYear))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($projects as $project) {
                $project->fill([
                    'stage' => ProjectStage::Proposed,
                    'deferred_target_planning_year_id' => null,
                    'lock_version' => $project->lock_version + 1,
                ])->save();
                $correlationId = (string) str()->uuid();
                $this->projectRevision($actor, $context, RevisionOperation::Update, $correlationId, $project, 'Deferred target year reached.');
                $this->projectAudit('project.deferred-promoted', $correlationId, $actor, $tenant, $project, ['target_year_reached' => $currentYear]);
            }

            return $projects->count();
        });
    }
}
