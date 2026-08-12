<?php

namespace App\Domain\Expenses\Queries;

use App\Domain\Economics\Data\AnnualEconomicProjection;
use App\Domain\Economics\Data\EconomicMeasure;
use App\Domain\Economics\Services\EconomicEngine;
use App\Domain\Expenses\Data\ExpenseDetail;
use App\Domain\Expenses\Services\ExpenseRelationshipAuthorizer;
use App\Domain\Plafonds\Data\PlafondProjectionSerializer;
use App\Domain\Reporting\Queries\EconomicDatasetQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Models\Expense;
use App\Models\User;
use App\Policies\ExpensePolicy;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

final class ExpenseDetailQuery
{
    public function find(User $actor, TenantContext $context, int $expenseId, ?int $planningYearId = null): ExpenseDetail
    {
        $policy = $this->policy($context);
        $policy->viewAny($actor)->authorize();

        $expense = TenantOwnedRecordQuery::forTenant($context, Expense::class)
            ->whereKey($expenseId)
            ->when($planningYearId !== null, fn ($query) => $query->where('planning_year_id', $planningYearId))
            ->first(['id', 'tenant_id', 'project_id', 'contract_id']);

        if (! $expense instanceof Expense) {
            throw (new ModelNotFoundException)->setModel(Expense::class, [$expenseId]);
        }

        $policy->view($actor, $expense)->authorize();
        app(ExpenseRelationshipAuthorizer::class)->authorize(
            $actor,
            $context,
            $expense->project_id !== null,
            $expense->contract_id !== null,
        );

        $header = DB::table('expenses')
            ->join('planning_years', function ($join): void {
                $join->on('planning_years.id', '=', 'expenses.planning_year_id')
                    ->on('planning_years.tenant_id', '=', 'expenses.tenant_id');
            })
            ->join('cost_centers', function ($join): void {
                $join->on('cost_centers.id', '=', 'expenses.cost_center_id')
                    ->on('cost_centers.tenant_id', '=', 'expenses.tenant_id');
            })
            ->leftJoin('projects', function ($join): void {
                $join->on('projects.id', '=', 'expenses.project_id')
                    ->on('projects.tenant_id', '=', 'expenses.tenant_id');
            })
            ->leftJoin('contracts', function ($join): void {
                $join->on('contracts.id', '=', 'expenses.contract_id')
                    ->on('contracts.tenant_id', '=', 'expenses.tenant_id');
            })
            ->where('expenses.tenant_id', $context->tenantId)
            ->where('expenses.id', $expenseId)
            ->when($planningYearId !== null, fn ($query) => $query->where('expenses.planning_year_id', $planningYearId))
            ->whereNull('expenses.deleted_at')
            ->first([
                'expenses.id',
                'expenses.planning_year_id',
                'planning_years.year_label as planning_year_label',
                'expenses.cost_center_id',
                'cost_centers.name as cost_center_name',
                'expenses.kind',
                'expenses.title',
                'expenses.notes',
                'expenses.project_id',
                'projects.title as project_title',
                'expenses.contract_id',
                'contracts.title as contract_title',
                'planning_years.budget_state',
                'expenses.current_planning_row_id',
                'expenses.lock_version',
                'projects.deleted_at as project_deleted_at',
                'contracts.deleted_at as contract_deleted_at',
            ]);

        if ($header === null) {
            throw (new ModelNotFoundException)->setModel(Expense::class, [$expenseId]);
        }

        $rows = DB::table('expense_rows')
            ->leftJoin('vendors', function ($join): void {
                $join->on('vendors.id', '=', 'expense_rows.vendor_id')
                    ->on('vendors.tenant_id', '=', 'expense_rows.tenant_id');
            })
            ->where('expense_rows.tenant_id', $context->tenantId)
            ->where('expense_rows.expense_id', $expenseId)
            ->whereNull('expense_rows.deleted_at')
            ->orderBy('expense_rows.position')
            ->orderBy('expense_rows.id')
            ->get([
                'expense_rows.id', 'expense_rows.position', 'expense_rows.vendor_id',
                'vendors.name as vendor_name', 'expense_rows.type', 'expense_rows.description', 'expense_rows.notes',
                'expense_rows.quantity', 'expense_rows.unit_price', 'expense_rows.entered_amount',
                'expense_rows.amount_includes_vat', 'expense_rows.vat_rate', 'expense_rows.is_extra',
                'expense_rows.funded_plafond_expense_id', 'expense_rows.spend_date',
                'expense_rows.external_reference', 'expense_rows.lock_version',
                'expense_rows.is_system_managed', 'expense_rows.source_key',
                'expense_rows.contract_term_id', 'expense_rows.net_amount',
                'expense_rows.vat_amount', 'expense_rows.gross_amount',
            ])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'position' => (int) $row->position,
                'vendor_id' => $row->vendor_id === null ? null : (int) $row->vendor_id,
                'vendor_name' => $row->vendor_name === null ? null : (string) $row->vendor_name,
                'type' => (string) $row->type,
                'is_current_planning' => (int) $header->current_planning_row_id === (int) $row->id,
                'description' => (string) $row->description,
                'notes' => $row->notes === null ? null : (string) $row->notes,
                'quantity' => $row->quantity === null ? null : $this->decimal($row->quantity),
                'unit_price' => $row->unit_price === null ? null : $this->decimal($row->unit_price),
                'entered_amount' => $row->entered_amount === null ? null : $this->decimal($row->entered_amount),
                'amount_includes_vat' => (bool) $row->amount_includes_vat,
                'vat_rate' => $this->decimal($row->vat_rate),
                'is_extra' => (bool) $row->is_extra,
                'funded_plafond_expense_id' => $row->funded_plafond_expense_id === null ? null : (int) $row->funded_plafond_expense_id,
                'spend_date' => $row->spend_date === null ? null : (string) $row->spend_date,
                'external_reference' => $row->external_reference === null ? null : (string) $row->external_reference,
                'lock_version' => (int) $row->lock_version,
                'is_system_managed' => (bool) $row->is_system_managed,
                'generated' => $row->source_key !== null,
                'contract_term_id' => $row->contract_term_id === null ? null : (int) $row->contract_term_id,
                'amount' => null,
            ])
            ->all();

        /** @var AnnualEconomicProjection $annual */
        $annual = app(EconomicEngine::class)->project(
            app(EconomicDatasetQuery::class)->execute($actor, $context, (int) $header->planning_year_id),
        );
        $expenseProjection = $annual->expenses[$expenseId] ?? null;
        if ($expenseProjection === null) {
            throw new \DomainException('ECONOMIC_RECONCILIATION_FAILED');
        }
        $projectedLines = collect($expenseProjection->lines)->keyBy('rowId');
        $rows = array_map(function (array $row) use ($annual, $projectedLines): array {
            $line = $projectedLines->get($row['id']);
            if ($line === null) {
                throw new \DomainException('ECONOMIC_RECONCILIATION_FAILED');
            }
            $row['amount'] = $this->measure($line->amount);
            $plafondId = $row['funded_plafond_expense_id'];
            if ($plafondId === null) {
                $row['funded_plafond'] = null;

                return $row;
            }
            $plafond = $annual->plafonds[$plafondId] ?? null;
            if ($plafond === null) {
                throw new \DomainException('ECONOMIC_RECONCILIATION_FAILED');
            }
            $row['funded_plafond'] = [
                'id' => $plafond->plafondExpenseId,
                'title' => $plafond->title,
                'cost_center' => [
                    'id' => $plafond->costCenterId,
                    'name' => $plafond->costCenterName,
                ],
                'currency' => $plafond->currency,
                'basis' => $plafond->basis,
                'measures' => PlafondProjectionSerializer::measures($plafond),
            ];

            return $row;
        }, $rows);

        $revisionActivity = $policy->viewRevisions($actor, $expense)->allowed()
            ? app(ExpenseRevisionQuery::class)->history($actor, $context, $expense)->values()->all()
            : [];

        return new ExpenseDetail(
            id: (int) $header->id,
            planningYearId: (int) $header->planning_year_id,
            economicYearLabel: (int) $header->planning_year_label,
            costCenterId: (int) $header->cost_center_id,
            costCenterName: (string) $header->cost_center_name,
            kind: (string) $header->kind,
            title: (string) $header->title,
            notes: $header->notes === null ? null : (string) $header->notes,
            projectId: $header->project_id === null ? null : (int) $header->project_id,
            projectTitle: $header->project_title === null ? null : (string) $header->project_title,
            projectCurrent: $header->project_id !== null && $header->project_deleted_at === null,
            contractId: $header->contract_id === null ? null : (int) $header->contract_id,
            contractTitle: $header->contract_title === null ? null : (string) $header->contract_title,
            contractCurrent: $header->contract_id !== null && $header->contract_deleted_at === null,
            budgetState: (string) $header->budget_state,
            currentPlanningRowId: $header->current_planning_row_id === null ? null : (int) $header->current_planning_row_id,
            lockVersion: (int) $header->lock_version,
            currency: $annual->currency,
            basis: $annual->basis,
            totals: [
                'current_planning' => $this->measure($expenseProjection->currentPlanning),
                'actual' => $this->measure($expenseProjection->actual),
            ],
            rows: $rows,
            revisionActivity: $revisionActivity,
        );
    }

    private function policy(TenantContext $context): ExpensePolicy
    {
        return new ExpensePolicy(
            $context,
            app(PermissionRegistrar::class),
            app(PlatformAdministrator::class),
        );
    }

    private function decimal(mixed $value, int $scale = 2): string
    {
        $normalized = (string) ($value ?? '0');
        [$integer, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $fraction = substr(str_pad($fraction, $scale, '0'), 0, $scale);

        return ($integer === '-0' ? '0' : $integer).'.'.$fraction;
    }

    /** @return array{net: string, vat: string, gross: string, official: string} */
    private function measure(EconomicMeasure $measure): array
    {
        return ['net' => $measure->net, 'vat' => $measure->vat, 'gross' => $measure->gross, 'official' => $measure->official];
    }
}
