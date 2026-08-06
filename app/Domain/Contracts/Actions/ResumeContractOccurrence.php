<?php

namespace App\Domain\Contracts\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\Contracts\Actions\Concerns\ManagesContracts;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Contract;
use App\Models\ContractGenerationException;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ResumeContractOccurrence
{
    use ManagesContracts;
    public function execute(User $actor, TenantContext $context, Contract $contract, string $sourceKey, string $correlationId): void
    {
        $this->contractPolicy($context)->resumeGeneration($actor, $contract)->authorize();
        [$actor, $tenant] = $this->persistedContractContext($actor, $context);
        DB::transaction(function()use($actor,$tenant,$contract,$sourceKey,$correlationId):void{Contract::query()->where('tenant_id',$tenant->getKey())->lockForUpdate()->findOrFail($contract->getKey());$exception=ContractGenerationException::query()->where('tenant_id',$tenant->getKey())->where('contract_id',$contract->getKey())->where('source_key',$sourceKey)->lockForUpdate()->first();if(!$exception instanceof ContractGenerationException){throw new DomainException('OCCURRENCE_NOT_SUPPRESSED');}$exception->delete();app(AuditRecorder::class)->record('contract.occurrence-resumed',$correlationId,new AuditProperties(['source_key'=>$sourceKey]),$actor,(int)$tenant->getKey(),$contract);});
    }
}
