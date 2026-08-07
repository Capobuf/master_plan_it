<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Economics\Data\EconomicLine;
use App\Domain\Tenancy\Data\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read EconomicLine $resource */
final class ReportingLineResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $line = $this->resource;
        $context = $request->attributes->get(TenantContext::class);

        return [
            'id' => $line->rowId,
            'expense_id' => $line->expenseId,
            'cost_center_id' => $line->costCenterId,
            'cost_center_name' => $line->costCenterName,
            'expense_kind' => $line->expenseKind,
            'type' => $line->type,
            'confirmation_state' => $line->confirmationState,
            'net' => $line->net,
            'vat' => $line->vat,
            'gross' => $line->gross,
            'currency' => $context instanceof TenantContext ? $context->currencyCode : 'EUR',
            'official_basis' => $context instanceof TenantContext ? $context->budgetBasis->value : 'net',
            'funded_plafond_expense_id' => $line->fundedPlafondExpenseId,
            'spend_date' => $line->spendDate,
            'period_start' => $line->periodStart,
            'period_end' => $line->periodEnd,
            'distribution' => $line->distribution,
            'is_extra' => $line->isExtra,
        ];
    }
}
