<?php

namespace App\Domain\Expenses\Services;

use App\Domain\Economics\Data\AnnualEconomicProjection;
use App\Domain\Economics\Data\EconomicDataset;
use App\Domain\Economics\Data\EconomicLine;
use App\Domain\Economics\Services\EconomicEngine;
use App\Domain\Expenses\Data\SaveExpenseData;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Reporting\Queries\EconomicDatasetQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use DomainException;

/**
 * Replaces one aggregate in the authoritative annual dataset and delegates all
 * economic classification and arithmetic to EconomicEngine.
 */
final class ProposedExpenseProjection
{
    /**
     * @param  array{header: array<string, mixed>, rows: list<array<string, mixed>>}  $validated
     */
    public function project(
        User $actor,
        TenantContext $context,
        Tenant $tenant,
        SaveExpenseData $data,
        array $validated,
        ?Expense $currentExpense,
    ): AnnualEconomicProjection {
        $dataset = app(EconomicDatasetQuery::class)->execute(
            $actor,
            $context,
            $data->planningYearId,
        );
        $expenseId = $currentExpense instanceof Expense
            ? (int) $currentExpense->getKey()
            : -1;
        $costCenter = CostCenter::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereKey($data->costCenterId)
            ->first();
        if (! $costCenter instanceof CostCenter) {
            throw new DomainException('ECONOMIC_RECONCILIATION_FAILED');
        }

        $vendors = Vendor::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereKey(collect($validated['rows'])->pluck('vendor_id')->filter()->unique()->all())
            ->pluck('name', 'id');
        $fundedIds = collect($validated['rows'])
            ->pluck('funded_plafond_expense_id')
            ->filter()
            ->unique()
            ->values();
        $funded = Expense::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereKey($fundedIds->all())
            ->with('costCenter:id,tenant_id,name')
            ->get()
            ->keyBy(fn (Expense $expense): int => (int) $expense->getKey());
        $project = $data->projectId === null
            ? null
            : Project::query()
                ->where('tenant_id', $tenant->getKey())
                ->whereKey($data->projectId)
                ->first(['id', 'title', 'stage']);
        $existingRows = $currentExpense instanceof Expense
            ? $currentExpense->rows()->get()->keyBy(fn (ExpenseRow $row): int => (int) $row->getKey())
            : collect();
        $syntheticRowId = -1;
        $replacement = [];

        foreach ($validated['rows'] as $row) {
            $existing = $row['id'] === null ? null : $existingRows->get((int) $row['id']);
            $fundedPlafond = $row['funded_plafond_expense_id'] === null
                ? null
                : $funded->get((int) $row['funded_plafond_expense_id']);
            $rowId = $row['id'] === null ? $syntheticRowId-- : (int) $row['id'];
            $type = $row['type'] instanceof ExpenseType
                ? $row['type']->value
                : (string) $row['type'];
            $creatorId = $type === ExpenseType::AllocationAdjustment->value
                ? ($existing instanceof ExpenseRow && $existing->created_by_user_id !== null
                    ? (int) $existing->created_by_user_id
                    : (int) $actor->getKey())
                : null;
            $creatorName = $creatorId === (int) $actor->getKey()
                ? (string) $actor->name
                : ($existing instanceof ExpenseRow ? $existing->createdBy?->name : null);

            $replacement[] = new EconomicLine(
                expenseId: $expenseId,
                rowId: $rowId,
                expenseKind: $data->kind->value,
                type: $type,
                confirmationState: $existing instanceof ExpenseRow && $existing->confirmation_state !== null
                    ? (string) $existing->getRawOriginal('confirmation_state')
                    : null,
                costCenterId: $data->costCenterId,
                costCenterName: (string) $costCenter->name,
                net: (string) $row['net_amount'],
                vat: (string) $row['vat_amount'],
                gross: (string) $row['gross_amount'],
                fundedPlafondExpenseId: $row['funded_plafond_expense_id'] === null
                    ? null
                    : (int) $row['funded_plafond_expense_id'],
                spendDate: $row['spend_date'] === null ? null : (string) $row['spend_date'],
                periodStart: $row['period_start'] === null ? null : (string) $row['period_start'],
                periodEnd: $row['period_end'] === null ? null : (string) $row['period_end'],
                distribution: $row['distribution'] === null
                    ? null
                    : ($row['distribution'] instanceof \BackedEnum
                        ? (string) $row['distribution']->value
                        : (string) $row['distribution']),
                isExtra: (bool) $row['is_extra'],
                projectId: $data->projectId,
                projectStage: $project === null ? null : (string) $project->getRawOriginal('stage'),
                projectTitle: $project === null ? null : (string) $project->title,
                isCurrentPlanning: (bool) $row['is_current_planning'],
                description: (string) $row['description'],
                notes: $row['notes'] === null ? null : (string) $row['notes'],
                vendorId: $row['vendor_id'] === null ? null : (int) $row['vendor_id'],
                vendorName: $row['vendor_id'] === null ? null : $vendors->get((int) $row['vendor_id']),
                contractId: $data->contractId,
                expenseTitle: trim($data->title),
                createdByUserId: $creatorId,
                createdByUserName: $creatorName,
                fundedPlafondTitle: $fundedPlafond instanceof Expense ? (string) $fundedPlafond->title : null,
                fundedPlafondCostCenterId: $fundedPlafond instanceof Expense ? (int) $fundedPlafond->cost_center_id : null,
                fundedPlafondCostCenterName: $fundedPlafond instanceof Expense ? $fundedPlafond->costCenter?->name : null,
            );
        }

        $lines = array_values(array_filter(
            $dataset->lines,
            static fn (EconomicLine $line): bool => $line->expenseId !== $expenseId,
        ));

        return app(EconomicEngine::class)->project(new EconomicDataset(
            $dataset->scope,
            [...$lines, ...$replacement],
        ));
    }
}
