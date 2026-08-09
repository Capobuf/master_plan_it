<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Budget\Actions\ApplyBudgetApproval;
use App\Domain\Budget\Actions\CloseAnnualBudget;
use App\Domain\Budget\Data\ApplyApprovalData;
use App\Domain\Budget\Data\ApprovalChangeData;
use App\Domain\Budget\Queries\AnnualBudgetQuery;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AnnualBudgetResource;
use App\Models\PlanningYear;
use Illuminate\Http\Request;

final class BudgetLifecycleController extends Controller
{
    public function approve(Request $request, int $planningYear, ApplyBudgetApproval $action, AnnualBudgetQuery $query): AnnualBudgetResource
    {
        $validated = $request->validate([
            'budget_lock_version' => ['required', 'integer', 'min:1'],
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.expense_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.expense_lock_version' => ['required', 'integer', 'min:1'],
            'items.*.approved_amount' => ['required', 'string', 'regex:/^\d+(?:\.\d{1,2})?$/D'],
        ]);
        $context = $this->tenantContext($request);
        /** @var PlanningYear $year */
        $year = TenantOwnedRecordQuery::findOrFail($context, PlanningYear::class, $planningYear);
        $action->execute(
            $this->actor($request),
            $context,
            $year,
            new ApplyApprovalData(
                (int) $validated['budget_lock_version'],
                (string) $validated['effective_date'],
                $validated['reason'] ?? null,
                array_map(static fn (array $item): ApprovalChangeData => new ApprovalChangeData(
                    (int) $item['expense_id'],
                    (int) $item['expense_lock_version'],
                    (string) $item['approved_amount'],
                ), $validated['items']),
            ),
            $this->correlationId($request),
        );

        return AnnualBudgetResource::make($query->execute($this->actor($request), $context, $planningYear));
    }

    public function close(Request $request, int $planningYear, CloseAnnualBudget $action, AnnualBudgetQuery $query): AnnualBudgetResource
    {
        $validated = $request->validate(['lock_version' => ['required', 'integer', 'min:1']]);
        $context = $this->tenantContext($request);
        /** @var PlanningYear $year */
        $year = TenantOwnedRecordQuery::findOrFail($context, PlanningYear::class, $planningYear);
        $action->execute($this->actor($request), $context, $year, (int) $validated['lock_version'], $this->correlationId($request));

        return AnnualBudgetResource::make($query->execute($this->actor($request), $context, $planningYear));
    }
}
