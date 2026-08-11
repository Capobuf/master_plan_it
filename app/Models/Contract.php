<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Overtrue\LaravelVersionable\Versionable;
use Overtrue\LaravelVersionable\VersionStrategy;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $vendor_id
 * @property int $cost_center_id
 * @property string $title
 * @property string|null $description
 * @property bool $active
 * @property Carbon|null $renewal_date
 * @property int|null $renewal_notice_days
 * @property string|null $renewal_notes
 * @property int $lock_version
 */
#[Fillable(['tenant_id', 'vendor_id', 'cost_center_id', 'project_id', 'title', 'description', 'active', 'renewal_date', 'renewal_notice_days', 'renewal_notes', 'lock_version', 'deleted_by_user_id', 'deleted_by_at', 'deletion_reason'])]
class Contract extends Model implements HasMedia
{
    use InteractsWithMedia, SoftDeletes, Versionable;

    public const ATTACHMENT_COLLECTION = 'attachments';

    /** @var list<string> */
    protected array $versionable = ['vendor_id', 'cost_center_id', 'project_id', 'title', 'description', 'active', 'renewal_date', 'renewal_notice_days', 'renewal_notes', 'deleted_by_user_id', 'deleted_by_at', 'deletion_reason'];

    protected VersionStrategy $versionStrategy = VersionStrategy::SNAPSHOT;

    protected function casts(): array
    {
        return ['active' => 'boolean', 'renewal_date' => 'date', 'deleted_by_at' => 'datetime', 'lock_version' => 'integer'];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** @return BelongsTo<CostCenter, $this> */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<ContractTerm, $this> */
    public function terms(): HasMany
    {
        return $this->hasMany(ContractTerm::class);
    }

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** @return HasMany<ContractGenerationException, $this> */
    public function generationExceptions(): HasMany
    {
        return $this->hasMany(ContractGenerationException::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::ATTACHMENT_COLLECTION)->useDisk('attachments');
    }
}
