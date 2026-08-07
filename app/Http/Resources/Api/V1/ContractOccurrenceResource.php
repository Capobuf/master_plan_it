<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ContractOccurrenceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $row = $this->resource;
        $get = static function (string $key) use ($row): mixed {
            if (is_array($row)) {
                return $row[$key] ?? null;
            }

            $property = [
                'term_id' => 'termId',
                'planning_year' => 'planningYear',
                'occurrence_date' => 'occurrenceDate',
                'source_key' => 'sourceKey',
                'net_amount' => 'netAmount',
                'vat_amount' => 'vatAmount',
                'gross_amount' => 'grossAmount',
                'expense_id' => 'expenseId',
                'generation_state' => 'generationState',
            ][$key] ?? $key;

            return $row->{$property};
        };

        return [
            'term_id' => (int) $get('term_id'),
            'planning_year' => (int) $get('planning_year'),
            'occurrence_date' => (string) $get('occurrence_date'),
            'source_key' => (string) $get('source_key'),
            'net' => (string) $get('net_amount'),
            'vat' => (string) $get('vat_amount'),
            'gross' => (string) $get('gross_amount'),
            'currency' => $get('currency') ?? $request->attributes->get('currency_code'),
            'official_basis' => $get('official_basis') ?? $request->attributes->get('official_basis'),
            'suppressed' => (bool) $get('suppressed'),
            'expense_id' => $get('expense_id') === null ? null : (int) $get('expense_id'),
            'generation_state' => $get('generation_state'),
        ];
    }
}
