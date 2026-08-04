<?php

namespace App\Filament\Resources\Tenants\Pages;

use App\Filament\Resources\Tenants\TenantResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

final class ListTenants extends ListRecords
{
    protected static string $resource = TenantResource::class;

    protected function authorizeAccess(): void
    {
        TenantResource::authorizeViewAny();
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Create tenant')
                ->authorize(fn (): bool => TenantResource::canCreate())
                ->url(TenantResource::getUrl('create')),
        ];
    }
}
