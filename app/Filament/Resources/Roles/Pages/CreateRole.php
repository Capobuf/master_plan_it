<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Domain\IdentityAccess\Actions\CreateTenantRole;
use App\Filament\Resources\Roles\RoleResource;
use App\Support\Diagnostics\CorrelationId;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $name = is_string($data['name'] ?? null) ? $data['name'] : '';
        $abilities = array_values(array_filter(
            is_array($data['abilities'] ?? null) ? $data['abilities'] : [],
            is_string(...),
        ));

        return app(CreateTenantRole::class)->execute(
            RoleResource::authenticatedActor(),
            RoleResource::tenantContext(),
            $name,
            $abilities,
            app(CorrelationId::class)->value(),
        );
    }
}
