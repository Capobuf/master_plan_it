<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Overtrue\LaravelVersionable\Versionable;
use Overtrue\LaravelVersionable\VersionStrategy;

#[Fillable(['tenant_id', 'vendor_id', 'cost_center_id', 'title', 'description', 'active', 'renewal_date', 'renewal_notice_days', 'renewal_notes', 'lock_version', 'deleted_by_user_id', 'deleted_by_at', 'deletion_reason'])]
class Contract extends Model
{
    use SoftDeletes, Versionable;

    protected array $versionable = ['vendor_id', 'cost_center_id', 'title', 'description', 'active', 'renewal_date', 'renewal_notice_days', 'renewal_notes', 'deleted_by_user_id', 'deleted_by_at', 'deletion_reason', 'lock_version'];
    protected VersionStrategy $versionStrategy = VersionStrategy::SNAPSHOT;

    protected function casts(): array
    {
        return ['active' => 'boolean', 'renewal_date' => 'date', 'deleted_by_at' => 'datetime', 'lock_version' => 'integer'];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class); }
    public function costCenter(): BelongsTo { return $this->belongsTo(CostCenter::class); }
    public function terms(): HasMany { return $this->hasMany(ContractTerm::class); }
    public function expenses(): HasMany { return $this->hasMany(Expense::class); }
    public function generationExceptions(): HasMany { return $this->hasMany(ContractGenerationException::class); }
}
