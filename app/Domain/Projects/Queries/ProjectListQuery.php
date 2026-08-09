<?php

namespace App\Domain\Projects\Queries;

use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Models\Project;
use App\Models\User;
use App\Policies\ProjectPolicy;
use Illuminate\Pagination\LengthAwarePaginator;

final class ProjectListQuery
{
    /** @return LengthAwarePaginator<int, Project> */
    public function paginate(User $actor, TenantContext $context, int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        app(ProjectPolicy::class)->viewAny($actor)->authorize();

        return TenantOwnedRecordQuery::forTenant($context, Project::class)
            ->with(['costCenter:id,name', 'deferredTargetPlanningYear:id,year_label,active'])
            ->withCount('expenses')
            ->orderByRaw('LOWER(title)')
            ->orderBy('id')
            ->paginate(min(max($perPage, 1), 100), ['*'], 'page', max(1, $page));
    }
}
