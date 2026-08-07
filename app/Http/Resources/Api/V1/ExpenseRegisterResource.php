<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Expenses\Data\ExpenseRegisterRow;
use App\Domain\Tenancy\Data\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read ExpenseRegisterRow $resource */
final class ExpenseRegisterResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $row = $this->resource;
        $context = $request->attributes->get(TenantContext::class);
        $currency = $context instanceof TenantContext ? $context->currencyCode : 'EUR';

        return [
            'id' => $row->id,
            'planning_year_id' => $row->planningYearId,
            'planning_year_label' => $row->planningYearLabel,
            'cost_center_id' => $row->costCenterId,
            'cost_center_name' => $row->costCenterName,
            'kind' => $row->kind,
            'title' => $row->title,
            'contract_id' => $row->contractId,
            'contract_title' => $row->contractTitle,
            'contract_current' => $row->contractCurrent,
            'row_count' => $row->rowCount,
            'totals' => ExpenseMoneyResource::make([
                'net' => $row->netTotal,
                'vat' => $row->vatTotal,
                'gross' => $row->grossTotal,
                'currency' => $currency,
            ])->resolve($request),
        ];
    }
}
