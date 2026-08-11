<?php

namespace App\Models;

use App\Domain\Budget\Enums\BudgetState;
use Database\Factories\PlanningYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Overtrue\LaravelVersionable\Versionable;
use Overtrue\LaravelVersionable\VersionStrategy;

#[Fillable([
    'tenant_id',
    'year_label',
    'active',
    'budget_state',
    'history_activated_at',
    'lock_version',
])]
class PlanningYear extends Model
{
    /** @use HasFactory<PlanningYearFactory> */
    use HasFactory, Versionable;

    /** @var list<string> */
    protected array $versionable = ['tenant_id', 'year_label', 'active', 'budget_state', 'history_activated_at', 'lock_version'];

    protected VersionStrategy $versionStrategy = VersionStrategy::SNAPSHOT;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year_label' => 'integer',
            'active' => 'boolean',
            'budget_state' => BudgetState::class,
            'history_activated_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    protected function performDeleteOnModel(): void
    {
        throw new \LogicException('Planning years cannot be permanently deleted.');
    }

    /**
     * @param  QueryBuilder  $query
     */
    public function newEloquentBuilder($query): PlanningYearBuilder
    {
        return new PlanningYearBuilder($query);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** @return HasMany<ApprovalOperation, $this> */
    public function approvalOperations(): HasMany
    {
        return $this->hasMany(ApprovalOperation::class);
    }
}

/**
 * @extends Builder<PlanningYear>
 */
final class PlanningYearBuilder extends Builder
{
    public function delete(): never
    {
        $this->denyDeletion();
    }

    public function forceDelete(): never
    {
        $this->denyDeletion();
    }

    private function denyDeletion(): never
    {
        throw new \LogicException('Planning years cannot be permanently deleted.');
    }
}
