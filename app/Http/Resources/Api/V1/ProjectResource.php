<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Projects\Enums\ProjectStage;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Models\RevisionBatch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProjectResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $payload = is_array($this->resource) ? $this->resource : ['project' => $this->resource];
        $project = $payload['project'];
        $stage = $project->stage;

        return [
            'id' => (int) $project->getKey(),
            'title' => (string) $project->title,
            'stage' => $stage instanceof ProjectStage ? $stage->value : (string) $project->getRawOriginal('stage'),
            'cost_center_id' => (int) $project->cost_center_id,
            'cost_center' => $project->relationLoaded('costCenter') && $project->costCenter !== null ? [
                'id' => (int) $project->costCenter->getKey(),
                'name' => (string) $project->costCenter->name,
            ] : null,
            'deferred_target_planning_year_id' => $project->deferred_target_planning_year_id === null
                ? null : (int) $project->deferred_target_planning_year_id,
            'deferred_target_planning_year' => $project->relationLoaded('deferredTargetPlanningYear')
                && $project->deferredTargetPlanningYear !== null ? [
                    'id' => (int) $project->deferredTargetPlanningYear->getKey(),
                    'year_label' => (int) $project->deferredTargetPlanningYear->year_label,
                    'active' => (bool) $project->deferredTargetPlanningYear->active,
                ] : null,
            'expense_count' => (int) ($project->getAttribute('expenses_count') ?? count($payload['expenses'] ?? [])),
            'expenses' => $payload['expenses'] ?? [],
            'lock_version' => (int) $project->lock_version,
            'revision_activity' => ($payload['revision_activity'] ?? null) instanceof Collection
                ? $this->revisionActivity($payload['revision_activity'])
                : [],
        ];
    }

    /**
     * @param  Collection<int, RevisionBatch>  $activity
     * @return list<array<string, mixed>>
     */
    private function revisionActivity(Collection $activity): array
    {
        return $activity->map(static function (RevisionBatch $batch): array {
            $operation = $batch->operation;

            return [
                'id' => (int) $batch->getKey(),
                'operation' => $operation instanceof RevisionOperation ? $operation->value : (string) $batch->getRawOriginal('operation'),
                'actor' => $batch->actor?->name,
                'timestamp' => $batch->occurred_at?->toIso8601String(),
                'summary' => $batch->reason,
                'restored_from_revision_id' => $batch->restored_from_version_id === null ? null : (int) $batch->restored_from_version_id,
            ];
        })->values()->all();
    }
}
