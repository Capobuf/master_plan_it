<?php

namespace App\Models;

use App\Domain\Expenses\Enums\ActualConfirmationState;
use App\Domain\Expenses\Enums\Distribution;
use App\Domain\Expenses\Enums\ExpenseType;
use Database\Factories\ExpenseRowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'position',
    'vendor_id',
    'type',
    'description',
    'quantity',
    'unit_price',
    'entered_amount',
    'amount_includes_vat',
    'vat_rate',
    'is_extra',
    'funded_plafond_expense_id',
    'spend_date',
    'period_start',
    'period_end',
    'distribution',
    'external_reference',
    'lock_version',
])]
class ExpenseRow extends Model
{
    /** @use HasFactory<ExpenseRowFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ExpenseType::class,
            'confirmation_state' => ActualConfirmationState::class,
            'distribution' => Distribution::class,
            'is_system_managed' => 'boolean',
            'amount_includes_vat' => 'boolean',
            'is_extra' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Expense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return BelongsTo<Expense, $this>
     */
    public function fundedPlafond(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'funded_plafond_expense_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }
}
