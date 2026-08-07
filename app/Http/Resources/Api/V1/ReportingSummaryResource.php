<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Economics\Data\EconomicSummary;
use App\Domain\Tenancy\Data\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read EconomicSummary $resource */
final class ReportingSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $summary = $this->resource;
        $context = $request->attributes->get(TenantContext::class);
        $amounts = [
            'official_current_position' => $summary->amounts['officialCurrentPosition'],
            'net' => $summary->amounts['net'],
            'vat' => $summary->amounts['vat'],
            'gross' => $summary->amounts['gross'],
            'estimate' => $summary->amounts['estimate'],
            'quote' => $summary->amounts['quote'],
            'actual' => $summary->amounts['actual'],
            'actual_to_confirm' => $summary->amounts['actualToConfirm'],
            'actual_confirmed' => $summary->amounts['actualConfirmed'],
            'extra' => $summary->amounts['extra'],
            'plafond_allocated' => $summary->amounts['plafondAllocated'],
            'plafond_consumed' => $summary->amounts['plafondConsumed'],
            'plafond_residual' => $summary->amounts['plafondResidual'],
            'plafond_overrun' => $summary->amounts['plafondOverrun'],
        ];

        return [
            'official_basis' => $summary->officialBasis,
            'currency' => $context instanceof TenantContext ? $context->currencyCode : 'EUR',
            'amounts' => $amounts,
        ];
    }
}
