<?php

namespace App\Domain\Contracts\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\Contracts\Actions\Concerns\ManagesContracts;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Contract;
use App\Models\ContractGenerationException;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class SuppressContractOccurrence
{
    use ManagesContracts;
    public function execute(User $actor, TenantContext $context, int $contractId, string $sourceKey, ?string $reason, string $correlationId): ContractGenerationException
    {
        $contract = Contract::query()->where('tenant_id', $context->tenantId)->find($contractId);
        if (! $contract instanceof Contract) { throw new DomainException('TENANT_RELATION_MISMATCH'); }
        $this->contractPolicy($context)->suppressGeneration($actor, $contract)->authorize();
        [$actor, $tenant] = $this->persistedContractContext($actor, $context);
        $expected = app(\App\Domain\Contracts\Queries\ExpectedContractOccurrenceQuery::class)->forContract($contract);
        if (! collect($expected)->contains(fn ($item) => hash_equals($item->sourceKey, $sourceKey))) { throw new DomainException('INVALID_OCCURRENCE'); }
        return DB::transaction(function()use($actor,$tenant,$contract,$sourceKey,$reason,$correlationId):ContractGenerationException{
            Contract::query()->where('tenant_id',$tenant->getKey())->lockForUpdate()->findOrFail($contract->getKey());
            $existing=ContractGenerationException::query()->where('tenant_id',$tenant->getKey())->where('source_key',$sourceKey)->lockForUpdate()->first();if($existing instanceof ContractGenerationException){return $existing;}
            $exception=ContractGenerationException::query()->create(['tenant_id'=>$tenant->getKey(),'source_key'=>$sourceKey,'contract_id'=>$contract->getKey(),'suppressed_by_user_id'=>$actor->getKey(),'suppressed_at'=>CarbonImmutable::now('UTC'),'reason'=>$reason]);
            app(AuditRecorder::class)->record('contract.occurrence-suppressed',$correlationId,new AuditProperties(['source_key'=>$sourceKey]),$actor,(int)$tenant->getKey(),$contract);return $exception;
        });
    }
}
