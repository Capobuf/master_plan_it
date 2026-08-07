<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ContractResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $payload = is_array($this->resource) ? $this->resource : ['contract' => $this->resource];
        $contract = $payload['contract'];
        $currency = $payload['currency'] ?? $request->attributes->get('currency_code');
        $basis = $payload['official_basis'] ?? $request->attributes->get('official_basis');
        $terms = $payload['terms'] ?? ($contract->relationLoaded('terms') ? $contract->terms : []);
        $occurrences = $payload['occurrences'] ?? [];
        $expenses = $payload['generated_expenses'] ?? [];

        return [
            'id' => (int) $contract->getKey(),
            'vendor_id' => (int) $contract->vendor_id,
            'vendor' => $contract->relationLoaded('vendor') && $contract->vendor !== null ? [
                'id' => (int) $contract->vendor->getKey(),
                'name' => (string) $contract->vendor->name,
            ] : null,
            'cost_center_id' => (int) $contract->cost_center_id,
            'cost_center' => $contract->relationLoaded('costCenter') && $contract->costCenter !== null ? [
                'id' => (int) $contract->costCenter->getKey(),
                'name' => (string) $contract->costCenter->name,
            ] : null,
            'title' => (string) $contract->title,
            'description' => $contract->description,
            'active' => (bool) $contract->active,
            'renewal_date' => $contract->renewal_date?->toDateString(),
            'renewal_notice_days' => $contract->renewal_notice_days === null ? null : (int) $contract->renewal_notice_days,
            'renewal_notes' => $contract->renewal_notes,
            'currency' => $currency,
            'official_basis' => $basis,
            'term_count' => count($terms),
            'generated_expense_count' => count($expenses) > 0
                ? count($expenses)
                : (int) ($contract->getAttribute('generated_expenses_count') ?? 0),
            'lock_version' => (int) $contract->lock_version,
            'terms' => ContractTermResource::collection($terms),
            'occurrences' => ContractOccurrenceResource::collection($occurrences),
            'generated_expenses' => GeneratedExpenseResource::collection($expenses),
            'revision_activity' => ContractRevisionResource::collection($payload['revision_activity'] ?? []),
        ];
    }
}
