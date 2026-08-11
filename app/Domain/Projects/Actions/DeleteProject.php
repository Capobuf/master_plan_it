<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Attachments\Actions\PurgeAttachments;
use App\Domain\Projects\Actions\Concerns\ManagesProjects;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Expense;
use App\Models\Project;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class DeleteProject
{
    use ManagesProjects;

    public function execute(User $actor, TenantContext $context, Project $target, int $expectedLockVersion, ?string $reason, string $correlationId): void
    {
        $this->projectPolicy($context)->delete($actor, $target)->authorize();
        [$actor, $tenant] = $this->persistedProjectContext($actor, $context);

        DB::transaction(function () use ($actor, $context, $correlationId, $expectedLockVersion, $reason, $target, $tenant): void {
            $project = Project::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->find($target->getKey());
            if (! $project instanceof Project || $project->lock_version !== $expectedLockVersion) {
                throw new DomainException('STALE_VERSION');
            }
            $linked = Expense::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('project_id', $project->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->first();
            if ($linked instanceof Expense) {
                throw new DomainException('PROJECT_HAS_LINKED_EXPENSES');
            }
            $normalizedReason = $this->projectDeletionReason($tenant, $reason);
            $deletedAt = now('UTC');
            $project->fill([
                'deleted_by_user_id' => $actor->getKey(),
                'deleted_by_at' => $deletedAt,
                'deletion_reason' => $normalizedReason,
                'lock_version' => $project->lock_version + 1,
            ])->save();
            $this->projectRevision($actor, $context, RevisionOperation::Delete, $correlationId, $project, $normalizedReason);
            $this->projectAudit('project.deleted', $correlationId, $actor, $tenant, $project);
            $project->delete();
            app(PurgeAttachments::class)->forParent($actor, $context, $project, $correlationId);
        });
    }
}
