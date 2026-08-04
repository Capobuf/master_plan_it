<?php

namespace App\Filament\Resources\Users\Tables;

use App\Domain\IdentityAccess\Actions\DeactivateTenantUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\Diagnostics\CorrelationId;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label('Edit')
                    ->authorize(fn (User $record): bool => UserResource::canEdit($record))
                    ->url(fn (User $record): string => UserResource::getUrl('edit', ['record' => $record])),
                Action::make('deactivate')
                    ->label('Deactivate')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => $record->is_active)
                    ->authorize(fn (User $record): bool => app(UserPolicy::class)
                        ->deactivate(UserResource::authenticatedActor(), $record)
                        ->allowed())
                    ->action(function (User $record): void {
                        app(DeactivateTenantUser::class)->execute(
                            UserResource::authenticatedActor(),
                            UserResource::tenantContext(),
                            $record,
                            [],
                            app(CorrelationId::class)->value(),
                        );
                    }),
            ]);
    }
}
