<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Budget\Queries\BudgetApprovalPreviewQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BudgetProposalResource;
use Illuminate\Http\Request;

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
}
