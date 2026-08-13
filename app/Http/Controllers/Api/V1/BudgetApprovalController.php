<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Budget\Actions\ApproveBudgetProposal;
use App\Domain\Budget\Data\ApproveBudgetProposalData;
use App\Domain\Budget\Queries\BudgetApprovalPreviewQuery;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BudgetApprovalSummaryResource;
use App\Http\Resources\Api\V1\BudgetProposalResource;
use App\Models\PlanningYear;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class BudgetApprovalController extends Controller
{
    public function preview(
        Request $request,
        int $planningYear,
        BudgetApprovalPreviewQuery $preview,
    ): BudgetProposalResource {
        if ($request->query() !== []) {
            abort(422);
        }

        return BudgetProposalResource::make($preview->execute(
            $this->actor($request),
            $this->tenantContext($request),
            $planningYear,
        ));
    }

    public function approve(
        Request $request,
        int $planningYear,
        ApproveBudgetProposal $approve,
    ): JsonResponse {
        $context = $this->tenantContext($request);
        $year = TenantOwnedRecordQuery::forTenant($context, PlanningYear::class)->find($planningYear);
        if (! $year instanceof PlanningYear) {
            throw (new ModelNotFoundException)->setModel(PlanningYear::class, [$planningYear]);
        }

        $payload = Validator::make($request->all(), [
            'effective_date' => ['required', 'string', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string'],
            'composition' => ['required', 'array:schema_version,fingerprint,versions'],
            'composition.schema_version' => ['required', 'string'],
            'composition.fingerprint' => ['required', 'string', 'regex:/^sha256:[0-9a-f]{64}$/D'],
            'composition.versions' => ['required', 'array:budget_lock_version,projection_version'],
            'composition.versions.budget_lock_version' => ['required', 'integer', 'min:1'],
            'composition.versions.projection_version' => ['required', 'string'],
        ])->validate();

        if (array_diff(array_keys($request->all()), ['effective_date', 'note', 'composition']) !== []) {
            abort(422);
        }

        /** @var array{schema_version:string,fingerprint:string,versions:array{budget_lock_version:int,projection_version:string}} $composition */
        $composition = $payload['composition'];
        $note = $payload['note'] ?? null;
        $approval = $approve->execute(
            $this->actor($request),
            $context,
            $year,
            new ApproveBudgetProposalData(
                effectiveDate: $payload['effective_date'],
                note: is_string($note) && trim($note) !== '' ? trim($note) : null,
                compositionSchemaVersion: $composition['schema_version'],
                compositionFingerprint: $composition['fingerprint'],
                budgetLockVersion: $composition['versions']['budget_lock_version'],
                projectionVersion: $composition['versions']['projection_version'],
            ),
            $this->correlationId($request),
        );
        $approval->load(['planningYear', 'tenant']);

        return BudgetApprovalSummaryResource::make($approval)->response()->setStatusCode(201);
    }
}
