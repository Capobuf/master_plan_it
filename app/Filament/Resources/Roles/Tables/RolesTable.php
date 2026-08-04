<?php

namespace App\Filament\Resources\Roles\Tables;

use App\Domain\IdentityAccess\Actions\CreateTenantRole;
use App\Domain\IdentityAccess\Actions\DeleteTenantRole;
use App\Filament\Resources\Roles\RoleResource;
use App\Support\Diagnostics\CorrelationId;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

final class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('permissions_count')
                    ->label('Permissions')
                    ->counts('permissions'),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label('Edit')
                    ->authorize(fn (Role $record): bool => RoleResource::canEdit($record))
                    ->url(fn (Role $record): string => RoleResource::getUrl('edit', ['record' => $record])),
                Action::make('duplicate')
                    ->label('Duplicate')
                    ->authorize(fn (): bool => RoleResource::canCreate())
                    ->schema([
                        TextInput::make('name')->required()->maxLength(255),
                    ])
                    ->action(function (Role $record, array $data): void {
                        $name = is_string($data['name'] ?? null) ? $data['name'] : '';
                        $abilities = $record->permissions()
                            ->orderBy('name')
                            ->pluck('name')
                            ->all();

                        app(CreateTenantRole::class)->execute(
                            RoleResource::authenticatedActor(),
                            RoleResource::tenantContext(),
                            $name,
                            $abilities,
                            app(CorrelationId::class)->value(),
                        );
                    }),
                Action::make('delete')
                    ->label('Delete')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn (Role $record): bool => RoleResource::canDelete($record))
                    ->action(function (Role $record): void {
                        app(DeleteTenantRole::class)->execute(
                            RoleResource::authenticatedActor(),
                            RoleResource::tenantContext(),
                            $record,
                            app(CorrelationId::class)->value(),
                        );
                    }),
            ]);
    }
}
