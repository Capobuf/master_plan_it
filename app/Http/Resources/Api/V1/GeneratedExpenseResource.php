<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class GeneratedExpenseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = $this->resource;
        $get = static fn (string $key): mixed => is_array($data) ? ($data[$key] ?? null) : $data->{$key};

        return [
            'id' => (int) $get('id'),
            'title' => (string) $get('title'),
            'planning_state' => $get('planning_state'),
            'is_system_managed' => (bool) $get('is_system_managed'),
            'source_key' => (string) $get('source_key'),
            'contract_term_id' => $get('contract_term_id') === null ? null : (int) $get('contract_term_id'),
            'occurrence_date' => $get('occurrence_date'),
            'net' => (string) $get('net_amount'),
            'vat' => (string) $get('vat_amount'),
            'gross' => (string) $get('gross_amount'),
            'currency' => $get('currency') ?? $request->attributes->get('currency_code'),
            'official_basis' => $get('official_basis') ?? $request->attributes->get('official_basis'),
            'lock_version' => (int) $get('lock_version'),
        ];
    }
}
