<?php

namespace App\Domain\Projects\Queries;

use App\Domain\Projects\Enums\ProjectStage;
use App\Domain\Revisions\Data\RevisionDiff;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Revisions\Queries\OperationalRevisionQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Models\CostCenter;
use App\Models\PlanningYear;
use App\Models\Project;
use App\Models\RevisionBatch;
use App\Models\User;
use App\Policies\ProjectPolicy;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;

final class ProjectRevisionQuery
{
    /** @return Collection<int, array<string, mixed>> */
    public function history(User $actor, TenantContext $context, Project $project): Collection
    {
        $policy = $this->policy($context);
        $policy->viewRevisions($actor, $project)->authorize();
        $canRestore = $policy->restoreRevision($actor, $project)->allowed();

        return app(OperationalRevisionQuery::class)->visibleForRoot($context, $project)
            ->map(fn (RevisionBatch $batch): array => $this->metadata($batch, $canRestore));
    }

    public function sourceBatchForRestore(User $actor, TenantContext $context, Project $project, int $revisionId): RevisionBatch
    {
        $this->policy($context)->restoreRevision($actor, $project)->authorize();

        return app(OperationalRevisionQuery::class)->findVisibleBatch($context, $project, $revisionId);
    }

    /** @return array<string, mixed> */
    public function comparison(User $actor, TenantContext $context, Project $project, int $revisionId): array
    {
        $policy = $this->policy($context);
        $policy->viewRevisions($actor, $project)->authorize();
        $logical = app(OperationalRevisionQuery::class);
        $batch = $logical->findVisibleBatch($context, $project, $revisionId);
        $state = $logical->snapshot($context, $project, $batch);
        $snapshot = $state[$project->getMorphClass()][(int) $project->getKey()];
        $changes = $this->diff($context, $snapshot, $project);
        $canRestore = $changes !== [] && $policy->restoreRevision($actor, $project)->allowed();

        return [
            'revision' => $this->metadata($batch, $canRestore),
            'changes' => array_map(static fn (RevisionDiff $diff): array => $diff->toArray(), $changes),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return list<RevisionDiff>
     */
    private function diff(TenantContext $context, array $snapshot, Project $current): array
    {
        $stage = $snapshot['stage'] ?? null;
        $revision = [
            'title' => (string) ($snapshot['title'] ?? ''),
            'stage' => $stage instanceof ProjectStage ? $stage->value : (string) $stage,
            'cost_center' => $this->costCenterLabel($context, isset($snapshot['cost_center_id']) ? (int) $snapshot['cost_center_id'] : null),
            'deferred_year' => $this->yearLabel($context, isset($snapshot['deferred_target_planning_year_id']) ? (int) $snapshot['deferred_target_planning_year_id'] : null),
        ];
        $now = [
            'title' => (string) $current->title,
            'stage' => $current->stage instanceof ProjectStage ? $current->stage->value : (string) $current->getRawOriginal('stage'),
            'cost_center' => $this->costCenterLabel($context, (int) $current->cost_center_id),
            'deferred_year' => $this->yearLabel($context, $current->deferred_target_planning_year_id === null ? null : (int) $current->deferred_target_planning_year_id),
        ];
        $labels = ['title' => 'Titolo', 'stage' => 'Stato', 'cost_center' => 'Centro di costo', 'deferred_year' => 'Anno di destinazione'];
        $changes = [];
        foreach ($labels as $field => $label) {
            if ($revision[$field] !== $now[$field]) {
                $changes[] = new RevisionDiff('project', 'Progetto', $field, $label, $revision[$field], $now[$field]);
            }
        }

        return $changes;
    }

    /** @return array<string, mixed> */
    private function metadata(RevisionBatch $batch, bool $canRestore): array
    {
        $operation = $batch->operation instanceof RevisionOperation ? $batch->operation->value : (string) $batch->getRawOriginal('operation');

        return [
            'id' => (int) $batch->getKey(),
            'operation' => $operation,
            'actor' => [
                'kind' => $batch->visualActorKind()->value,
                'label' => $batch->visualActorLabel(),
            ],
            'timestamp' => $batch->occurred_at?->toIso8601String(),
            'summary' => $batch->reason ?? $this->operationLabel($operation),
            'changed_count' => $batch->items->count(),
            'changed_fields' => ['Dati progetto'],
            'can_compare' => true,
            'can_restore' => $canRestore,
        ];
    }

    private function costCenterLabel(TenantContext $context, ?int $id): ?string
    {
        if ($id === null) {
            return null;
        }

        return TenantOwnedRecordQuery::forTenant($context, CostCenter::class)->withTrashed()->whereKey($id)->value('name')
            ?? 'Riferimento non più disponibile';
    }

    private function yearLabel(TenantContext $context, ?int $id): ?string
    {
        if ($id === null) {
            return null;
        }

        $label = TenantOwnedRecordQuery::forTenant($context, PlanningYear::class)->whereKey($id)->value('year_label');

        return $label === null ? 'Riferimento non più disponibile' : (string) $label;
    }

    private function operationLabel(string $operation): string
    {
        return match ($operation) {
            'create' => 'Creazione progetto',
            'restore' => 'Ripristino progetto',
            'delete' => 'Eliminazione progetto',
            default => 'Aggiornamento progetto',
        };
    }

    private function policy(TenantContext $context): ProjectPolicy
    {
        return new ProjectPolicy($context, app(PermissionRegistrar::class), app(PlatformAdministrator::class));
    }
}
