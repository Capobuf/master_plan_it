<?php

namespace App\Domain\Contracts\Actions;

use App\Domain\Contracts\Actions\Concerns\ManagesContracts;
use App\Domain\Contracts\Data\SaveContractData;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Contract;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class UpdateContract
{
    use ManagesContracts;

    public function execute(User $actor, TenantContext $context, Contract $target, SaveContractData $data, string $correlationId): Contract
    {
        $this->contractPolicy($context)->update($actor, $target)->authorize();
        [$actor, $tenant] = $this->persistedContractContext($actor, $context);

        return DB::transaction(function () use ($actor, $context, $correlationId, $data, $target, $tenant): Contract {
            $contract = Contract::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->find($target->getKey());
            if (! $contract instanceof Contract || $data->expectedLockVersion === null || $contract->lock_version !== $data->expectedLockVersion) {
                throw new DomainException('STALE_VERSION');
            }
            $changed = $this->saveContract($contract, $tenant, $data);
            if ($changed === []) {
                return $contract->fresh(['terms']);
            }
            $this->contractRevisions($actor, $context, RevisionOperation::Update, $correlationId, $contract, $changed);
            $this->contractAudit('contract.updated', $correlationId, $actor, $tenant, $contract, ['terms' => count($data->terms)]);

            return $contract->fresh(['terms']);
        });
    }
}
