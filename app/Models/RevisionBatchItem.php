<?php

namespace App\Models;

use App\Domain\Revisions\Data\RevisionMutation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RevisionBatchItem extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'revision_batch_id',
        'tenant_id',
        'planning_year_id',
        'mutation',
        'is_changed',
        'version_id',
        'versionable_type',
        'versionable_id',
        'snapshot_contents',
        'operational_root_type',
        'operational_root_id',
        'sequence',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'mutation' => RevisionMutation::class,
            'is_changed' => 'boolean',
            'snapshot_contents' => 'array',
            'operational_root_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<RevisionBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(RevisionBatch::class, 'revision_batch_id');
    }

    /**
     * @return BelongsTo<Version, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(Version::class, 'version_id');
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<PlanningYear, $this> */
    public function planningYear(): BelongsTo
    {
        return $this->belongsTo(PlanningYear::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function versionable(): MorphTo
    {
        return $this->morphTo('versionable', 'versionable_type', 'versionable_id');
    }
}
