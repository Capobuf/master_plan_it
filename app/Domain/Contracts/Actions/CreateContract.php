<?php

namespace App\Domain\Contracts\Actions;

use App\Domain\Contracts\Actions\Concerns\ManagesContracts;
use App\Domain\Contracts\Data\SaveContractData;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateContract
{
    use ManagesContracts;
    public function execute(User $actor, TenantContext $context, SaveContractData $data, string $correlationId): Contract
    {
        $this->contractPolicy($context)->create($actor)->authorize();
        [$actor, $tenant] = $this->persistedContractContext($actor, $context);
        return DB::transaction(function () use ($actor, $context, $correlationId, $data, $tenant): Contract {
            $contract = new Contract;
            $changed = $this->saveContract($contract, $tenant, $data);
            $this->contractRevisions($actor, $context, RevisionOperation::Create, $correlationId, $contract, $changed);
            $this->contractAudit('contract.created', $correlationId, $actor, $tenant, $contract, ['terms' => count($data->terms)]);
            return $contract->fresh(['terms']);
        });
    }
}
