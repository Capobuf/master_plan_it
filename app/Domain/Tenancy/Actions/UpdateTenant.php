<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class UpdateTenant
{
    /** @var list<string> */
    private const INPUT_FIELDS = [
        'name',
        'code',
        'currency_code',
        'language_code',
        'timezone',
        'default_vat_rate',
    ];

    public function __construct(
        private readonly PlatformAdministrator $platformAdministrator,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    /**
     * @param  array<string, mixed>  $changes
     */
    public function execute(
        User $actor,
        Tenant $target,
        array $changes,
        int $expectedLockVersion,
        string $correlationId,
    ): Tenant {
        if (! $this->platformAdministrator->allows($actor, 'platform.tenants.update')) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        $values = $this->validatedChanges($changes, $target);

        return DB::transaction(function () use ($actor, $correlationId, $expectedLockVersion, $target, $values): Tenant {
            $persistedActor = $this->persistedActiveTenantlessActor($actor);
            $tenant = $this->lockedTenant($target);

            if ($tenant->lock_version !== $expectedLockVersion) {
                throw new DomainException('STALE_VERSION');
            }

            $occurredAt = CarbonImmutable::now('UTC');

            try {
                $updated = Tenant::query()
                    ->whereKey($tenant->getKey())
                    ->where('lock_version', $expectedLockVersion)
                    ->update([
                        ...$values,
                        'lock_version' => $expectedLockVersion + 1,
                        'updated_at' => $occurredAt,
                    ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'code' => 'The tenant code has already been taken.',
                ]);
            }

            if ($updated !== 1) {
                throw new DomainException('STALE_VERSION');
            }

            $tenant->refresh();

            $this->auditRecorder->record(
                eventType: 'tenant.updated',
                correlationId: $correlationId,
                properties: new AuditProperties(['changed_fields' => array_keys($values)]),
                actor: $persistedActor,
                tenantId: (int) $tenant->getKey(),
                subject: $tenant,
                occurredAt: $occurredAt,
            );

            return $tenant;
        });
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function validatedChanges(array $changes, Tenant $target): array
    {
        $unexpected = array_diff(array_keys($changes), self::INPUT_FIELDS);

        if ($unexpected !== []) {
            throw ValidationException::withMessages(
                array_fill_keys($unexpected, 'This field is not allowed for tenant updates.'),
            );
        }

        if ($changes === []) {
            throw ValidationException::withMessages(['changes' => 'At least one tenant field is required.']);
        }

        return Validator::make($changes, [
            'name' => ['sometimes', 'required', 'string', 'max:255', 'not_regex:/^\s*$/u'],
            'code' => ['sometimes', 'required', 'string', 'max:255', 'not_regex:/^\s*$/u', Rule::unique('tenants', 'code')->ignore($target->getKey())],
            'currency_code' => ['sometimes', 'required', 'string', 'regex:/^[A-Za-z]{3}$/D'],
            'language_code' => ['sometimes', 'required', 'string', 'regex:/^[A-Za-z]{1,10}$/D'],
            'timezone' => ['sometimes', 'required', 'string', 'timezone'],
            'default_vat_rate' => ['sometimes', 'required', 'string', 'regex:/^[0-9]{1,6}(?:\.[0-9]{1,6})?$/D'],
        ])->validate();
    }

    private function lockedTenant(Tenant $target): Tenant
    {
        if (! $target->exists || $target->getKey() === null) {
            throw new DomainException('STALE_VERSION');
        }

        $tenant = Tenant::query()->lockForUpdate()->find($target->getKey());

        if ($tenant === null) {
            throw new DomainException('STALE_VERSION');
        }

        return $tenant;
    }

    private function persistedActiveTenantlessActor(User $actor): User
    {
        $keyName = $actor->getKeyName();
        $currentKey = $actor->getKey();
        $originalKey = $actor->getRawOriginal($keyName);

        if (
            ! $actor->exists
            || (! is_int($currentKey) && ! is_string($currentKey))
            || (! is_int($originalKey) && ! is_string($originalKey))
            || $currentKey !== $originalKey
        ) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        $persistedActor = User::query()
            ->whereKey($originalKey)
            ->whereNull('tenant_id')
            ->where('is_active', true)
            ->first();

        if ($persistedActor === null) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        return $persistedActor;
    }
}
