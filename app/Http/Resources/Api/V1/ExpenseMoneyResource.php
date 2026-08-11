<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read array{net: string, vat: string, gross: string, currency: string, official_basis: string} $resource */
final class ExpenseMoneyResource extends JsonResource
{
    /** @return array{net: string, vat: string, gross: string, currency: string, official_basis: string} */
    public function toArray(Request $request): array
    {
        return [
            'net' => (string) $this->resource['net'],
            'vat' => (string) $this->resource['vat'],
            'gross' => (string) $this->resource['gross'],
            'currency' => (string) $this->resource['currency'],
            'official_basis' => (string) $this->resource['official_basis'],
        ];
    }
}
