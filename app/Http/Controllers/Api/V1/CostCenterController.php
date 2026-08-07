<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\MasterData\Actions\CreateCostCenter;
use App\Domain\MasterData\Actions\DeactivateCostCenter;
use App\Domain\MasterData\Actions\DeleteCostCenter;
use App\Domain\MasterData\Actions\ReactivateCostCenter;
use App\Domain\MasterData\Actions\RestoreCostCenterRevision;
use App\Domain\MasterData\Actions\UpdateCostCenter;
use App\Domain\MasterData\Queries\CostCenterTreeQuery;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Revisions\Queries\RevisionHistoryQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthorizeApplicationAbility;
use App\Http\Resources\Api\V1\CostCenterResource;
use App\Http\Resources\Api\V1\RevisionResource;
use App\Models\CostCenter;
use App\Models\RevisionBatch;
use App\Models\RevisionBatchItem;
use App\Models\Version;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

final class CostCenterController extends Controller
{
    public function __construct(private readonly AuthorizeApplicationAbility $authorizeAbility) {}

    /** The collection representation is the authorized hierarchy tree. */
    public function index(Request $request, CostCenterTreeQuery $tree): AnonymousResourceCollection
    {
        return $this->tree($request, $tree);
    }

    public function hierarchy(Request $request, CostCenterTreeQuery $tree): AnonymousResourceCollection
    {
        return $this->tree($request, $tree);
    }

    public function show(Request $request, int $costCenter): CostCenterResource
    {
        $this->authorize($request, 'cost-center.view');

        return CostCenterResource::make($this->costCenter($request, $costCenter));
    }

    public function store(Request $request, CreateCostCenter $action): CostCenterResource
    {
        $this->authorize($request, 'cost-center.create');
        $this->rejectUnexpected($request, ['name', 'parent_id']);
        $input = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $context = $this->tenantContext($request);
        $parent = $this->optionalParent($context, $input['parent_id'] ?? null);
        $created = $action->execute($this->actor($request), $context, (string) $input['name'], $parent, $this->correlationId($request));

        return CostCenterResource::make($created);
    }

