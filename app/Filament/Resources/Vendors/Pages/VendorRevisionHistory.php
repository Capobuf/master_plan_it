<?php

namespace App\Filament\Resources\Vendors\Pages;

use App\Domain\MasterData\Actions\RestoreVendorRevision;
use App\Filament\Resources\Vendors\VendorResource;
use App\Models\RevisionBatch;
use App\Models\Vendor;
use App\Models\Version;
use App\Support\Diagnostics\CorrelationId;
use Filament\Resources\Pages\Page;

final class VendorRevisionHistory extends Page
{
    protected static string $resource = VendorResource::class;

    protected string $view = 'filament.resources.vendors.pages.vendor-revision-history';

    public Vendor $record;

    public function mount(int|string $record): void
    {
        $vendor = VendorResource::getEloquentQuery()->findOrFail($record);
        abort_unless(VendorResource::canViewRevisions($vendor), 403);
        $this->record = $vendor;
    }

    public function restore(int $versionId): void
    {
        abort_unless(VendorResource::canRestoreRevision($this->record), 403);
        $version = Version::query()->findOrFail($versionId);
        $this->record = app(RestoreVendorRevision::class)->execute(
            VendorResource::authenticatedActor(),
            VendorResource::tenantContext(),
            $this->record,
            $version,
            $this->record->lock_version,
            app(CorrelationId::class)->value(),
        );
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'record' => $this->record,
            'batches' => RevisionBatch::query()
                ->where('tenant_id', VendorResource::tenantContext()->tenantId)
                ->where('root_subject_type', $this->record->getMorphClass())
                ->where('root_subject_id', $this->record->getKey())
                ->with('items.version')
                ->latest('occurred_at')
                ->get(),
        ];
    }
}
