<?php

namespace App\Models;

use Database\Factories\BudgetClosureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use LogicException;

#[Fillable([
    'tenant_id', 'planning_year_id', 'budget_approval_id', 'actor_user_id', 'recorded_at',
    'revision_batch_id', 'correlation_id',
])]
class BudgetClosure extends Model
{
    /** @use HasFactory<BudgetClosureFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['recorded_at' => 'immutable_datetime'];
    }

    /** @param Builder<static> $query */
    protected function performUpdate(Builder $query): bool
    {
        throw new LogicException('Budget closures are append-only.');
    }

    protected function performDeleteOnModel(): void
    {
        throw new LogicException('Budget closures are append-only.');
    }

    /** @param QueryBuilder $query */
    public function newEloquentBuilder($query): BudgetClosureBuilder
    {
        return new BudgetClosureBuilder($query);
    }

    /** @return BelongsTo<BudgetApproval, $this> */
    public function approval(): BelongsTo
    {
        return $this->belongsTo(BudgetApproval::class, 'budget_approval_id');
    }

    /** @return BelongsTo<PlanningYear, $this> */
    public function planningYear(): BelongsTo
    {
        return $this->belongsTo(PlanningYear::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return BelongsTo<RevisionBatch, $this> */
    public function revisionBatch(): BelongsTo
    {
        return $this->belongsTo(RevisionBatch::class);
    }
}

/** @extends Builder<BudgetClosure> */
final class BudgetClosureBuilder extends Builder
{
    /** @param array<string, mixed> $values */
    public function update(array $values): never
    {
        throw new LogicException('Budget closures are append-only.');
    }

    public function delete(): never
    {
        throw new LogicException('Budget closures are append-only.');
    }

    public function forceDelete(): never
    {
        throw new LogicException('Budget closures are append-only.');
    }
}