    public function update(Request $request, int $costCenter, UpdateCostCenter $action): CostCenterResource
    {
        $this->authorize($request, 'cost-center.update');
        $this->rejectUnexpected($request, ['name', 'parent_id', 'lock_version']);
        $input = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'min:1'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ]);
        $context = $this->tenantContext($request);
        $updated = $action->execute(
            $this->actor($request),
            $context,
            $this->costCenter($request, $costCenter),
            (string) $input['name'],
            $this->optionalParent($context, $input['parent_id'] ?? null),
            (int) $input['lock_version'],
            $this->correlationId($request),
        );

        return CostCenterResource::make($updated);
    }

    public function deactivate(Request $request, int $costCenter, DeactivateCostCenter $action): CostCenterResource
    {
        return $this->changeState($request, $costCenter, $action, 'cost-center.deactivate');
    }

    public function reactivate(Request $request, int $costCenter, ReactivateCostCenter $action): CostCenterResource
    {
        return $this->changeState($request, $costCenter, $action, 'cost-center.reactivate');
    }

    public function destroy(Request $request, int $costCenter, DeleteCostCenter $action): Response
    {
        $this->authorize($request, 'cost-center.delete');
        $lockVersion = $this->lockVersion($request);
        $action->execute($this->actor($request), $this->tenantContext($request), $this->costCenter($request, $costCenter), $lockVersion, $this->correlationId($request));

        return response()->noContent();
    }

    public function history(Request $request, int $costCenter, RevisionHistoryQuery $history): AnonymousResourceCollection
    {
        $this->authorize($request, 'cost-center.view-revisions');
        $context = $this->tenantContext($request);
        $subject = $this->costCenter($request, $costCenter);
        $rows = $history->forSubject($context, $subject)->map(function (RevisionBatch $batch) use ($context, $history, $subject): array {
            $source = $history->forBatch($batch, $context)->first(fn ($row): bool => $row->versionableType === $subject->getMorphClass() && $row->versionableId === (int) $subject->getKey());
            $operation = $batch->getAttribute('operation');

            return [
                'operation' => $operation instanceof RevisionOperation ? $operation->value : (string) $batch->getRawOriginal('operation'),
                'actor' => $batch->actor?->name,
                'timestamp' => $batch->occurred_at instanceof CarbonInterface ? $batch->occurred_at->toIso8601String() : null,
                'reason' => $batch->reason,
                'source_revision_id' => $source?->versionId,
                'restored_from_revision_id' => $batch->restored_from_version_id === null ? null : (int) $batch->restored_from_version_id,
            ];
        });

        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $page = max($request->integer('page', 1), 1);
        $historyPage = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return RevisionResource::collection($historyPage);
    }

    public function restore(Request $request, int $costCenter, int $version, RestoreCostCenterRevision $action): CostCenterResource
    {
        $this->authorize($request, 'cost-center.restore-revision');
        $lockVersion = $this->lockVersion($request);
        $context = $this->tenantContext($request);
        $subject = $this->costCenter($request, $costCenter);
        $revision = $this->version($context, $subject, $version);
        $updated = $action->execute($this->actor($request), $context, $subject, $revision, $lockVersion, $this->correlationId($request));

        return CostCenterResource::make($updated);
    }

    private function tree(Request $request, CostCenterTreeQuery $tree): AnonymousResourceCollection
    {
        $this->authorize($request, 'cost-center.view');
        $items = $tree->forTenant($this->actor($request), $this->tenantContext($request));
        $this->setDepth($items->all(), 0);

        return CostCenterResource::collection($items)->additional([
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $items->count(),
                'total' => $items->count(),
            ],
            'links' => [
                'first' => $request->url(),
                'last' => $request->url(),
                'prev' => null,
                'next' => null,
            ],
        ]);
    }

    /** @param list<CostCenter> $items */
    private function setDepth(array $items, int $depth): void
    {
        foreach ($items as $item) {
            $item->setAttribute('depth', $depth);
            if ($item->relationLoaded('children')) {
                $this->setDepth($item->children->all(), $depth + 1);
            }
        }
    }

    private function costCenter(Request $request, int $id): CostCenter
    {
        /** @var CostCenter $costCenter */
        $costCenter = TenantOwnedRecordQuery::findOrFail($this->tenantContext($request), CostCenter::class, $id);

        return $costCenter;
    }

    private function optionalParent(TenantContext $context, mixed $id): ?CostCenter
    {
        if ($id === null) {
            return null;
        }

        /** @var CostCenter $parent */
        $parent = TenantOwnedRecordQuery::findOrFail($context, CostCenter::class, (int) $id);

        return $parent;
    }

    private function changeState(Request $request, int $id, DeactivateCostCenter|ReactivateCostCenter $action, string $ability): CostCenterResource
    {
        $this->authorize($request, $ability);
        $lockVersion = $this->lockVersion($request);
        /** @var CostCenter $updated */
        $updated = $action->execute($this->actor($request), $this->tenantContext($request), $this->costCenter($request, $id), $lockVersion, $this->correlationId($request));

        return CostCenterResource::make($updated);
    }

    private function lockVersion(Request $request): int
    {
        $this->rejectUnexpected($request, ['lock_version']);

        return (int) $request->validate(['lock_version' => ['required', 'integer', 'min:1']])['lock_version'];
    }

    private function version(TenantContext $context, CostCenter $subject, int $versionId): Version
    {
        $item = RevisionBatchItem::query()
            ->where('version_id', $versionId)
            ->where('versionable_type', $subject->getMorphClass())
            ->where('versionable_id', $subject->getKey())
            ->whereHas('batch', fn ($query) => $query->where('tenant_id', $context->tenantId)->where('root_subject_type', $subject->getMorphClass())->where('root_subject_id', $subject->getKey()))
            ->with('version')
            ->firstOrFail();

        /** @var Version $version */
        $version = $item->version;

        return $version;
    }

    /** @param list<string> $allowed */
    private function rejectUnexpected(Request $request, array $allowed): void
    {
        $unexpected = array_diff(array_keys($request->all()), $allowed);
        if ($unexpected !== []) {
            throw ValidationException::withMessages(array_fill_keys($unexpected, 'This field is not allowed for this operation.'));
        }
    }

    private function authorize(Request $request, string $ability): void
    {
        if (! $this->authorizeAbility->allows($request, $this->actor($request), $ability)) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }
    }
}
