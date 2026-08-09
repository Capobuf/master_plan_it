<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Tenancy\Data\TenantContext;
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
        $context = $request->attributes->get(TenantContext::class);
        $currency = $context instanceof TenantContext ? $context->currencyCode : 'EUR';
        $officialBasis = $context instanceof TenantContext ? $context->budgetBasis->value : 'net';

        if (is_array($row)) {
            return [
                'id' => (int) $row['id'],
                'position' => (int) $row['position'],
                'vendor_id' => $row['vendor_id'] === null ? null : (int) $row['vendor_id'],
                'type' => (string) $row['type'],
                'is_current_planning' => (bool) ($row['is_current_planning'] ?? false),
                'description' => (string) $row['description'],
                'quantity' => $row['quantity'],
                'unit_price' => $row['unit_price'],
                'entered_amount' => $row['entered_amount'],
                'amount_includes_vat' => (bool) $row['amount_includes_vat'],
                'vat_rate' => $row['vat_rate'],
                'is_extra' => (bool) $row['is_extra'],
                'funded_plafond_expense_id' => $row['funded_plafond_expense_id'],
                'spend_date' => $row['spend_date'],
                'external_reference' => $row['external_reference'],
                'lock_version' => (int) $row['lock_version'],
                'is_system_managed' => (bool) $row['is_system_managed'],
                'generated' => (bool) $row['generated'],
                'contract_term_id' => $row['contract_term_id'],
                'totals' => ExpenseMoneyResource::make([
                    'net' => (string) $row['net_amount'],
                    'vat' => (string) $row['vat_amount'],
                    'gross' => (string) $row['gross_amount'],
                    'currency' => $currency,
                    'official_basis' => $officialBasis,
                ])->resolve($request),
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
            'quantity' => $row->quantity === null ? null : self::decimal((string) $row->quantity, 6),
            'unit_price' => $row->unit_price === null ? null : self::decimal((string) $row->unit_price, 6),
            'entered_amount' => self::decimal((string) $row->entered_amount, 6),
            'amount_includes_vat' => (bool) $row->amount_includes_vat,
            'vat_rate' => self::decimal((string) $row->vat_rate, 6),
            'is_extra' => (bool) $row->is_extra,
            'funded_plafond_expense_id' => $row->funded_plafond_expense_id === null ? null : (int) $row->funded_plafond_expense_id,
            'spend_date' => $row->getRawOriginal('spend_date'),
            'external_reference' => $row->external_reference,
            'lock_version' => (int) $row->lock_version,
            'is_system_managed' => (bool) $row->is_system_managed,
            'generated' => $row->source_key !== null,
            'contract_term_id' => $row->contract_term_id === null ? null : (int) $row->contract_term_id,
            'totals' => ExpenseMoneyResource::make([
                'net' => self::decimal((string) $row->net_amount, 2),
                'vat' => self::decimal((string) $row->vat_amount, 2),
                'gross' => self::decimal((string) $row->gross_amount, 2),
                'currency' => $currency,
                'official_basis' => $officialBasis,
            ])->resolve($request),
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
