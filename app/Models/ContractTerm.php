<?php

namespace App\Models;

use App\Domain\Contracts\Enums\BillingCycle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Overtrue\LaravelVersionable\Versionable;
use Overtrue\LaravelVersionable\VersionStrategy;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $contract_id
 * @property Carbon $effective_start
 * @property Carbon $effective_end
 * @property BillingCycle $billing_cycle
 * @property string|null $quantity
 * @property string|null $unit_price
 * @property string $entered_amount
 * @property bool $amount_includes_vat
 * @property string $vat_rate
 * @property string $net_amount
 * @property string $vat_amount
 * @property string $gross_amount
 * @property bool $auto_renew
 * @property int $lock_version
 */
#[Fillable(['tenant_id', 'contract_id', 'source_rule_key', 'effective_start', 'effective_end', 'billing_cycle', 'quantity', 'unit_price', 'entered_amount', 'amount_includes_vat', 'vat_rate', 'net_amount', 'vat_amount', 'gross_amount', 'auto_renew', 'lock_version', 'deleted_by_user_id', 'deleted_by_at', 'deletion_reason'])]
class ContractTerm extends Model
{
    use SoftDeletes, Versionable;

    /** @var list<string> */
    protected array $versionable = ['effective_start', 'effective_end', 'billing_cycle', 'quantity', 'unit_price', 'entered_amount', 'amount_includes_vat', 'vat_rate', 'net_amount', 'vat_amount', 'gross_amount', 'auto_renew', 'deleted_by_user_id', 'deleted_by_at', 'deletion_reason'];

    protected VersionStrategy $versionStrategy = VersionStrategy::SNAPSHOT;

    protected function casts(): array
    {
        return ['billing_cycle' => BillingCycle::class, 'effective_start' => 'date', 'effective_end' => 'date', 'amount_includes_vat' => 'boolean', 'auto_renew' => 'boolean', 'deleted_by_at' => 'datetime', 'lock_version' => 'integer'];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /** @return HasMany<ExpenseRow, $this> */
    public function expenseRows(): HasMany
    {
        return $this->hasMany(ExpenseRow::class);
    }
}
