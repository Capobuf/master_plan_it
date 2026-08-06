<?php

namespace App\Domain\Contracts\Actions;

use App\Domain\Contracts\Data\ExpectedContractOccurrence;
use App\Domain\Contracts\Queries\ExpectedContractOccurrenceQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\User;
use App\Policies\ContractPolicy;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ResumeAndGenerateOccurrence
{
    public function execute(User $actor, TenantContext $context, Contract $contract, string $sourceKey, string $correlationId): Expense
    {
        app(ContractPolicy::class)->resumeGeneration($actor,$contract)->authorize();
        app(ContractPolicy::class)->generateOccurrence($actor,$contract)->authorize();
        return DB::transaction(function () use ($actor, $context, $contract, $correlationId, $sourceKey): Expense {
            app(ResumeContractOccurrence::class)->execute($actor, $context, $contract, $sourceKey, $correlationId);
            $expected = collect(app(ExpectedContractOccurrenceQuery::class)->forContract($contract))->first(fn (ExpectedContractOccurrence $item) => hash_equals($item->sourceKey, $sourceKey));
            if (! $expected instanceof ExpectedContractOccurrence || $expected->expenseId !== null) { throw new DomainException('GENERATION_SOURCE_DUPLICATE'); }
            $resumed = new ExpectedContractOccurrence($expected->contractId, $expected->termId, $expected->planningYear, $expected->occurrenceDate, $expected->sourceKey, $expected->netAmount, $expected->vatAmount, $expected->grossAmount, false, null, null);
            return app(GenerateContractOccurrenceForYear::class)->generateExpected($actor, $context, $contract, $resumed, $correlationId);
        });
    }
}
