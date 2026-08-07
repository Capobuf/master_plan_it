<?php

namespace App\Http\Controllers\Api\V1;

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
use App\Http\Resources\Api\V1\RevisionResource;
use App\Http\Resources\Api\V1\VendorResource;
use App\Models\RevisionBatch;
use App\Models\RevisionBatchItem;
use App\Models\Vendor;
use App\Models\Version;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

final class VendorController extends Controller
{
    public function __construct(private readonly AuthorizeApplicationAbility $authorizeAbility) {}

    public function index(Request $request, VendorListQuery $vendors): AnonymousResourceCollection
    {
        $this->authorize($request, 'vendor.view');
        $query = $vendors->forTenant($this->actor($request), $this->tenantContext($request));
        $status = (string) $request->query('status', 'all');
        $search = trim((string) $request->query('q', ''));

        if (! in_array($status, ['all', 'active', 'inactive'], true)) {
            throw ValidationException::withMessages(['status' => 'The selected status is invalid.']);
        }
        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }
        if ($status !== 'all') {
            $query->where('active', $status === 'active');
        }

        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        return VendorResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(Request $request, int $vendor): VendorResource
    {
        $this->authorize($request, 'vendor.view');

        return VendorResource::make($this->vendor($request, $vendor));
    }

    public function store(Request $request, CreateVendor $createVendor): VendorResource
    {
        $this->authorize($request, 'vendor.create');
        $this->rejectUnexpected($request, [...array_keys($this->vendorRules()),]);
        $input = $request->validate($this->vendorRules());
        $vendor = $createVendor->execute(
            $this->actor($request),
            $this->tenantContext($request),
            (string) $input['name'],
            $this->nullable($input['vat_number'] ?? null),
            $this->nullable($input['email'] ?? null),
            $this->nullable($input['phone'] ?? null),
            $this->nullable($input['address'] ?? null),
            $this->correlationId($request),
        );

        return VendorResource::make($vendor);
    }

    public function update(Request $request, int $vendor, UpdateVendor $updateVendor): VendorResource
    {
        $this->authorize($request, 'vendor.update');
        $this->rejectUnexpected($request, [...array_keys($this->vendorRules()), 'lock_version']);
        $input = $request->validate([...$this->vendorRules(), 'lock_version' => ['required', 'integer', 'min:1']]);
        $updated = $updateVendor->execute(
            $this->actor($request),
            $this->tenantContext($request),
            $this->vendor($request, $vendor),
            (string) $input['name'],
            $this->nullable($input['vat_number'] ?? null),
            $this->nullable($input['email'] ?? null),
            $this->nullable($input['phone'] ?? null),
            $this->nullable($input['address'] ?? null),
            (int) $input['lock_version'],
            $this->correlationId($request),
        );

        return VendorResource::make($updated);
    }

    public function deactivate(Request $request, int $vendor, DeactivateVendor $action): VendorResource
    {
        return $this->changeState($request, $vendor, $action, 'vendor.deactivate');
    }

    public function reactivate(Request $request, int $vendor, ReactivateVendor $action): VendorResource
    {
        return $this->changeState($request, $vendor, $action, 'vendor.reactivate');
    }

    public function destroy(Request $request, int $vendor, DeleteVendor $action): Response
    {
        $this->authorize($request, 'vendor.delete');
        $input = $this->lockVersion($request);
        $action->execute($this->actor($request), $this->tenantContext($request), $this->vendor($request, $vendor), $input, $this->correlationId($request));

        return response()->noContent();
    }

    public function history(Request $request, int $vendor, RevisionHistoryQuery $history): AnonymousResourceCollection
    {
        $this->authorize($request, 'vendor.view-revisions');
        $context = $this->tenantContext($request);
        $subject = $this->vendor($request, $vendor);
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

    public function restore(Request $request, int $vendor, int $version, RestoreVendorRevision $action): VendorResource
    {
        $this->authorize($request, 'vendor.restore-revision');
        $input = $this->lockVersion($request);
        $context = $this->tenantContext($request);
        $subject = $this->vendor($request, $vendor);
        $revision = $this->version($context, $subject, $version);
        $updated = $action->execute($this->actor($request), $context, $subject, $revision, $input, $this->correlationId($request));

        return VendorResource::make($updated);
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

    private function vendor(Request $request, int $id): Vendor
    {
        /** @var Vendor $vendor */
        $vendor = TenantOwnedRecordQuery::findOrFail($this->tenantContext($request), Vendor::class, $id);

        return $vendor;
    }

    private function changeState(Request $request, int $vendor, DeactivateVendor|ReactivateVendor $action, string $ability): VendorResource
    {
        $this->authorize($request, $ability);
        $input = $this->lockVersion($request);
        /** @var Vendor $updated */
        $updated = $action->execute($this->actor($request), $this->tenantContext($request), $this->vendor($request, $vendor), $input, $this->correlationId($request));

        return VendorResource::make($updated);
    }

    private function lockVersion(Request $request): int
    {
        $this->rejectUnexpected($request, ['lock_version']);

        return (int) $request->validate(['lock_version' => ['required', 'integer', 'min:1']])['lock_version'];
    }

    private function version(TenantContext $context, Vendor $vendor, int $versionId): Version
    {
        $item = RevisionBatchItem::query()
            ->where('version_id', $versionId)
            ->where('versionable_type', $vendor->getMorphClass())
            ->where('versionable_id', $vendor->getKey())
            ->whereHas('batch', fn ($query) => $query->where('tenant_id', $context->tenantId)->where('root_subject_type', $vendor->getMorphClass())->where('root_subject_id', $vendor->getKey()))
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

    private function nullable(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private function authorize(Request $request, string $ability): void
    {
        if (! $this->authorizeAbility->allows($request, $this->actor($request), $ability)) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }
    }
}
