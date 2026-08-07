<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ContractTermResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $term = $this->resource;
        $currency = is_array($term) ? ($term['currency'] ?? null) : ($request->attributes->get('currency_code') ?? null);
        $read = static function (string $key) use ($term): mixed {
            if (is_array($term)) {
                return $term[$key] ?? null;
            }

            $value = $term->getAttribute($key);
            if ($value === null && $key === 'local_key') {
                $value = $term->getAttribute('source_rule_key');
            }

            return $value;
        };

        return [
            'id' => $read('id') === null ? null : (int) $read('id'),
            'local_key' => (string) $read('local_key'),
            'effective_start' => $read('effective_start'),
            'effective_end' => $read('effective_end'),
            'billing_cycle' => is_object($read('billing_cycle')) ? $read('billing_cycle')->value : (string) $read('billing_cycle'),
            'quantity' => $read('quantity') === null ? null : (string) $read('quantity'),
            'unit_price' => $read('unit_price') === null ? null : (string) $read('unit_price'),
            'entered_amount' => (string) $read('entered_amount'),
            'amount_includes_vat' => (bool) $read('amount_includes_vat'),
            'vat_rate' => (string) $read('vat_rate'),
            'net' => (string) $read('net_amount'),
            'vat' => (string) $read('vat_amount'),
            'gross' => (string) $read('gross_amount'),
            'currency' => $currency,
            'auto_renew' => (bool) $read('auto_renew'),
            'lock_version' => (int) $read('lock_version'),
        ];
    }
}
