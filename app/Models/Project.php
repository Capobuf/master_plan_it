<?php

namespace App\Models;

use App\Domain\Projects\Enums\ProjectStage;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Overtrue\LaravelVersionable\Versionable;
use Overtrue\LaravelVersionable\VersionStrategy;

#[Fillable([
    'tenant_id', 'cost_center_id', 'title', 'stage', 'deferred_target_planning_year_id',
    'lock_version', 'deleted_by_user_id', 'deleted_by_at', 'deletion_reason',
])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes, Versionable;

    /** @var list<string> */
    protected array $versionable = [
        'cost_center_id', 'title', 'stage', 'deferred_target_planning_year_id',
        'deleted_by_user_id', 'deleted_by_at', 'deletion_reason', 'lock_version',
    ];

    protected VersionStrategy $versionStrategy = VersionStrategy::SNAPSHOT;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'stage' => ProjectStage::class,
            'lock_version' => 'integer',
            'deleted_by_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<CostCenter, $this> */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /** @return BelongsTo<PlanningYear, $this> */
    public function deferredTargetPlanningYear(): BelongsTo
    {
        return $this->belongsTo(PlanningYear::class, 'deferred_target_planning_year_id');
    }

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
