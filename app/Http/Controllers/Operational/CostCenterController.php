<?php

namespace App\Http\Controllers\Operational;

use App\Domain\MasterData\Actions\CreateCostCenter;
use App\Domain\MasterData\Actions\DeactivateCostCenter;
use App\Domain\MasterData\Actions\DeleteCostCenter;
use App\Domain\MasterData\Actions\ReactivateCostCenter;
use App\Domain\MasterData\Actions\RestoreCostCenterRevision;
use App\Domain\MasterData\Actions\UpdateCostCenter;
use App\Domain\MasterData\Queries\CostCenterSelectorQuery;
use App\Domain\MasterData\Queries\CostCenterTreeQuery;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Revisions\Queries\RevisionHistoryQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthorizeApplicationAbility;
use App\Models\CostCenter;
use App\Models\RevisionBatch;
use App\Models\RevisionBatchItem;
use App\Models\Version;
use App\Policies\CostCenterPolicy;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use LogicException;

final class CostCenterController extends Controller
{
    public function __construct(private readonly AuthorizeApplicationAbility $authorizeAbility) {}

    public function index(Request $request, CostCenterTreeQuery $costCenterTree): View
    {
        $actor = $this->actor($request);
        $context = $this->tenantContext($request);

        return view('operational.cost-centers.index', [
            'costCenters' => $costCenterTree->forTenant($actor, $context)
                ->map(fn (CostCenter $costCenter): array => $this->treeProps($costCenter, 0))
                ->all(),
            'abilities' => $this->abilities($request),
        ]);
    }

    public function create(Request $request, CostCenterSelectorQuery $selector): View
    {
        $actor = $this->actor($request);
        $context = $this->tenantContext($request);

        return view('operational.cost-centers.create', [
            'parents' => $this->parentOptions($selector->forNewSelection($actor, $context)),
            'abilities' => $this->abilities($request),
        ]);
    }

