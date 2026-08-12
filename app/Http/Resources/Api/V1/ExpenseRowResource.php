<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Expenses\Enums\ExpenseType;
use App\Models\ExpenseRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read ExpenseRow|array<string, mixed> $resource */
final class ExpenseRowResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $row = $this->resource;
        if (is_array($row)) {
            return [
                'id' => (int) $row['id'],
                'position' => (int) $row['position'],
                'vendor_id' => $row['vendor_id'] === null ? null : (int) $row['vendor_id'],
                'vendor_name' => $row['vendor_name'] ?? null,
                'type' => (string) $row['type'],
                'is_current_planning' => (bool) ($row['is_current_planning'] ?? false),
                'description' => (string) $row['description'],
                'notes' => $row['notes'] ?? null,
                'quantity' => $row['quantity'],
                'unit_price' => $row['unit_price'],
                'entered_amount' => $row['entered_amount'],
                'amount_includes_vat' => (bool) $row['amount_includes_vat'],
                'vat_rate' => $row['vat_rate'],
                'spend_date' => $row['spend_date'],
                'external_reference' => $row['external_reference'],
                'lock_version' => (int) $row['lock_version'],
                'is_system_managed' => (bool) $row['is_system_managed'],
                'generated' => (bool) $row['generated'],
                'contract_term_id' => $row['contract_term_id'],
                'amount' => $row['amount'],
            ];
        }

        return [
            'id' => (int) $row->getKey(),
            'position' => (int) $row->position,
            'vendor_id' => $row->vendor_id === null ? null : (int) $row->vendor_id,
            'vendor_name' => $row->relationLoaded('vendor') ? $row->vendor?->name : null,
            'type' => $row->type instanceof ExpenseType ? $row->type->value : (string) $row->getRawOriginal('type'),
            'is_current_planning' => (bool) ($row->getAttribute('is_current_planning') ?? false),
            'description' => (string) $row->description,
            'notes' => $row->notes,
            'quantity' => $row->quantity === null ? null : self::decimal((string) $row->quantity, 2),
            'unit_price' => $row->unit_price === null ? null : self::decimal((string) $row->unit_price, 2),
            'entered_amount' => self::decimal((string) $row->entered_amount, 2),
            'amount_includes_vat' => (bool) $row->amount_includes_vat,
            'vat_rate' => self::decimal((string) $row->vat_rate, 2),
            'spend_date' => $row->getRawOriginal('spend_date'),
            'external_reference' => $row->external_reference,
            'lock_version' => (int) $row->lock_version,
            'is_system_managed' => (bool) $row->is_system_managed,
            'generated' => $row->source_key !== null,
            'contract_term_id' => $row->contract_term_id === null ? null : (int) $row->contract_term_id,
            'amount' => null,
        ];
    }

    private static function decimal(string $value, int $scale): string
    {
        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $negative = str_starts_with($integer, '-');
        $integer = ltrim($integer, '-');
        $integer = ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;
        $fraction = substr(str_pad($fraction, $scale, '0'), 0, $scale);

        return ($negative && ($integer !== '0' || trim($fraction, '0') !== '') ? '-' : '').$integer.($scale > 0 ? '.'.$fraction : '');
    }
}
