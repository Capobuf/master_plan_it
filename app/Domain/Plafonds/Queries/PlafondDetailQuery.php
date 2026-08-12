<?php

namespace App\Domain\Plafonds\Queries;

use App\Domain\Budget\Enums\BudgetState;
use App\Domain\Economics\Data\ProjectedEconomicLine;
use App\Domain\Economics\Services\EconomicEngine;
use App\Domain\Plafonds\Data\PlafondProjectionSerializer;
use App\Domain\Plafonds\Services\PlafondReadAuthorizer;
use App\Domain\Plafonds\Services\PlafondRelationshipAuthorizer;
use App\Domain\Reporting\Queries\EconomicDatasetQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\User;
use DomainException;

final class PlafondDetailQuery
{
    /** @return array<string, mixed> */
    public function find(
        User $actor,
        TenantContext $context,
        int $plafondId,
        int $planningYearId,
    ): array {
        return $this->build($actor, $context, $plafondId, $planningYearId, true);
    }

    /**
     * A successful mutation already authorized its Expense ability. Its response
     * still needs the relationship-read abilities, but not the separate
     * expense.view ability that the write contract deliberately does not require.
     *
     * @return array<string, mixed>
     */
    public function findAfterMutation(
        User $actor,
        TenantContext $context,
        int $plafondId,
        int $planningYearId,
    ): array {
        return $this->build($actor, $context, $plafondId, $planningYearId, false);
    }

    /** @return array<string, mixed> */
    private function build(
        User $actor,
        TenantContext $context,
        int $plafondId,
        int $planningYearId,
        bool $authorizeExpenseRead,
    ): array {
        if ($authorizeExpenseRead) {
            app(PlafondReadAuthorizer::class)->authorize($actor, $context);
        } else {
            app(PlafondRelationshipAuthorizer::class)->authorize($actor, $context);
        }
        $expense = app(PlafondQuery::class)->find($context, $plafondId, $planningYearId);
        if ($authorizeExpenseRead) {
            app(PlafondReadAuthorizer::class)->authorize($actor, $context, $expense);
        }
        $expense->load(['planningYear:id,tenant_id,year_label,budget_state', 'costCenter:id,tenant_id,name']);
        $annual = app(EconomicEngine::class)->project(
            app(EconomicDatasetQuery::class)->execute($actor, $context, $planningYearId),
        );
        $projection = $annual->plafonds[$plafondId] ?? null;
        if ($projection === null || $expense->planningYear === null || $expense->costCenter === null) {
            throw new DomainException('ECONOMIC_RECONCILIATION_FAILED');
        }
        $rowMetadata = $expense->rows()
            ->orderBy('id')
            ->get(['id', 'position', 'lock_version'])
            ->keyBy('id');
        $allocation = array_map(function (ProjectedEconomicLine $line) use ($rowMetadata): array {
            $metadata = $rowMetadata->get($line->rowId);
            if ($metadata === null) {
                throw new DomainException('ECONOMIC_RECONCILIATION_FAILED');
            }

            return PlafondProjectionSerializer::allocationLine(
                $line,
                (int) $metadata->position,
                (int) $metadata->lock_version,
            );
        }, $projection->allocationLines);
        $budgetState = BudgetState::from((string) $expense->planningYear->getRawOriginal('budget_state'));

        return [
            'id' => (int) $expense->getKey(),
            'planning_year_id' => (int) $expense->planning_year_id,
            'economic_year_label' => (int) $expense->planningYear->year_label,
            'kind' => 'plafond',
            'title' => (string) $expense->title,
            'notes' => $expense->notes,
            'cost_center' => [
                'id' => (int) $expense->cost_center_id,
                'name' => (string) $expense->costCenter->name,
            ],
            'lock_version' => (int) $expense->lock_version,
            'budget_context' => [
                'state' => $budgetState->value,
                'read_only' => $budgetState !== BudgetState::Preparation,
            ],
            'currency' => $annual->currency,
            'basis' => $annual->basis,
            'measures' => PlafondProjectionSerializer::measures($projection),
            'allocation_adjustments' => $allocation,
            'covered_rows' => $authorizeExpenseRead
                ? array_map(
                    static fn (ProjectedEconomicLine $line): array => PlafondProjectionSerializer::coveredLine($line),
                    $projection->coveredLines,
                )
                : [],
        ];
    }
}
