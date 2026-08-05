<?php

namespace App\Filament\Resources\Vendors\Pages;

use App\Filament\Resources\Vendors\VendorResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

final class ListVendors extends ListRecords
{
    protected static string $resource = VendorResource::class;

    protected function authorizeAccess(): void
    {
        VendorResource::getEloquentQuery();
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Create vendor')
                ->authorize(fn (): bool => VendorResource::canCreate())
                ->url(VendorResource::getUrl('create')),
        ];
    }
}
