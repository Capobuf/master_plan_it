<?php

namespace App\Models;

use App\Domain\Revisions\Data\RevisionOperation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property RevisionOperation $operation
 * @property string|null $reason
 * @property \Carbon\Carbon $occurred_at
 */
class RevisionBatch extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'actor_user_id',
        'root_subject_type',
        'root_subject_id',
        'operation',
        'reason',
        'correlation_id',
        'restored_from_batch_id',
        'restored_from_version_id',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'operation' => RevisionOperation::class,
            'occurred_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function rootSubject(): MorphTo
    {
        return $this->morphTo('root_subject', 'root_subject_type', 'root_subject_id');
    }

    /**
     * @return BelongsTo<RevisionBatch, $this>
     */
    public function restoredFromBatch(): BelongsTo
    {
        return $this->belongsTo(RevisionBatch::class, 'restored_from_batch_id');
    }

    /**
     * @return BelongsTo<Version, $this>
     */
    public function restoredFromVersion(): BelongsTo
    {
        return $this->belongsTo(Version::class, 'restored_from_version_id');
    }

    /**
     * @return HasMany<RevisionBatchItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(RevisionBatchItem::class, 'revision_batch_id');
    }
}
