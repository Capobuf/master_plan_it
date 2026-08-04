<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

final class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    protected function authorizeAccess(): void
    {
        RoleResource::getEloquentQuery();
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Create role')
                ->authorize(fn (): bool => RoleResource::canCreate())
                ->url(RoleResource::getUrl('create')),
        ];
    }
}
