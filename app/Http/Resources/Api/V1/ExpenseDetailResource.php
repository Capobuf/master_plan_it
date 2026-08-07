<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Expenses\Data\ExpenseDetail;
use App\Domain\Tenancy\Data\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read ExpenseDetail $resource */
final class ExpenseDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $expense = $this->resource;
        $context = $request->attributes->get(TenantContext::class);
        $currency = $context instanceof TenantContext ? $context->currencyCode : 'EUR';
        $officialBasis = $context instanceof TenantContext ? $context->budgetBasis->value : 'net';

        return [
            'id' => $expense->id,
            'planning_year_id' => $expense->planningYearId,
            'planning_year_label' => $expense->planningYearLabel,
            'cost_center_id' => $expense->costCenterId,
            'cost_center_name' => $expense->costCenterName,
            'kind' => $expense->kind,
            'title' => $expense->title,
            'notes' => $expense->notes,
            'contract_id' => $expense->contractId,
            'lock_version' => $expense->lockVersion,
            'rows' => ExpenseRowResource::collection(collect($expense->rows))->resolve($request),
            'totals' => ExpenseMoneyResource::make([
                'net' => $expense->netTotal,
                'vat' => $expense->vatTotal,
                'gross' => $expense->grossTotal,
                'currency' => $currency,
                'official_basis' => $officialBasis,
            ])->resolve($request),
        ];
    }
}
