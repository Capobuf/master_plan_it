<?php

namespace App\Filament\Resources\Users\Pages;

use App\Domain\IdentityAccess\Actions\CreateTenantUser;
use App\Filament\Resources\Users\UserResource;
use App\Support\Diagnostics\CorrelationId;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $name = is_string($data['name'] ?? null) ? $data['name'] : '';
        $email = is_string($data['email'] ?? null) ? $data['email'] : '';
        $password = is_string($data['password'] ?? null) ? $data['password'] : '';
        $roles = UserResource::tenantRolesFromForm($data['roles'] ?? null);

        return app(CreateTenantUser::class)->execute(
            UserResource::authenticatedActor(),
            UserResource::tenantContext(),
            $name,
            $email,
            $password,
            $roles,
            app(CorrelationId::class)->value(),
        );
    }
}
