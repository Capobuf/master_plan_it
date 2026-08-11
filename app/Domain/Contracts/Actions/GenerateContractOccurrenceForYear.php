<?php

namespace App\Domain\Contracts\Actions;

use App\Domain\Contracts\Actions\Concerns\ManagesContracts;
use App\Domain\Contracts\Data\ExpectedContractOccurrence;
use App\Domain\Contracts\Queries\ExpectedContractOccurrenceQuery;
use App\Domain\Expenses\Actions\Concerns\ManagesExpenseAggregate;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Money\Money;
use App\Domain\Money\Services\VatCalculator;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Contract;
use App\Models\ContractTerm;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class GenerateContractOccurrenceForYear
{
    use ManagesContracts, ManagesExpenseAggregate;

    public function execute(User $actor, TenantContext $context, Contract $contract, int $year, string $correlationId): Expense
    {
        $this->contractPolicy($context)->generateOccurrence($actor, $contract)->authorize();
        [$actor, $tenant] = $this->persistedContractContext($actor, $context);

        return DB::transaction(function () use ($actor, $context, $contract, $correlationId, $tenant, $year): Expense {
            $contract = Contract::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->find($contract->getKey());
            if (! $contract instanceof Contract) {
                throw new DomainException('GENERATION_NOT_APPLICABLE');
            }
            $planningYear = PlanningYear::query()->where('tenant_id', $tenant->getKey())->where('year_label', $year)->first();
            if (! $planningYear instanceof PlanningYear) {
                throw new DomainException('INVALID_GENERATION_YEAR');
            }
            $expected = app(ExpectedContractOccurrenceQuery::class)->forContract($contract, $year);
            if ($expected === []) {
                throw new DomainException('GENERATION_NOT_APPLICABLE');
            }
            $candidate = collect($expected)->first(fn (ExpectedContractOccurrence $item) => ! $item->suppressed && $item->expenseId === null);
            if (! $candidate instanceof ExpectedContractOccurrence) {
                if (collect($expected)->contains(fn (ExpectedContractOccurrence $item) => $item->suppressed && $item->expenseId === null)) {
                    throw new DomainException('GENERATION_SUPPRESSED');
                }
                throw new DomainException('GENERATION_SOURCE_DUPLICATE');
            }

            return $this->generateExpected($actor, $context, $contract, $candidate, $correlationId);
        });
    }

    public function generateExpected(User $actor, TenantContext $context, Contract $contract, ExpectedContractOccurrence $expected, string $correlationId): Expense
    {
        if ($expected->suppressed) {
            throw new DomainException('GENERATION_SUPPRESSED');
        }
        if (ExpenseRow::query()->where('tenant_id', $context->tenantId)->where('source_key', $expected->sourceKey)->exists()) {
            throw new DomainException('GENERATION_SOURCE_DUPLICATE');
        }
        $planningYear = PlanningYear::query()->where('tenant_id', $context->tenantId)->where('year_label', $expected->planningYear)->first();
        $term = ContractTerm::query()->where('tenant_id', $context->tenantId)->where('contract_id', $contract->getKey())->find($expected->termId);
        if (! $planningYear instanceof PlanningYear || ! $term instanceof ContractTerm || $contract->deleted_at !== null || ! $contract->active) {
            throw new DomainException('TERMINAL_DELETION');
        }
        $expense = new Expense;
        $expense->forceFill([
            'tenant_id' => $context->tenantId,
            'planning_year_id' => $planningYear->getKey(),
            'cost_center_id' => $contract->cost_center_id,
            'kind' => ExpenseKind::Ordinary,
            'title' => $contract->title.' — '.$expected->occurrenceDate,
            'notes' => 'Generated from contract.',
            'project_id' => $contract->project_id,
            'contract_id' => $contract->getKey(),
        ])->save();
        $row = new ExpenseRow;
        $row->forceFill([
            'tenant_id' => $context->tenantId, 'expense_id' => $expense->getKey(), 'position' => 1, 'vendor_id' => $contract->vendor_id,
            'type' => ExpenseType::Quote, 'confirmation_state' => null, 'confirmed_by_user_id' => null, 'confirmed_at' => null,
            'is_system_managed' => true, 'manual_override_at' => null, 'contract_term_id' => $term->getKey(), 'contract_source_rule_key' => $term->source_rule_key,
            'contract_occurrence_date' => $expected->occurrenceDate, 'source_key' => $expected->sourceKey, 'description' => $contract->title,
            'quantity' => null, 'unit_price' => null, 'entered_amount' => $expected->netAmount, 'amount_includes_vat' => false,
            'vat_rate' => (new VatCalculator)->rateFromAmounts(
                Money::fromDecimal($expected->netAmount, $context->currencyCode),
                Money::fromDecimal($expected->vatAmount, $context->currencyCode),
            ), 'net_amount' => $expected->netAmount, 'vat_amount' => $expected->vatAmount, 'gross_amount' => $expected->grossAmount,
            'is_extra' => false, 'funded_plafond_expense_id' => null, 'spend_date' => null, 'period_start' => null, 'period_end' => null,
            'distribution' => null, 'external_reference' => null,
        ]);
        $row->save();
        $this->revisions($actor, $context, RevisionOperation::Create, $correlationId, $expense, [$expense, $row]);
        $this->audit('contract.occurrence-generated', $correlationId, $actor, $context->tenant, $expense, ['contract_id' => $contract->getKey(), 'source_key' => $expected->sourceKey]);

        return $expense->fresh(['rows']);
    }
}
