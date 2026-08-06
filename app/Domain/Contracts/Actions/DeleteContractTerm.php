<?php

namespace App\Domain\Contracts\Actions;

use App\Domain\Contracts\Actions\Concerns\ManagesContracts;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Contract;
use App\Models\ContractTerm;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeleteContractTerm
{
    use ManagesContracts;
    public function execute(User $actor, TenantContext $context, Contract $target, ContractTerm $targetTerm, int $expectedLockVersion, ?string $reason, string $correlationId): void
    {
        $this->contractPolicy($context)->delete($actor, $target)->authorize();
        [$actor, $tenant] = $this->persistedContractContext($actor, $context);
        DB::transaction(function () use ($actor, $context, $correlationId, $expectedLockVersion, $reason, $target, $targetTerm, $tenant): void {
            $contract = Contract::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->find($target->getKey());
            $term = ContractTerm::query()->where('tenant_id', $tenant->getKey())->where('contract_id', $contract?->getKey())->lockForUpdate()->find($targetTerm->getKey());
            if (! $contract instanceof Contract || ! $term instanceof ContractTerm || $term->lock_version !== $expectedLockVersion) { throw new DomainException('STALE_VERSION'); }
            if ($tenant->deletion_reason_required && trim((string) $reason) === '') { throw ValidationException::withMessages(['deletion_reason' => 'A deletion reason is required.']); }
            $rows=$this->terminalizeTerm($term, $actor, $reason);
            $this->contractRevisions($actor, $context, RevisionOperation::Delete, $correlationId, $contract, [$term,...$rows], $reason);
            $this->contractAudit('contract.term-deleted', $correlationId, $actor, $tenant, $contract, ['term_id' => $term->getKey()]);
        });
    }
}
