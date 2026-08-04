<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

final class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function authorizeAccess(): void
    {
        UserResource::getEloquentQuery();
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Create user')
                ->authorize(fn (): bool => UserResource::canCreate())
                ->url(UserResource::getUrl('create')),
        ];
    }
}