    public function store(Request $request, CreateCostCenter $createCostCenter): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer'],
        ]);
        $context = $this->tenantContext($request);

        $costCenter = $createCostCenter->execute(
            $this->actor($request),
            $context,
            $validated['name'],
            $this->optionalCostCenter($context, $validated['parent_id'] ?? null),
            $this->correlationId($request),
        );

        return redirect()
            ->route('operational.cost-centers.edit', $costCenter->getKey())
            ->with('success', 'Cost center created.');
    }

    public function edit(
        Request $request,
        int $costCenter,
        CostCenterSelectorQuery $selector,
    ): View {
        $actor = $this->actor($request);
        $context = $this->tenantContext($request);
        $target = $this->costCenter($context, $costCenter);

        return view('operational.cost-centers.edit', [
            'costCenter' => $this->costCenterProps($target),
            'parents' => $this->parentOptions($selector->forRecord($actor, $context, $costCenter)),
            'abilities' => $this->abilities($request),
        ]);
    }

    public function update(
        Request $request,
        int $costCenter,
        UpdateCostCenter $updateCostCenter,
    ): RedirectResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ]);
        $context = $this->tenantContext($request);

        $updateCostCenter->execute(
            $this->actor($request),
            $context,
            $this->costCenter($context, $costCenter),
            $validated['name'],
            $this->optionalCostCenter($context, $validated['parent_id'] ?? null),
            (int) $validated['lock_version'],
            $this->correlationId($request),
        );

        return redirect()
            ->route('operational.cost-centers.edit', $costCenter)
            ->with('success', 'Cost center updated.');
    }

    public function deactivate(
        Request $request,
        int $costCenter,
        DeactivateCostCenter $deactivateCostCenter,
    ): RedirectResponse {
        return $this->changeActiveState($request, $costCenter, $deactivateCostCenter, false);
    }

    public function reactivate(
        Request $request,
        int $costCenter,
        ReactivateCostCenter $reactivateCostCenter,
    ): RedirectResponse {
        return $this->changeActiveState($request, $costCenter, $reactivateCostCenter, true);
    }

    public function destroy(
        Request $request,
        int $costCenter,
        DeleteCostCenter $deleteCostCenter,
    ): RedirectResponse {
        $validated = $request->validate([
            'lock_version' => ['required', 'integer', 'min:1'],
        ]);
        $context = $this->tenantContext($request);

        $deleteCostCenter->execute(
            $this->actor($request),
            $context,
            $this->costCenter($context, $costCenter),
            (int) $validated['lock_version'],
            $this->correlationId($request),
        );

        return redirect()
            ->route('operational.cost-centers.index')
            ->with('success', 'Cost center deleted.');
    }

    public function history(
        Request $request,
        int $costCenter,
        RevisionHistoryQuery $revisionHistory,
    ): View {
        $actor = $this->actor($request);
        $context = $this->tenantContext($request);
        $target = $this->costCenter($context, $costCenter);
        app(CostCenterPolicy::class)->viewRevisions($actor, $target)->authorize();

        return view('operational.cost-centers.history', [
            'costCenter' => $this->costCenterProps($target),
            'history' => $this->historyProps($context, $target, $revisionHistory),
            'canRestore' => app(CostCenterPolicy::class)->restoreRevision($actor, $target)->allowed(),
        ]);
    }

    public function restore(
        Request $request,
        int $costCenter,
        int $version,
        RestoreCostCenterRevision $restoreCostCenterRevision,
    ): RedirectResponse {
        $validated = $request->validate([
            'lock_version' => ['required', 'integer', 'min:1'],
        ]);
        $context = $this->tenantContext($request);
        $target = $this->costCenter($context, $costCenter);

        $restoreCostCenterRevision->execute(
            $this->actor($request),
            $context,
            $target,
            $this->version($context, $target, $version),
            (int) $validated['lock_version'],
            $this->correlationId($request),
        );

        return redirect()
            ->route('operational.cost-centers.history', $costCenter)
            ->with('success', 'Cost center revision restored.');
    }

    /** @param DeactivateCostCenter|ReactivateCostCenter $action */
    private function changeActiveState(
        Request $request,
        int $costCenter,
        object $action,
        bool $active,
    ): RedirectResponse {
        $validated = $request->validate([
            'lock_version' => ['required', 'integer', 'min:1'],
        ]);
        $context = $this->tenantContext($request);

        $action->execute(
            $this->actor($request),
            $context,
            $this->costCenter($context, $costCenter),
            (int) $validated['lock_version'],
            $this->correlationId($request),
        );

        return redirect()
            ->route('operational.cost-centers.index')
            ->with('success', $active ? 'Cost center reactivated.' : 'Cost center deactivated.');
    }

    private function costCenter(TenantContext $context, int $id): CostCenter
    {
        /** @var CostCenter $costCenter */
        $costCenter = TenantOwnedRecordQuery::findOrFail($context, CostCenter::class, $id);

        return $costCenter;
    }

    private function optionalCostCenter(TenantContext $context, mixed $id): ?CostCenter
    {
        return $id === null ? null : $this->costCenter($context, (int) $id);
    }

    private function version(TenantContext $context, CostCenter $costCenter, int $versionId): Version
    {
        $item = RevisionBatchItem::query()
            ->where('version_id', $versionId)
            ->where('versionable_type', $costCenter->getMorphClass())
            ->where('versionable_id', $costCenter->getKey())
            ->whereHas('batch', fn ($query) => $query
                ->where('tenant_id', $context->tenantId)
                ->where('root_subject_type', $costCenter->getMorphClass())
                ->where('root_subject_id', $costCenter->getKey()))
            ->with('version')
            ->firstOrFail();

        /** @var Version $version */
        $version = $item->version;

        return $version;
    }

    /** @return array<string, mixed> */
    private function costCenterProps(CostCenter $costCenter): array
    {
        return [
            'id' => (int) $costCenter->getKey(),
            'name' => (string) $costCenter->name,
            'parentId' => $costCenter->parent_id === null ? null : (int) $costCenter->parent_id,
            'active' => (bool) $costCenter->active,
            'lockVersion' => (int) $costCenter->lock_version,
        ];
    }

    /** @return array<string, mixed> */
    private function treeProps(CostCenter $costCenter, int $depth): array
    {
        $children = $costCenter->getRelation('children');

        return [
            ...$this->costCenterProps($costCenter),
            'depth' => $depth,
            'children' => $children instanceof Collection
                ? $children->toBase()->map(function (Model $child) use ($depth): array {
                    if (! $child instanceof CostCenter) {
                        throw new LogicException('The cost center tree contains an invalid child model.');
                    }

                    return $this->treeProps($child, $depth + 1);
                })->all()
                : [],
        ];
    }

    /** @param Collection<int, CostCenter> $costCenters
     * @return list<array{value: int, label: string, active: bool}>
     */
    private function parentOptions(Collection $costCenters): array
    {
        return $costCenters->map(fn (CostCenter $costCenter): array => [
            'value' => (int) $costCenter->getKey(),
            'label' => (string) $costCenter->name,
            'active' => (bool) $costCenter->active,
        ])->all();
    }

    /** @return array<string, bool> */
    private function abilities(Request $request): array
    {
        $actor = $this->actor($request);

        return [
            'create' => $this->authorizeAbility->allows($request, $actor, 'cost-center.create'),
            'update' => $this->authorizeAbility->allows($request, $actor, 'cost-center.update'),
            'delete' => $this->authorizeAbility->allows($request, $actor, 'cost-center.delete'),
            'deactivate' => $this->authorizeAbility->allows($request, $actor, 'cost-center.deactivate'),
            'reactivate' => $this->authorizeAbility->allows($request, $actor, 'cost-center.reactivate'),
            'viewRevisions' => $this->authorizeAbility->allows($request, $actor, 'cost-center.view-revisions'),
            'restoreRevision' => $this->authorizeAbility->allows($request, $actor, 'cost-center.restore-revision'),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function historyProps(
        TenantContext $context,
        CostCenter $costCenter,
        RevisionHistoryQuery $revisionHistory,
    ): array {
        $batches = $revisionHistory->forSubject($context, $costCenter);

        return $batches->toBase()->map(function (RevisionBatch $batch) use ($context, $costCenter, $revisionHistory): array {
            $source = $revisionHistory->forBatch($batch, $context)
                ->first(fn ($row): bool => $row->versionableType === $costCenter->getMorphClass()
                    && $row->versionableId === (int) $costCenter->getKey());
            $operation = $batch->getAttribute('operation');
            $occurredAt = $batch->getAttribute('occurred_at');

            return [
                'operation' => $operation instanceof RevisionOperation
                    ? $operation->value
                    : (string) $batch->getRawOriginal('operation'),
                'actor' => $batch->actor?->name,
                'timestamp' => $occurredAt instanceof CarbonInterface
                    ? $occurredAt->toIso8601String()
                    : null,
                'reason' => $batch->reason,
                'sourceRevisionId' => $source?->versionId,
                'restoredFromRevisionId' => $batch->restored_from_version_id,
            ];
        })->all();
    }
}
