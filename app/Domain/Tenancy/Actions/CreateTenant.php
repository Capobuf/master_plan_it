<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\Tenancy\Enums\BudgetBasis;
use App\Domain\Tenancy\Enums\TenantState;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;
use Spatie\Permission\Models\Role;

final class CreateTenant
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
     * @param  array<string, mixed>  $input
     */
    public function execute(User $actor, array $input, string $correlationId): Tenant
    {
        if (! $this->platformAdministrator->allows($actor, 'platform.tenants.create')) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        $values = $this->validatedInput($input);

        return DB::transaction(function () use ($actor, $correlationId, $values): Tenant {
            $persistedActor = $this->persistedActiveTenantlessActor($actor);
            $occurredAt = CarbonImmutable::now('UTC');

            try {
                $tenant = Tenant::query()->create([
                    ...$values,
                    'state' => TenantState::Active,
                    'budget_basis' => BudgetBasis::Net,
                    'attachment_quota_bytes' => '2147483648',
                    'deletion_reason_required' => false,
                    'created_by_user_id' => $persistedActor->getKey(),
                    'lock_version' => 1,
                    'created_at' => $occurredAt,
                    'updated_at' => $occurredAt,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'code' => 'The tenant code has already been taken.',
                ]);
            }

            $this->copySourceTemplates($tenant);

            $this->auditRecorder->record(
                eventType: 'tenant.created',
                correlationId: $correlationId,
                properties: new AuditProperties([]),
                actor: $persistedActor,
                tenantId: (int) $tenant->getKey(),
                subject: $tenant,
                occurredAt: $occurredAt,
            );

            return $tenant;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validatedInput(array $input): array
    {
        $this->rejectUnexpectedFields($input);

        return Validator::make($input, [
            'name' => ['required', 'string', 'max:255', 'not_regex:/^\s*$/u'],
            'code' => ['required', 'string', 'max:255', 'not_regex:/^\s*$/u', 'unique:tenants,code'],
            'currency_code' => ['required', 'string', 'regex:/^[A-Za-z]{3}$/D'],
            'language_code' => ['required', 'string', 'regex:/^[A-Za-z]{1,10}$/D'],
            'timezone' => ['required', 'string', 'timezone'],
            'default_vat_rate' => ['required', 'string', 'regex:/^[0-9]{1,10}(?:\.[0-9]{1,2})?$/D'],
        ])->validate();
    }

    /** @param array<string, mixed> $input */
    private function rejectUnexpectedFields(array $input): void
    {
        $unexpected = array_diff(array_keys($input), self::INPUT_FIELDS);

        if ($unexpected === []) {
            return;
        }

        throw ValidationException::withMessages(
            array_fill_keys($unexpected, 'This field is not allowed for tenant creation.'),
        );
    }

    private function copySourceTemplates(Tenant $tenant): void
    {
        $templates = Role::query()
            ->whereNull('tenant_id')
            ->where('guard_name', 'web')
            ->whereIn('name', ['Editor', 'Viewer'])
            ->lockForUpdate()
            ->get();

        if (
            $templates->count() !== 2
            || $templates->pluck('name')->sort()->values()->all() !== ['Editor', 'Viewer']
        ) {
            throw new LogicException('Global Editor and Viewer role templates must each exist exactly once.');
        }

        $templates = $templates->keyBy('name');

        foreach (['Editor', 'Viewer'] as $name) {
            /** @var Role $source */
            $source = $templates->get($name);
            $copy = Role::query()->create([
                'name' => $source->name,
                'guard_name' => $source->guard_name,
                'tenant_id' => $tenant->getKey(),
            ]);

            $copy->syncPermissions($source->permissions()->get());
        }
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
