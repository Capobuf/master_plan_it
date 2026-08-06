<?php

namespace App\Models;

use App\Domain\Contracts\Enums\BillingCycle;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Overtrue\LaravelVersionable\Versionable;
use Overtrue\LaravelVersionable\VersionStrategy;

#[Fillable(['tenant_id', 'contract_id', 'source_rule_key', 'effective_start', 'effective_end', 'billing_cycle', 'quantity', 'unit_price', 'entered_amount', 'amount_includes_vat', 'vat_rate', 'net_amount', 'vat_amount', 'gross_amount', 'auto_renew', 'lock_version', 'deleted_by_user_id', 'deleted_by_at', 'deletion_reason'])]
class ContractTerm extends Model
{
    use SoftDeletes, Versionable;

    protected array $versionable = ['effective_start', 'effective_end', 'billing_cycle', 'quantity', 'unit_price', 'entered_amount', 'amount_includes_vat', 'vat_rate', 'net_amount', 'vat_amount', 'gross_amount', 'auto_renew', 'deleted_by_user_id', 'deleted_by_at', 'deletion_reason', 'lock_version'];
    protected VersionStrategy $versionStrategy = VersionStrategy::SNAPSHOT;

    protected function casts(): array
    {
        return ['billing_cycle' => BillingCycle::class, 'effective_start' => 'date', 'effective_end' => 'date', 'amount_includes_vat' => 'boolean', 'auto_renew' => 'boolean', 'deleted_by_at' => 'datetime', 'lock_version' => 'integer'];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function expenseRows(): HasMany { return $this->hasMany(ExpenseRow::class); }
}
