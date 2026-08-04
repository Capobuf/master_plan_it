<?php

namespace App\Filament\Resources\Users\Pages;

use App\Domain\IdentityAccess\Actions\AssignTenantRoles;
use App\Domain\IdentityAccess\Actions\UpdateTenantUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\Diagnostics\CorrelationId;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

final class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        if (! $record instanceof User) {
            throw new LogicException('UserResource can only edit tenant users.');
        }

        $record->unsetRelation('roles');
        $data['roles'] = $record->roles
            ->pluck('id')
            ->map(static fn (mixed $roleId): int => (int) $roleId)
            ->all();

        return $data;
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof User) {
            throw new LogicException('UserResource can only update tenant users.');
        }

        $name = is_string($data['name'] ?? null) ? $data['name'] : '';
        $email = is_string($data['email'] ?? null) ? $data['email'] : '';
        $roles = UserResource::tenantRolesFromForm($data['roles'] ?? null);
        $actor = UserResource::authenticatedActor();
        $context = UserResource::tenantContext();
        $correlationId = app(CorrelationId::class)->value();

        return DB::transaction(function () use ($actor, $context, $correlationId, $email, $name, $record, $roles): User {
            $updated = app(UpdateTenantUser::class)->execute(
                $actor,
                $context,
                $record,
                $name,
                $email,
                $correlationId,
            );

            return app(AssignTenantRoles::class)->execute(
                $actor,
                $context,
                $updated,
                $roles,
                $correlationId,
            );
        });
    }
}
