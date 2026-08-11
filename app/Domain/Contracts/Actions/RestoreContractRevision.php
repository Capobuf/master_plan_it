<?php

namespace App\Domain\Contracts\Actions;

use App\Domain\Contracts\Actions\Concerns\ManagesContracts;
use App\Domain\Contracts\Data\SaveContractData;
use App\Domain\Contracts\Data\SaveContractTermData;
use App\Domain\Contracts\Enums\BillingCycle;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Revisions\Queries\OperationalRevisionQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Contract;
use App\Models\ContractTerm;
use App\Models\RevisionBatch;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class RestoreContractRevision
{
    use ManagesContracts;

    public function execute(User $actor, TenantContext $context, Contract $target, RevisionBatch $source, int $expectedLockVersion, string $correlationId): Contract
    {
        $this->contractPolicy($context)->restoreRevision($actor, $target)->authorize();
        [$actor, $tenant] = $this->persistedContractContext($actor, $context);

        return DB::transaction(function () use ($actor, $context, $correlationId, $expectedLockVersion, $source, $target, $tenant): Contract {
            $contract = Contract::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->find($target->getKey());
            if (! $contract instanceof Contract || $contract->lock_version !== $expectedLockVersion) {
                throw new DomainException('STALE_VERSION');
            }
            $logical = app(OperationalRevisionQuery::class);
            $batch = $logical->findVisibleBatch($context, $contract, (int) $source->getKey());
            $state = $logical->snapshot($context, $contract, $batch);
            $snapshot = $state[$contract->getMorphClass()][(int) $contract->getKey()] ?? null;
            if (! is_array($snapshot)) {
                throw new DomainException('REVISION_RESTORE_INVALID');
            }

            $termType = (new ContractTerm)->getMorphClass();
            $historicalTerms = $state[$termType] ?? [];
            $currentTerms = $contract->terms()->lockForUpdate()->get()->keyBy(fn (ContractTerm $term): int => (int) $term->getKey());
            $termData = [];
            foreach ($historicalTerms as $termId => $contents) {
                $term = $currentTerms->get((int) $termId);
                if (! $term instanceof ContractTerm) {
                    throw new DomainException('REVISION_RESTORE_INVALID');
                }
                $cycle = BillingCycle::tryFrom((string) ($contents['billing_cycle'] ?? ''));
                if (! $cycle instanceof BillingCycle) {
                    throw new DomainException('REVISION_RESTORE_INVALID');
                }
                $termData[] = new SaveContractTermData(
                    id: (int) $term->getKey(),
                    localKey: (string) $term->source_rule_key,
                    effectiveStart: (string) ($contents['effective_start'] ?? ''),
                    effectiveEnd: (string) ($contents['effective_end'] ?? ''),
                    billingCycle: $cycle,
                    quantity: $this->nullableDecimal($contents['quantity'] ?? null),
                    unitPrice: $this->nullableDecimal($contents['unit_price'] ?? null),
                    enteredAmount: $this->legacyDecimal($contents['entered_amount'] ?? ''),
                    amountIncludesVat: (bool) ($contents['amount_includes_vat'] ?? false),
                    vatRate: $this->legacyDecimal($contents['vat_rate'] ?? ''),
                    autoRenew: (bool) ($contents['auto_renew'] ?? false),
                    expectedLockVersion: (int) $term->lock_version,
                );
            }

            $historicalIds = array_map('intval', array_keys($historicalTerms));
            $removed = $currentTerms->reject(fn (ContractTerm $term): bool => in_array((int) $term->getKey(), $historicalIds, true))->values();
            foreach ($removed as $term) {
                $term->delete();
            }

            $data = new SaveContractData(
                vendorId: (int) ($snapshot['vendor_id'] ?? 0),
                costCenterId: (int) ($snapshot['cost_center_id'] ?? 0),
                title: (string) ($snapshot['title'] ?? ''),
                description: $this->nullableString($snapshot['description'] ?? null),
                active: (bool) ($snapshot['active'] ?? false),
                renewalDate: $this->nullableString($snapshot['renewal_date'] ?? null),
                renewalNoticeDays: isset($snapshot['renewal_notice_days']) ? (int) $snapshot['renewal_notice_days'] : null,
                renewalNotes: $this->nullableString($snapshot['renewal_notes'] ?? null),
                expectedLockVersion: $expectedLockVersion,
                terms: $termData,
                projectId: isset($snapshot['project_id']) ? (int) $snapshot['project_id'] : null,
            );
            $changed = $this->saveContract($contract, $tenant, $data);
            if ($removed->isNotEmpty() && $changed === []) {
                $contract->forceFill(['lock_version' => $contract->lock_version + 1])->save();
            }
            $changed = [...$changed, ...$removed->all()];
            if ($changed === []) {
                throw new DomainException('REVISION_RESTORE_INVALID');
            }
            $this->contractRevisions($actor, $context, RevisionOperation::Restore, $correlationId, $contract, $changed, restoredFromBatchId: (int) $batch->getKey());
            $this->contractAudit('contract.restored', $correlationId, $actor, $tenant, $contract, ['restored_from_batch_id' => (int) $batch->getKey()]);

            return $contract->fresh(['terms']);
        });
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }

    private function nullableDecimal(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : $this->legacyDecimal($value);
    }

    private function legacyDecimal(mixed $value): string
    {
        $decimal = (string) $value;

        return preg_match('/^-?\d+(?:\.\d{1,2}0*)?$/D', $decimal) === 1
            ? bcadd($decimal, '0', 2)
            : $decimal;
    }
}
