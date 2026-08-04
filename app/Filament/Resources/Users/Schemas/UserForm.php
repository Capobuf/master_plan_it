<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Resources\Users\UserResource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

final class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->required()
                ->email()
                ->maxLength(255),
            TextInput::make('password')
                ->password()
                ->required()
                ->visibleOn('create'),
            Select::make('roles')
                ->options(fn (): array => Role::query()
                    ->where('tenant_id', UserResource::tenantContext()->tenantId)
                    ->where('guard_name', 'web')
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->multiple()
                ->required()
                ->searchable(),
        ]);
    }
}
