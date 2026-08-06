<?php

namespace App\Domain\Contracts\Actions\Concerns;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\Contracts\Data\SaveContractData;
use App\Domain\Contracts\Data\SaveContractTermData;
use App\Domain\Money\Money;
use App\Domain\Money\Services\MoneyCalculator;
use App\Domain\Money\Services\VatCalculator;
use App\Domain\Revisions\Actions\BeginRevisionBatch;
use App\Domain\Revisions\Actions\LinkVersionToRevisionBatch;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Models\Contract;
use App\Models\ContractTerm;
use App\Models\CostCenter;
use App\Models\ExpenseRow;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Version;
use App\Policies\ContractPolicy;
use App\Support\Authorization\PlatformAdministrator;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

trait ManagesContracts
{
    private function contractPolicy(TenantContext $context): ContractPolicy
    {
        return new ContractPolicy($context, app(PermissionRegistrar::class), app(PlatformAdministrator::class));
    }

    /** @return array{User,Tenant} */
    private function persistedContractContext(User $actor, TenantContext $context): array
    {
        $actorKey=$actor->getKey();$actorOriginal=$actor->getRawOriginal($actor->getKeyName());$contextActorKey=$context->actor->getKey();$contextActorOriginal=$context->actor->getRawOriginal($context->actor->getKeyName());$tenantKey=$context->tenant->getKey();$tenantOriginal=$context->tenant->getRawOriginal($context->tenant->getKeyName());
        if(!$actor->exists||$actorKey===null||$actorKey!==$actorOriginal||!$context->actor->exists||$contextActorKey!==$contextActorOriginal||$contextActorKey!==$actorKey||!$context->tenant->exists||$tenantKey!==$tenantOriginal||(int)$tenantKey!==$context->tenantId){throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');}
        $user = User::query()->whereKey($actor->getRawOriginal($actor->getKeyName()))->where('is_active', true)->first();
        $tenant = Tenant::query()->whereKey($context->tenantId)->first();
        if (! $user instanceof User || ! $tenant instanceof Tenant) { throw new AuthorizationException('TENANT_CONTEXT_REQUIRED'); }
        if ($tenant->state !== TenantState::Active) { throw new AuthorizationException('TENANT_INACTIVE'); }
        if ($user->tenant_id !== null && (int) $user->tenant_id !== (int) $tenant->getKey()) { throw new AuthorizationException('PERMISSION_DENIED'); }
        return [$user, $tenant];
    }

    /** @return list<Contract|ContractTerm> */
    private function saveContract(Contract $contract, Tenant $tenant, SaveContractData $data): array
    {
        if (trim($data->title) === '' || mb_strlen($data->title) > 255) { $this->contractFail('title', 'A contract title is required.'); }
        if (! Vendor::query()->where('tenant_id', $tenant->getKey())->whereKey($data->vendorId)->exists()) { $this->contractFail('vendor_id', 'The vendor is invalid.'); }
        if (! CostCenter::query()->where('tenant_id', $tenant->getKey())->whereKey($data->costCenterId)->exists()) { $this->contractFail('cost_center_id', 'The cost center is invalid.'); }
        if ($data->terms === []) { $this->contractFail('terms', 'At least one term is required.'); }

        $contract->fill(['vendor_id' => $data->vendorId, 'cost_center_id' => $data->costCenterId, 'title' => trim($data->title), 'description' => $data->description, 'active' => $data->active, 'renewal_date' => $data->renewalDate, 'renewal_notice_days' => $data->renewalNoticeDays, 'renewal_notes' => $data->renewalNotes]);
        $contract->tenant_id = $tenant->getKey();
        $contract->save();
        $existing = $contract->terms()->lockForUpdate()->get();
        $existing = $existing->keyBy('id');
        $periods = [];
        $kept = [];
        $changed = [$contract];
        foreach ($data->terms as $index => $termData) {
            if (! $termData instanceof SaveContractTermData) { $this->contractFail("terms.{$index}", 'The term is invalid.'); }
            try {$start = CarbonImmutable::parse($termData->effectiveStart)->startOfDay();$end = CarbonImmutable::parse($termData->effectiveEnd)->startOfDay();}catch(\Throwable){$this->contractFail("terms.{$index}.effective_start",'The term dates are invalid.');}
            if ($start->greaterThan($end)) { $this->contractFail("terms.{$index}.effective_end", 'The term end must not precede its start.'); }
            foreach ($periods as [$otherStart, $otherEnd]) {
                if ($start->lessThanOrEqualTo($otherEnd) && $end->greaterThanOrEqualTo($otherStart)) { throw new DomainException('CONTRACT_TERM_OVERLAP'); }
            }
            $periods[] = [$start, $end];
            $term = $termData->id === null ? new ContractTerm : $existing->get($termData->id);
            if (! $term instanceof ContractTerm) { throw new DomainException('TENANT_RELATION_MISMATCH'); }
            if ($term->exists && (int) $term->lock_version !== (int) $termData->expectedLockVersion) { throw new DomainException('STALE_VERSION'); }
            $entered = Money::fromDecimal($termData->enteredAmount, (string) $tenant->currency_code)->amount();
            if ($termData->unitPrice !== null && trim($termData->unitPrice) !== '' && bccomp($termData->unitPrice, '0', 6) !== 0) {
                if ($termData->quantity === null) { $this->contractFail("terms.{$index}.quantity", 'Quantity is required with a unit price.'); }
                $entered = (new MoneyCalculator)->multiply(Money::fromDecimal($termData->unitPrice, (string) $tenant->currency_code), $termData->quantity)->amount();
            }
            $rate = trim($termData->vatRate) === '' ? (string) $tenant->default_vat_rate : $termData->vatRate;
            $breakdown = $termData->amountIncludesVat ? (new VatCalculator)->fromIncludedAmount(Money::fromDecimal($entered, (string) $tenant->currency_code), $rate) : (new VatCalculator)->fromExcludedAmount(Money::fromDecimal($entered, (string) $tenant->currency_code), $rate);
            $term->fill(['effective_start' => $start->toDateString(), 'effective_end' => $end->toDateString(), 'billing_cycle' => $termData->billingCycle, 'quantity' => $termData->quantity, 'unit_price' => $termData->unitPrice, 'entered_amount' => $entered, 'amount_includes_vat' => $termData->amountIncludesVat, 'vat_rate' => $rate, 'net_amount' => $breakdown->net()->amount(), 'vat_amount' => $breakdown->vat()->amount(), 'gross_amount' => $breakdown->gross()->amount(), 'auto_renew' => $termData->autoRenew]);
            if (! $term->exists) { $term->tenant_id = $tenant->getKey(); $term->contract_id = $contract->getKey(); $term->source_rule_key = (string) Str::uuid(); }
            else { $term->lock_version++; }
            $term->save();
            $kept[(int) $term->getKey()] = true;
            $changed[] = $term;
        }
        foreach ($existing as $term) {
            if (! isset($kept[(int) $term->getKey()])) { throw new DomainException('STALE_VERSION'); }
        }
        return $changed;
    }

    /** @return list<ExpenseRow> */
    private function terminalizeTerm(Contract $contract, ContractTerm $term, User $actor, ?string $reason, CarbonImmutable $deletedAt, bool $contractIsDeleted): array
    {
        $rows = ExpenseRow::query()
            ->where('tenant_id', $contract->tenant_id)
            ->where('contract_term_id', $term->getKey())
            ->whereNotNull('source_key')
            ->lockForUpdate()
            ->get();

        foreach ($rows as $row) {
            if ($row->source_deleted_contract_id === null) {
                $row->forceFill([
                    'source_deleted_contract_id' => $contract->getKey(),
                    'source_deleted_contract_title' => $contract->title,
                ]);
            }
            if ($row->source_term_deleted_at === null) {
                $row->forceFill([
                    'source_deleted_term_id' => $term->getKey(),
                    'source_deleted_term_rule_key' => $term->source_rule_key,
                    'source_deleted_term_start' => $term->effective_start,
                    'source_deleted_term_end' => $term->effective_end,
                    'source_term_deleted_at' => $deletedAt,
                    'source_term_deletion_reason' => $reason,
                ]);
            }
            if ($contractIsDeleted && $row->source_contract_deleted_at === null) {
                $row->forceFill([
                    'source_contract_deleted_at' => $deletedAt,
                    'source_contract_deletion_reason' => $reason,
                ]);
            }
            if ($row->is_system_managed) {
                $row->forceFill(['is_system_managed' => false, 'manual_override_at' => $deletedAt]);
            }
            if ($row->isDirty()) {
                $row->forceFill(['lock_version' => $row->lock_version + 1])->save();
            }
        }

        $term->fill([
            'deleted_by_user_id' => $actor->getKey(),
            'deleted_by_at' => $deletedAt,
            'deletion_reason' => $reason,
            'lock_version' => $term->lock_version + 1,
        ])->save();
        $term->delete();
        return $rows->all();
    }

    /** @return list<ExpenseRow> */
    private function terminalizeContractSources(Contract $contract, ?string $reason, CarbonImmutable $deletedAt): array
    {
        $rows = ExpenseRow::query()
            ->where('tenant_id', $contract->tenant_id)
            ->whereNotNull('source_key')
            ->whereHas('expense', fn ($query) => $query->where('tenant_id', $contract->tenant_id)->where('contract_id', $contract->getKey()))
            ->lockForUpdate()
            ->get();

        foreach ($rows as $row) {
            if ($row->source_deleted_contract_id === null) {
                $row->forceFill([
                    'source_deleted_contract_id' => $contract->getKey(),
                    'source_deleted_contract_title' => $contract->title,
                ]);
            }
            if ($row->source_contract_deleted_at === null) {
                $row->forceFill([
                    'source_contract_deleted_at' => $deletedAt,
                    'source_contract_deletion_reason' => $reason,
                ]);
            }
            if ($row->is_system_managed) {
                $row->forceFill(['is_system_managed' => false, 'manual_override_at' => $deletedAt]);
            }
            if ($row->isDirty()) {
                $row->forceFill(['lock_version' => $row->lock_version + 1])->save();
            }
        }

        return $rows->filter(fn (ExpenseRow $row): bool => $row->wasChanged())->all();
    }

    private function deletionReason(Tenant $tenant, ?string $reason): ?string
    {
        $normalized = trim((string) $reason);
        if (mb_strlen($normalized) > 500) {
            $this->contractFail('deletion_reason', 'The deletion reason may not exceed 500 characters.');
        }
        if ($tenant->deletion_reason_required && $normalized === '') {
            $this->contractFail('deletion_reason', 'A deletion reason is required.');
        }

        return $normalized === '' ? null : $normalized;
    }

    /** @param list<Contract|ContractTerm|ExpenseRow> $models */
    private function contractRevisions(User $actor, TenantContext $context, RevisionOperation $operation, string $correlationId, Contract $contract, array $models, ?string $reason = null): void
    {
        $batch = app(BeginRevisionBatch::class)->execute($actor, $context, $operation, $reason, $correlationId, $contract, null);
        $sequence = 1;
        $seen = [];
        foreach ($models as $model) {
            $identity = $model->getMorphClass().'#'.$model->getKey();
            if (isset($seen[$identity])) {
                continue;
            }
            $seen[$identity] = true;
            $version = $model->latestVersions()->first();
            if ($version instanceof Version) { app(LinkVersionToRevisionBatch::class)->execute($batch, $version, $sequence++); }
        }
    }

    /** @param array<string, mixed> $properties */
    private function contractAudit(string $event, string $correlationId, User $actor, Tenant $tenant, Contract $contract, array $properties = []): void
    {
        app(AuditRecorder::class)->record($event, $correlationId, new AuditProperties($properties), $actor, (int) $tenant->getKey(), $contract);
    }

    private function contractFail(string $field, string $message): never { throw ValidationException::withMessages([$field => $message]); }
}
