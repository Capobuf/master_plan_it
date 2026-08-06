<?php

namespace App\Http\Controllers\Operational;

use App\Domain\MasterData\Actions\CreateVendor;
use App\Domain\MasterData\Actions\DeactivateVendor;
use App\Domain\MasterData\Actions\DeleteVendor;
use App\Domain\MasterData\Actions\ReactivateVendor;
use App\Domain\MasterData\Actions\RestoreVendorRevision;
use App\Domain\MasterData\Actions\UpdateVendor;
use App\Domain\MasterData\Queries\VendorListQuery;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Revisions\Queries\RevisionHistoryQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthorizeApplicationAbility;
use App\Models\RevisionBatch;
use App\Models\RevisionBatchItem;
use App\Models\Vendor;
use App\Models\Version;
use App\Policies\VendorPolicy;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;

final class VendorController extends Controller
{
    public function __construct(private readonly AuthorizeApplicationAbility $authorizeAbility) {}

    public function index(Request $request, VendorListQuery $vendorList): View
    {
        $actor = $this->actor($request);
        $context = $this->tenantContext($request);
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');
        $status = in_array($status, ['all', 'active', 'inactive'], true) ? $status : 'all';
        $vendors = $vendorList->forTenant($actor, $context)
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->when($status === 'active', fn ($query) => $query->where('active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('active', false))
            ->paginate(15)
            ->withQueryString();

        return view('operational.vendors.index', [
            'vendors' => $this->paginatedVendors($vendors),
            'filters' => ['q' => $search, 'status' => $status],
            'abilities' => $this->abilities($request),
        ]);
    }

    public function create(Request $request): View
    {
        return view('operational.vendors.create', [
            'abilities' => $this->abilities($request),
        ]);
    }

    public function store(Request $request, CreateVendor $createVendor): RedirectResponse
    {
        $validated = $request->validate($this->vendorRules());

        $vendor = $createVendor->execute(
            $this->actor($request),
            $this->tenantContext($request),
            $validated['name'],
            $this->nullableString($request, 'vat_number'),
            $this->nullableString($request, 'email'),
            $this->nullableString($request, 'phone'),
            $this->nullableString($request, 'address'),
            $this->correlationId($request),
        );

        return redirect()
            ->route('operational.vendors.edit', $vendor->getKey())
            ->with('success', 'Vendor created.');
    }

    public function edit(Request $request, int $vendor): View
    {
        $target = $this->vendor($this->tenantContext($request), $vendor);

        return view('operational.vendors.edit', [
            'vendor' => $this->vendorProps($target),
            'abilities' => $this->abilities($request),
        ]);
    }

    public function update(
        Request $request,
        int $vendor,
        UpdateVendor $updateVendor,
    ): RedirectResponse {
        $validated = $request->validate([
            ...$this->vendorRules(),
            'lock_version' => ['required', 'integer', 'min:1'],
        ]);
        $context = $this->tenantContext($request);

        $updateVendor->execute(
            $this->actor($request),
            $context,
            $this->vendor($context, $vendor),
            $validated['name'],
            $this->nullableString($request, 'vat_number'),
            $this->nullableString($request, 'email'),
            $this->nullableString($request, 'phone'),
            $this->nullableString($request, 'address'),
            (int) $validated['lock_version'],
            $this->correlationId($request),
        );

        return redirect()
            ->route('operational.vendors.edit', $vendor)
            ->with('success', 'Vendor updated.');
    }

    public function deactivate(
        Request $request,
        int $vendor,
        DeactivateVendor $deactivateVendor,
    ): RedirectResponse {
        return $this->changeActiveState($request, $vendor, $deactivateVendor, false);
    }

    public function reactivate(
        Request $request,
        int $vendor,
        ReactivateVendor $reactivateVendor,
    ): RedirectResponse {
        return $this->changeActiveState($request, $vendor, $reactivateVendor, true);
    }

    public function destroy(
        Request $request,
        int $vendor,
        DeleteVendor $deleteVendor,
    ): RedirectResponse {
        $validated = $request->validate([
            'lock_version' => ['required', 'integer', 'min:1'],
        ]);
        $context = $this->tenantContext($request);

        $deleteVendor->execute(
            $this->actor($request),
            $context,
            $this->vendor($context, $vendor),
            (int) $validated['lock_version'],
            $this->correlationId($request),
        );

        return redirect()
            ->route('operational.vendors.index')
            ->with('success', 'Vendor deleted.');
    }

    public function history(
        Request $request,
        int $vendor,
        RevisionHistoryQuery $revisionHistory,
    ): View {
        $actor = $this->actor($request);
        $context = $this->tenantContext($request);
        $target = $this->vendor($context, $vendor);
        app(VendorPolicy::class)->viewRevisions($actor, $target)->authorize();

        return view('operational.vendors.history', [
            'vendor' => $this->vendorProps($target),
            'history' => $this->historyProps($context, $target, $revisionHistory),
            'canRestore' => app(VendorPolicy::class)->restoreRevision($actor, $target)->allowed(),
        ]);
    }

    public function restore(
        Request $request,
        int $vendor,
        int $version,
        RestoreVendorRevision $restoreVendorRevision,
    ): RedirectResponse {
        $validated = $request->validate([
            'lock_version' => ['required', 'integer', 'min:1'],
        ]);
        $context = $this->tenantContext($request);
        $target = $this->vendor($context, $vendor);

        $restoreVendorRevision->execute(
            $this->actor($request),
            $context,
            $target,
            $this->version($context, $target, $version),
            (int) $validated['lock_version'],
            $this->correlationId($request),
        );

        return redirect()
            ->route('operational.vendors.history', $vendor)
            ->with('success', 'Vendor revision restored.');
    }

    /** @param DeactivateVendor|ReactivateVendor $action */
    private function changeActiveState(
        Request $request,
        int $vendor,
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
            $this->vendor($context, $vendor),
            (int) $validated['lock_version'],
            $this->correlationId($request),
        );

        return redirect()
            ->route('operational.vendors.index')
            ->with('success', $active ? 'Vendor reactivated.' : 'Vendor deactivated.');
    }

    /** @return array<string, list<string>> */
    private function vendorRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:65535'],
        ];
    }

    private function vendor(TenantContext $context, int $id): Vendor
    {
        /** @var Vendor $vendor */
        $vendor = TenantOwnedRecordQuery::findOrFail($context, Vendor::class, $id);

        return $vendor;
    }

    private function version(TenantContext $context, Vendor $vendor, int $versionId): Version
    {
        $item = RevisionBatchItem::query()
            ->where('version_id', $versionId)
            ->where('versionable_type', $vendor->getMorphClass())
            ->where('versionable_id', $vendor->getKey())
            ->whereHas('batch', fn ($query) => $query
                ->where('tenant_id', $context->tenantId)
                ->where('root_subject_type', $vendor->getMorphClass())
                ->where('root_subject_id', $vendor->getKey()))
            ->with('version')
            ->firstOrFail();

        /** @var Version $version */
        $version = $item->version;

        return $version;
    }

    /** @return array<string, mixed> */
    private function vendorProps(Vendor $vendor): array
    {
        return [
            'id' => (int) $vendor->getKey(),
            'name' => (string) $vendor->name,
            'vatNumber' => $vendor->vat_number === null ? null : (string) $vendor->vat_number,
            'email' => $vendor->email === null ? null : (string) $vendor->email,
            'phone' => $vendor->phone === null ? null : (string) $vendor->phone,
            'address' => $vendor->address === null ? null : (string) $vendor->address,
            'active' => (bool) $vendor->active,
            'lockVersion' => (int) $vendor->lock_version,
        ];
    }

    /** @return array<string, bool> */
    private function abilities(Request $request): array
    {
        $actor = $this->actor($request);

        return [
            'create' => $this->authorizeAbility->allows($request, $actor, 'vendor.create'),
            'update' => $this->authorizeAbility->allows($request, $actor, 'vendor.update'),
            'delete' => $this->authorizeAbility->allows($request, $actor, 'vendor.delete'),
            'deactivate' => $this->authorizeAbility->allows($request, $actor, 'vendor.deactivate'),
            'reactivate' => $this->authorizeAbility->allows($request, $actor, 'vendor.reactivate'),
            'viewRevisions' => $this->authorizeAbility->allows($request, $actor, 'vendor.view-revisions'),
            'restoreRevision' => $this->authorizeAbility->allows($request, $actor, 'vendor.restore-revision'),
        ];
    }

    /** @param LengthAwarePaginator<int, Vendor> $vendors
     * @return array<string, mixed>
     */
    private function paginatedVendors(LengthAwarePaginator $vendors): array
    {
        return [
            'data' => array_map(fn (Vendor $vendor): array => $this->vendorProps($vendor), $vendors->items()),
            'currentPage' => $vendors->currentPage(),
            'lastPage' => $vendors->lastPage(),
            'perPage' => $vendors->perPage(),
            'total' => $vendors->total(),
            'from' => $vendors->firstItem(),
            'to' => $vendors->lastItem(),
            'links' => $vendors->linkCollection()->map(static fn (array $link): array => [
                'url' => $link['url'],
                'label' => $link['label'],
                'active' => $link['active'],
            ])->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function historyProps(
        TenantContext $context,
        Vendor $vendor,
        RevisionHistoryQuery $revisionHistory,
    ): array {
        $batches = $revisionHistory->forSubject($context, $vendor);

        return $batches->toBase()->map(function (RevisionBatch $batch) use ($context, $revisionHistory, $vendor): array {
            $source = $revisionHistory->forBatch($batch, $context)
                ->first(fn ($row): bool => $row->versionableType === $vendor->getMorphClass()
                    && $row->versionableId === (int) $vendor->getKey());
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
