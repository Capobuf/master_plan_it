<?php

namespace App\Filament\Resources\CostCenters\Pages;

use App\Filament\Resources\CostCenters\CostCenterResource;
use App\Models\CostCenter;
use App\Models\RevisionBatch;
use Filament\Resources\Pages\Page;

final class CostCenterRevisionHistory extends Page
{
    protected static string $resource = CostCenterResource::class;

    protected string $view = 'filament.resources.cost-centers.pages.cost-center-revision-history';

    public CostCenter $record;

    public function mount(int|string $record): void
    {
        $costCenter = CostCenterResource::getEloquentQuery()->findOrFail($record);
        abort_unless(CostCenterResource::canViewRevisions($costCenter), 403);
        $this->record = $costCenter;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'record' => $this->record,
            'batches' => RevisionBatch::query()
                ->where('tenant_id', CostCenterResource::tenantContext()->tenantId)
                ->where('root_subject_type', $this->record->getMorphClass())
                ->where('root_subject_id', $this->record->getKey())
                ->with('items.version')
                ->latest('occurred_at')
                ->get(),
        ];
    }
}
