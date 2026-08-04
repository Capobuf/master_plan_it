<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Domain\IdentityAccess\Actions\UpdateTenantRole;
use App\Filament\Resources\Roles\RoleResource;
use App\Support\Diagnostics\CorrelationId;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Spatie\Permission\Models\Role;

final class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        if (! $record instanceof Role) {
            throw new LogicException('RoleResource can only edit tenant roles.');
        }

        $data['abilities'] = $record->permissions()
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return $data;
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Role) {
            throw new LogicException('RoleResource can only update tenant roles.');
        }

        $name = is_string($data['name'] ?? null) ? $data['name'] : '';
        $abilities = array_values(array_filter(
            is_array($data['abilities'] ?? null) ? $data['abilities'] : [],
            is_string(...),
        ));

        return app(UpdateTenantRole::class)->execute(
            RoleResource::authenticatedActor(),
            RoleResource::tenantContext(),
            $record,
            $name,
            $abilities,
            app(CorrelationId::class)->value(),
        );
    }
}
