<?php

namespace App\Domain\Contracts\Actions;

use App\Domain\Attachments\Actions\PurgeAttachments;
use App\Domain\Contracts\Actions\Concerns\ManagesContracts;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Contract;
use App\Models\ContractTerm;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class DeleteContract
{
    use ManagesContracts;

    public function execute(User $actor, TenantContext $context, Contract $target, int $expectedLockVersion, ?string $reason, string $correlationId): void
    {
        $this->contractPolicy($context)->delete($actor, $target)->authorize();
        [$actor, $tenant] = $this->persistedContractContext($actor, $context);
        DB::transaction(function () use ($actor, $context, $correlationId, $expectedLockVersion, $reason, $target, $tenant): void {
            $contract = Contract::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->find($target->getKey());
            if (! $contract instanceof Contract || $contract->lock_version !== $expectedLockVersion) {
                throw new DomainException('STALE_VERSION');
            }
            $reason = $this->deletionReason($tenant, $reason);
            $deletedAt = now('UTC')->toImmutable();
            $changed = [$contract];
            foreach (ContractTerm::query()->where('tenant_id', $tenant->getKey())->where('contract_id', $contract->getKey())->lockForUpdate()->get() as $term) {
                $changed[] = $term;
                $changed = [...$changed, ...$this->terminalizeTerm($contract, $term, $actor, $reason, $deletedAt, true)];
            }
            $changed = [...$changed, ...$this->terminalizeContractSources($contract, $reason, $deletedAt)];
            $contract->fill(['active' => false, 'deleted_by_user_id' => $actor->getKey(), 'deleted_by_at' => $deletedAt, 'deletion_reason' => $reason, 'lock_version' => $contract->lock_version + 1])->save();
            $this->contractRevisions($actor, $context, RevisionOperation::Delete, $correlationId, $contract, $changed, $reason);
            $this->contractAudit('contract.deleted', $correlationId, $actor, $tenant, $contract);
            $contract->delete();
            app(PurgeAttachments::class)->forParent($actor, $context, $contract, $correlationId);
        });
    }
}
