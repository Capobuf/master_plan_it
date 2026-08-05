<?php

namespace App\Filament\Resources\CostCenters\Pages;

use App\Filament\Resources\CostCenters\CostCenterResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

final class ListCostCenters extends ListRecords
{
    protected static string $resource = CostCenterResource::class;

    protected function authorizeAccess(): void
    {
        CostCenterResource::getEloquentQuery();
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Create cost center')
                ->authorize(fn (): bool => CostCenterResource::canCreate())
                ->url(CostCenterResource::getUrl('create')),
        ];
    }
}
