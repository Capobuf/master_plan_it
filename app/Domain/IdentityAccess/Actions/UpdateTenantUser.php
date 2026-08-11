<?php

namespace App\Domain\IdentityAccess\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\TenantAbilityAuthorizer;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

final class UpdateTenantUser
{
    public function __construct(
        private readonly TenantAbilityAuthorizer $tenantAbilityAuthorizer,
        private readonly PermissionRegistrar $permissionRegistrar,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function execute(
        User $actor,
        TenantContext $context,
        User $target,
        string $name,
        string $email,
        string $correlationId,
    ): User {
        $previousTeamId = $this->permissionRegistrar->getPermissionsTeamId();

        try {
            [$persistedActor, $tenant] = $this->authorize($actor, $context);
            $this->permissionRegistrar->setPermissionsTeamId((int) $tenant->getKey());

            return DB::transaction(function () use ($correlationId, $email, $name, $persistedActor, $target, $tenant): User {
                $user = $this->lockedTenantUser($target, (int) $tenant->getKey());
                [$validatedName, $validatedEmail] = $this->validatedIdentity($name, $email, $user);

                try {
                    $user->forceFill([
                        'name' => $validatedName,
                        'email' => $validatedEmail,
                        'lock_version' => $user->lock_version + 1,
                    ])->save();
                } catch (UniqueConstraintViolationException) {
                    throw ValidationException::withMessages([
                        'email' => 'The email address has already been taken.',
                    ]);
                }

                $this->auditRecorder->record(
                    eventType: 'tenant.user.updated',
                    correlationId: $correlationId,
                    properties: new AuditProperties(['changed_fields' => ['name', 'email']]),
                    actor: $persistedActor,
                    tenantId: (int) $tenant->getKey(),
                    subject: $user,
                );

                return $user->refresh();
            });
        } finally {
            $target->unsetRelation('roles');
            $target->unsetRelation('permissions');
            $actor->unsetRelation('roles');
            $actor->unsetRelation('permissions');
            $context->actor->unsetRelation('roles');
            $context->actor->unsetRelation('permissions');
            $this->permissionRegistrar->setPermissionsTeamId($previousTeamId);
        }
    }

    /** @return array{string, string} */
    private function validatedIdentity(string $name, string $email, User $user): array
    {
        $values = Validator::make([
            'name' => trim($name),
            'email' => trim($email),
        ], [
            'name' => ['required', 'string', 'max:255', 'not_regex:/^\s*$/u'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'not_regex:/^\s*$/u',
                Rule::unique('users', 'email')->ignore($user->getKey()),
            ],
        ])->validate();

        return [$values['name'], $values['email']];
    }

    private function lockedTenantUser(User $target, int $tenantId): User
    {
        $keyName = $target->getKeyName();
        $key = $target->getKey();
        $originalKey = $target->getRawOriginal($keyName);
        if (! $target->exists || $key === null || $key !== $originalKey) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        $user = User::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($originalKey)
            ->lockForUpdate()
            ->first();

        if ($user === null) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        return $user;
    }

    /** @return array{User, Tenant} */
    private function authorize(User $actor, TenantContext $context): array
    {
        return $this->tenantAbilityAuthorizer->authorize($actor, $context, 'tenant-users.manage');
    }
}
