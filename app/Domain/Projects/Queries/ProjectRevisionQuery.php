<?php

namespace App\Domain\Projects\Queries;

use App\Domain\Projects\Enums\ProjectStage;
use App\Domain\Revisions\Queries\RevisionHistoryQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Models\Project;
use App\Models\RevisionBatch;
use App\Models\RevisionBatchItem;
use App\Models\User;
use App\Models\Version;
use App\Policies\ProjectPolicy;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Spatie\Permission\PermissionRegistrar;

final class ProjectRevisionQuery
{
    /** @return Collection<int, RevisionBatch> */
    public function history(User $actor, TenantContext $context, Project $project): Collection
    {
        $this->policy($context)->viewRevisions($actor, $project)->authorize();

        return app(RevisionHistoryQuery::class)->forSubject($context, $project)->load(['items.version', 'actor']);
    }

    public function sourceVersion(User $actor, TenantContext $context, Project $project, int $revisionId): Version
    {
        $this->policy($context)->viewRevisions($actor, $project)->authorize();

        return $this->resolveSourceVersion($context, $project, $revisionId);
    }

    public function sourceVersionForRestore(User $actor, TenantContext $context, Project $project, int $revisionId): Version
    {
        $this->policy($context)->restoreRevision($actor, $project)->authorize();

        return $this->resolveSourceVersion($context, $project, $revisionId);
    }

    private function resolveSourceVersion(TenantContext $context, Project $project, int $revisionId): Version
    {
        $batch = $this->batch($context, $project, $revisionId);
        $item = $batch->items->first(fn (RevisionBatchItem $candidate): bool => $candidate->versionable_type === $project->getMorphClass()
            && (int) $candidate->versionable_id === (int) $project->getKey());
        if (! $item instanceof RevisionBatchItem || ! $item->version instanceof Version) {
            throw (new ModelNotFoundException)->setModel(RevisionBatch::class, [$revisionId]);
        }

        return $item->version;
    }

    /** @return array<string, mixed> */
    public function comparison(User $actor, TenantContext $context, Project $project, int $revisionId): array
    {
        $version = $this->sourceVersion($actor, $context, $project, $revisionId);
        $batch = $this->batch($context, $project, $revisionId);

        return [
            'batch' => $batch,
            'version' => $version,
            'snapshot' => $this->snapshot($version->contents),
            'current' => [
                'title' => (string) $project->title,
                'stage' => $project->stage instanceof ProjectStage ? $project->stage->value : (string) $project->getRawOriginal('stage'),
                'cost_center_id' => (int) $project->cost_center_id,
                'deferred_target_planning_year_id' => $project->deferred_target_planning_year_id === null ? null : (int) $project->deferred_target_planning_year_id,
                'lock_version' => (int) $project->lock_version,
            ],
        ];
    }

    private function batch(TenantContext $context, Project $project, int $revisionId): RevisionBatch
    {
        $batch = TenantOwnedRecordQuery::forTenant($context, RevisionBatch::class)
            ->whereKey($revisionId)
            ->where('root_subject_type', $project->getMorphClass())
            ->where('root_subject_id', $project->getKey())
            ->with(['items.version', 'actor'])
            ->first();
        if (! $batch instanceof RevisionBatch) {
            throw (new ModelNotFoundException)->setModel(RevisionBatch::class, [$revisionId]);
        }

        return $batch;
    }

    /**
     * @param  array<string, mixed>  $contents
     * @return array<string, mixed>
     */
    private function snapshot(array $contents): array
    {
        $stage = $contents['stage'] ?? null;

        return [
            'title' => (string) ($contents['title'] ?? ''),
            'stage' => $stage instanceof ProjectStage ? $stage->value : (string) $stage,
            'cost_center_id' => isset($contents['cost_center_id']) ? (int) $contents['cost_center_id'] : 0,
            'deferred_target_planning_year_id' => isset($contents['deferred_target_planning_year_id'])
                ? (int) $contents['deferred_target_planning_year_id'] : null,
        ];
    }

    private function policy(TenantContext $context): ProjectPolicy
    {
        return new ProjectPolicy(
            $context,
            app(PermissionRegistrar::class),
            app(PlatformAdministrator::class),
        );
    }
}
