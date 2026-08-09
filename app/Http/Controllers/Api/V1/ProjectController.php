<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Projects\Actions\CreateProject;
use App\Domain\Projects\Actions\DeleteProject;
use App\Domain\Projects\Actions\RestoreProjectRevision;
use App\Domain\Projects\Actions\UpdateProject;
use App\Domain\Projects\Data\SaveProjectData;
use App\Domain\Projects\Enums\ProjectStage;
use App\Domain\Projects\Queries\ProjectDetailQuery;
use App\Domain\Projects\Queries\ProjectListQuery;
use App\Domain\Projects\Queries\ProjectRevisionQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthorizeApplicationAbility;
use App\Http\Resources\Api\V1\ProjectResource;
use App\Http\Resources\Api\V1\ProjectRevisionResource;
use App\Models\Project;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ProjectController extends Controller
{
    public function __construct(private readonly AuthorizeApplicationAbility $authorizeAbility) {}

    public function index(Request $request, ProjectListQuery $query): AnonymousResourceCollection
    {
        $this->authorizeAbility($request, 'project.view');

        return ProjectResource::collection($query->paginate(
            $this->actor($request), $this->tenantContext($request),
            max(1, $request->integer('page', 1)), min(max(1, $request->integer('per_page', 15)), 100),
        ));
    }

    public function show(Request $request, int $project, ProjectDetailQuery $query): ProjectResource
    {
        $this->authorizeAbility($request, 'project.view');

        return ProjectResource::make($query->find($this->actor($request), $this->tenantContext($request), $project));
    }

    public function store(Request $request, CreateProject $action): JsonResponse
    {
        $this->authorizeAbility($request, 'project.create');
        $context = $this->tenantContext($request);
        $created = $action->execute($this->actor($request), $context, $this->projectData($request, false), $this->correlationId($request));

        return ProjectResource::make($created)
            ->response($request)->setStatusCode(201);
    }

    public function update(Request $request, int $project, UpdateProject $action): ProjectResource
    {
        $this->authorizeAbility($request, 'project.update');
        $context = $this->tenantContext($request);
        $updated = $action->execute(
            $this->actor($request), $context, $this->project($context, $project),
            $this->projectData($request, true), $this->correlationId($request),
        );

        return ProjectResource::make($updated);
    }

    public function destroy(Request $request, int $project, DeleteProject $action): Response
    {
        $this->authorizeAbility($request, 'project.delete');
        $this->rejectUnexpected($request, ['lock_version', 'deletion_reason']);
        $input = $request->validate([
            'lock_version' => ['required', 'integer', 'min:1'],
            'deletion_reason' => ['nullable', 'string', 'max:500'],
        ]);
        $context = $this->tenantContext($request);
        $action->execute($this->actor($request), $context, $this->project($context, $project), (int) $input['lock_version'], $input['deletion_reason'] ?? null, $this->correlationId($request));

        return response()->noContent();
    }

    public function history(Request $request, int $project, ProjectRevisionQuery $query): AnonymousResourceCollection
    {
        $this->authorizeAbility($request, 'project.view-revisions');
        $context = $this->tenantContext($request);
        $subject = $this->project($context, $project);
        $rows = $query->history($this->actor($request), $context, $subject);
        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $page = max($request->integer('page', 1), 1);
        $paginator = new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return ProjectRevisionResource::collection($paginator);
    }

    public function revision(Request $request, int $project, int $revision, ProjectRevisionQuery $query): ProjectRevisionResource
    {
        $this->authorizeAbility($request, 'project.view-revisions');
        $context = $this->tenantContext($request);

        return ProjectRevisionResource::make($query->comparison($this->actor($request), $context, $this->project($context, $project), $revision));
    }

    public function restore(Request $request, int $project, int $revision, ProjectRevisionQuery $revisionQuery, RestoreProjectRevision $action): ProjectResource
    {
        $this->authorizeAbility($request, 'project.restore-revision');
        $this->rejectUnexpected($request, ['lock_version']);
        $input = $request->validate(['lock_version' => ['required', 'integer', 'min:1']]);
        $context = $this->tenantContext($request);
        $subject = $this->project($context, $project);
        $source = $revisionQuery->sourceVersionForRestore($this->actor($request), $context, $subject, $revision);
        $restored = $action->execute($this->actor($request), $context, $subject, $source, (int) $input['lock_version'], $this->correlationId($request));

        return ProjectResource::make($restored);
    }

    private function projectData(Request $request, bool $updating): SaveProjectData
    {
        $allowed = ['title', 'cost_center_id', 'stage', 'deferred_target_planning_year_id'];
        if ($updating) {
            $allowed[] = 'lock_version';
        }
        $this->rejectUnexpected($request, $allowed);
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'cost_center_id' => ['required', 'integer', 'min:1'],
            'stage' => ['required', Rule::enum(ProjectStage::class)],
            'deferred_target_planning_year_id' => ['nullable', 'integer', 'min:1'],
        ];
        if ($updating) {
            $rules['lock_version'] = ['required', 'integer', 'min:1'];
        }
        $input = $request->validate($rules);

        return new SaveProjectData(
            (string) $input['title'], (int) $input['cost_center_id'], ProjectStage::from((string) $input['stage']),
            isset($input['deferred_target_planning_year_id']) ? (int) $input['deferred_target_planning_year_id'] : null,
            isset($input['lock_version']) ? (int) $input['lock_version'] : null,
        );
    }

    /** @param list<string> $allowed */
    private function rejectUnexpected(Request $request, array $allowed): void
    {
        $unexpected = array_diff(array_keys($request->all()), $allowed);
        if ($unexpected !== []) {
            throw ValidationException::withMessages(array_fill_keys($unexpected, 'This field is not allowed for this operation.'));
        }
    }

    private function project(TenantContext $context, int $id): Project
    {
        return TenantOwnedRecordQuery::findOrFail($context, Project::class, $id);
    }

    private function authorizeAbility(Request $request, string $ability): void
    {
        if (! $this->authorizeAbility->allows($request, $this->actor($request), $ability)) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }
    }
}
