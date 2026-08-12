<?php

namespace App\Domain\Contracts\Actions;

use App\Domain\Contracts\Actions\Concerns\ManagesContracts;
use App\Domain\Contracts\Data\ExpectedContractOccurrence;
use App\Domain\Contracts\Queries\ExpectedContractOccurrenceQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\PlanningYear;
use App\Models\User;
use App\Policies\ContractPolicy;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ResumeAndGenerateOccurrence
{
    use ManagesContracts;

    public function execute(User $actor, TenantContext $context, Contract $contract, string $sourceKey, string $correlationId): Expense
    {
        app(ContractPolicy::class)->resumeGeneration($actor, $contract)->authorize();
        app(ContractPolicy::class)->generateOccurrence($actor, $contract)->authorize();

        $expected = collect(app(ExpectedContractOccurrenceQuery::class)->forContract($contract))->first(fn (ExpectedContractOccurrence $item) => hash_equals($item->sourceKey, $sourceKey));
        if (! $expected instanceof ExpectedContractOccurrence || $expected->expenseId !== null) {
            throw new DomainException('GENERATION_SOURCE_DUPLICATE');
        }

        return DB::transaction(function () use ($actor, $context, $contract, $correlationId, $expected, $sourceKey): Expense {
            $planningYearId = PlanningYear::query()->where('tenant_id', $context->tenantId)
                ->where('year_label', $expected->planningYear)->value('id');
            if ($planningYearId === null) {
                throw new DomainException('INVALID_GENERATION_YEAR');
            }
            $this->lockContractEconomicYears($context->tenantId, (int) $contract->getKey(), [(int) $planningYearId]);
            app(ResumeContractOccurrence::class)->execute($actor, $context, $contract, $sourceKey, $correlationId);
            $resumed = new ExpectedContractOccurrence($expected->contractId, $expected->termId, $expected->planningYear, $expected->occurrenceDate, $expected->sourceKey, $expected->netAmount, $expected->vatAmount, $expected->grossAmount, $expected->vatRate, false, null, null, null);

            return app(GenerateContractOccurrenceForYear::class)->generateExpected($actor, $context, $contract, $resumed, $correlationId);
        });
    }
}
