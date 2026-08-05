<?php

namespace App\Domain\Expenses\Queries;

use App\Domain\Expenses\Data\ExpenseDetail;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Expense;
use App\Models\User;
use App\Policies\ExpensePolicy;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

final class ExpenseDetailQuery
{
    public function find(User $actor, TenantContext $context, int $expenseId): ExpenseDetail
    {
        $policy = $this->policy($context);
        $policy->viewAny($actor)->authorize();

        $expense = Expense::query()
            ->where('tenant_id', $context->tenantId)
            ->whereKey($expenseId)
            ->first(['id', 'tenant_id']);

        if (! $expense instanceof Expense) {
            throw (new ModelNotFoundException)->setModel(Expense::class, [$expenseId]);
        }

        $policy->view($actor, $expense)->authorize();

        $header = DB::table('expenses')
            ->join('planning_years', function ($join): void {
                $join->on('planning_years.id', '=', 'expenses.planning_year_id')
                    ->on('planning_years.tenant_id', '=', 'expenses.tenant_id');
            })
            ->join('cost_centers', function ($join): void {
                $join->on('cost_centers.id', '=', 'expenses.cost_center_id')
                    ->on('cost_centers.tenant_id', '=', 'expenses.tenant_id');
            })
            ->where('expenses.tenant_id', $context->tenantId)
            ->where('expenses.id', $expenseId)
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
            ]);

        if ($header === null) {
            throw (new ModelNotFoundException)->setModel(Expense::class, [$expenseId]);
        }

        $rows = DB::table('expense_rows')
            ->where('tenant_id', $context->tenantId)
            ->where('expense_id', $expenseId)
            ->whereNull('deleted_at')
            ->orderBy('position')
            ->orderBy('id')
            ->get([
                'id',
                'position',
                'vendor_id',
                'type',
                'confirmation_state',
                'description',
                'net_amount',
                'vat_amount',
                'gross_amount',
            ])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'position' => (int) $row->position,
                'vendor_id' => $row->vendor_id === null ? null : (int) $row->vendor_id,
                'type' => (string) $row->type,
                'confirmation_state' => $row->confirmation_state === null ? null : (string) $row->confirmation_state,
                'description' => (string) $row->description,
                'net_amount' => $this->decimal($row->net_amount),
                'vat_amount' => $this->decimal($row->vat_amount),
                'gross_amount' => $this->decimal($row->gross_amount),
            ])
            ->all();

        $totals = DB::table('expense_rows')
            ->where('tenant_id', $context->tenantId)
            ->where('expense_id', $expenseId)
            ->whereNull('deleted_at')
            ->selectRaw('COALESCE(SUM(net_amount), 0) AS net_total')
            ->selectRaw('COALESCE(SUM(vat_amount), 0) AS vat_total')
            ->selectRaw('COALESCE(SUM(gross_amount), 0) AS gross_total')
            ->first();

        return new ExpenseDetail(
            id: (int) $header->id,
            planningYearId: (int) $header->planning_year_id,
            planningYearLabel: (int) $header->planning_year_label,
            costCenterId: (int) $header->cost_center_id,
            costCenterName: (string) $header->cost_center_name,
            kind: (string) $header->kind,
            title: (string) $header->title,
            notes: $header->notes === null ? null : (string) $header->notes,
            rows: $rows,
            netTotal: $this->decimal($totals?->net_total),
            vatTotal: $this->decimal($totals?->vat_total),
            grossTotal: $this->decimal($totals?->gross_total),
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

    private function decimal(mixed $value): string
    {
        $normalized = (string) ($value ?? '0');
        [$integer, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $fraction = substr(str_pad($fraction, 2, '0'), 0, 2);

        return ($integer === '-0' ? '0' : $integer).'.'.$fraction;
    }
}
