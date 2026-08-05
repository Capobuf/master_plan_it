<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RevisionBatchItem extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'revision_batch_id',
        'version_id',
        'versionable_type',
        'versionable_id',
        'sequence',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
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

    /**
     * @return MorphTo<Model, $this>
     */
    public function versionable(): MorphTo
    {
        return $this->morphTo('versionable', 'versionable_type', 'versionable_id');
    }
}
