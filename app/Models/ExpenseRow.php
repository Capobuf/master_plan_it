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
use Overtrue\LaravelVersionable\Versionable;
use Overtrue\LaravelVersionable\VersionStrategy;

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
    use HasFactory, SoftDeletes, Versionable;

    /** @var list<string> */
    protected array $versionable = [
        'tenant_id',
        'expense_id',
        'position',
        'vendor_id',
        'type',
        'description',
        'quantity',
        'unit_price',
        'entered_amount',
        'amount_includes_vat',
        'vat_rate',
        'net_amount',
        'vat_amount',
        'gross_amount',
        'is_extra',
        'funded_plafond_expense_id',
        'spend_date',
        'period_start',
        'period_end',
        'distribution',
        'external_reference',
        'confirmation_state',
        'confirmed_by_user_id',
        'confirmed_at',
        'is_system_managed',
        'manual_override_at',
        'contract_term_id',
        'contract_source_rule_key',
        'contract_occurrence_date',
        'source_key',
        'source_deleted_contract_id',
        'source_deleted_contract_title',
        'source_contract_deleted_at',
        'source_contract_deletion_reason',
        'source_deleted_term_id',
        'source_deleted_term_rule_key',
        'source_deleted_term_start',
        'source_deleted_term_end',
        'source_term_deleted_at',
        'source_term_deletion_reason',
        'lock_version',
    ];

    protected VersionStrategy $versionStrategy = VersionStrategy::SNAPSHOT;

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
            'confirmed_at' => 'datetime',
            'manual_override_at' => 'datetime',
            'contract_occurrence_date' => 'date',
            'source_contract_deleted_at' => 'datetime',
            'source_deleted_term_start' => 'date',
            'source_deleted_term_end' => 'date',
            'source_term_deleted_at' => 'datetime',
            'quantity' => 'decimal:6',
            'unit_price' => 'decimal:6',
            'entered_amount' => 'decimal:6',
            'vat_rate' => 'decimal:6',
            'net_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'gross_amount' => 'decimal:2',
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

    /**
     * @return BelongsTo<ContractTerm, $this>
     */
    public function contractTerm(): BelongsTo
    {
        return $this->belongsTo(ContractTerm::class);
    }
}
