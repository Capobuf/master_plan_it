<?php

namespace App\Domain\Projects\Queries;

use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Models\Project;
use App\Models\User;
use App\Policies\ProjectPolicy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class ProjectDetailQuery
{
    /** @return array{project: Project, expenses: list<array<string, mixed>>, revision_activity: mixed} */
    public function find(User $actor, TenantContext $context, int $id): array
    {
        $policy = app(ProjectPolicy::class);
        $policy->viewAny($actor)->authorize();
        $project = TenantOwnedRecordQuery::forTenant($context, Project::class)
            ->with(['costCenter', 'deferredTargetPlanningYear'])
            ->withCount('expenses')
            ->whereKey($id)
            ->first();
        if (! $project instanceof Project) {
            throw (new ModelNotFoundException)->setModel(Project::class, [$id]);
        }
        $policy->view($actor, $project)->authorize();

        $expenses = DB::table('expenses')
            ->join('planning_years', fn ($join) => $join
                ->on('planning_years.id', '=', 'expenses.planning_year_id')
                ->on('planning_years.tenant_id', '=', 'expenses.tenant_id'))
            ->where('expenses.tenant_id', $context->tenantId)
            ->where('expenses.project_id', $project->getKey())
            ->whereNull('expenses.deleted_at')
            ->orderByRaw('LOWER(expenses.title)')
            ->orderBy('expenses.id')
            ->get(['expenses.id', 'expenses.title', 'expenses.kind', 'expenses.planning_year_id', 'planning_years.year_label as planning_year_label'])
            ->map(static fn (object $expense): array => [
                'id' => (int) $expense->id,
                'title' => (string) $expense->title,
                'kind' => (string) $expense->kind,
                'planning_year_id' => (int) $expense->planning_year_id,
                'planning_year_label' => (int) $expense->planning_year_label,
            ])->all();

        return [
            'project' => $project,
            'expenses' => $expenses,
            'revision_activity' => $policy->viewRevisions($actor, $project)->allowed()
                ? app(ProjectRevisionQuery::class)->history($actor, $context, $project)
                : collect(),
        ];
    }
}
