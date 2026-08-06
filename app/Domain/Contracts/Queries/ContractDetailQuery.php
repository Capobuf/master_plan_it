<?php

namespace App\Domain\Contracts\Queries;

use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Contract;
use App\Models\User;
use App\Policies\ContractPolicy;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class ContractDetailQuery
{
    /** @return array{contract:Contract,occurrences:array} */
    public function find(User $actor, TenantContext $context, int $id): array
    {
        app(ContractPolicy::class)->viewAny($actor)->authorize();
        $contract = Contract::query()->where('tenant_id', $context->tenantId)->with(['vendor', 'costCenter', 'terms', 'expenses.rows'])->find($id);
        if (! $contract instanceof Contract) { throw (new ModelNotFoundException)->setModel(Contract::class, [$id]); }
        app(ContractPolicy::class)->view($actor, $contract)->authorize();
        return ['contract' => $contract, 'occurrences' => app(ExpectedContractOccurrenceQuery::class)->forContract($contract)];
    }
}
