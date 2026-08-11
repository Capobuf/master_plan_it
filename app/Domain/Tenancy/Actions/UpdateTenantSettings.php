<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\BudgetBasis;
use App\Models\ApprovalOperation;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\TenantAbilityAuthorizer;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class UpdateTenantSettings
{
    private const FIELDS = ['name', 'timezone', 'default_vat_rate', 'budget_basis', 'deletion_reason_required'];

    public function __construct(
        private readonly TenantAbilityAuthorizer $authorizer,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    /** @param array<string, mixed> $changes */
    public function execute(User $actor, TenantContext $context, array $changes, int $expectedLockVersion, string $correlationId): Tenant
    {
        [$persistedActor] = $this->authorizer->authorize($actor, $context, 'tenant-settings.update');
        $unexpected = array_diff(array_keys($changes), self::FIELDS);
        if ($unexpected !== []) {
            throw ValidationException::withMessages(array_fill_keys($unexpected, 'This field is not allowed for Tenant settings.'));
        }

        $values = Validator::make($changes, [
            'name' => ['required', 'string', 'max:255', 'not_regex:/^\s*$/u'],
            'timezone' => ['required', 'string', 'timezone'],
            'default_vat_rate' => ['required', 'string', 'regex:/^[0-9]{1,10}(?:\.[0-9]{1,2})?$/D'],
            'budget_basis' => ['required', Rule::enum(BudgetBasis::class)],
            'deletion_reason_required' => ['required', 'boolean'],
        ])->validate();

        return DB::transaction(function () use ($context, $correlationId, $expectedLockVersion, $persistedActor, $values): Tenant {
            $tenant = Tenant::query()->whereKey($context->tenantId)->lockForUpdate()->firstOrFail();
            if ((int) $tenant->lock_version !== $expectedLockVersion) {
                throw new DomainException('STALE_VERSION');
            }
            if ($values['budget_basis'] !== $tenant->getRawOriginal('budget_basis')
                && ApprovalOperation::query()->where('tenant_id', $tenant->getKey())->exists()) {
                throw new DomainException('TENANT_BUDGET_BASIS_LOCKED');
            }

            $changedFields = array_keys(array_filter($values, static fn (mixed $value, string $key): bool => $value !== $tenant->getAttribute($key) && $value !== $tenant->getRawOriginal($key), ARRAY_FILTER_USE_BOTH));
            $occurredAt = CarbonImmutable::now('UTC');
            $updated = Tenant::query()->whereKey($tenant->getKey())->where('lock_version', $expectedLockVersion)->update([
                ...$values, 'lock_version' => $expectedLockVersion + 1, 'updated_at' => $occurredAt,
            ]);
            if ($updated !== 1) {
                throw new DomainException('STALE_VERSION');
            }

            $tenant->refresh();
            $this->auditRecorder->record('tenant.settings.updated', $correlationId,
                new AuditProperties(['changed_fields' => $changedFields]), $persistedActor,
                (int) $tenant->getKey(), $tenant, $occurredAt);

            return $tenant;
        });
    }
}
