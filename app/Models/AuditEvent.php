<?php

namespace App\Models;

use App\Models\Builders\AuditEventBuilder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Query\Builder as QueryBuilder;

#[Fillable([
    'tenant_id',
    'actor_user_id',
    'actor_label',
    'event_type',
    'subject_type',
    'subject_id',
    'correlation_id',
    'properties',
    'occurred_at',
])]
class AuditEvent extends Model
{
    public $timestamps = false;

    /**
     * @param  Builder<static>  $query
     */
    protected function performUpdate(Builder $query): bool
    {
        throw new \LogicException('Audit events are append-only.');
    }

    protected function performDeleteOnModel(): void
    {
        throw new \LogicException('Audit events are append-only.');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'occurred_at' => 'immutable_datetime',
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
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  QueryBuilder  $query
     */
    public function newEloquentBuilder($query): AuditEventBuilder
    {
        return new AuditEventBuilder($query);
    }
}
