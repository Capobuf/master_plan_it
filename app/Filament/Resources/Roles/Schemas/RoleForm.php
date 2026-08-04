<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Support\Authorization\PermissionCatalogue;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        $abilityOptions = [];

        foreach (PermissionCatalogue::tenantAbilities() as $ability) {
            $abilityOptions[$ability] = Str::of($ability)
                ->replace('.', ' ')
                ->headline()
                ->toString();
        }

        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            CheckboxList::make('abilities')
                ->options($abilityOptions)
                ->columns(2)
                ->searchable()
                ->bulkToggleable(),
        ]);
    }
}
