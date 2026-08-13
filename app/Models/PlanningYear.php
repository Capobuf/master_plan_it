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
    protected array $versionable = ['tenant_id', 'year_label', 'active', 'budget_state', 'history_activated_at'];

    protected VersionStrategy $versionStrategy = VersionStrategy::SNAPSHOT;

    private bool $budgetStateTransition = false;

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

    /** @param Builder<static> $query */
    protected function performUpdate(Builder $query): bool
    {
        $changesBudgetState = $this->isDirty('budget_state');

        if ($changesBudgetState && ! $this->budgetStateTransition) {
            throw new \DomainException('BUDGET_STATE_CONFLICT');
        }

        if (! $changesBudgetState) {
            return parent::performUpdate($query);
        }

        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        $dirty = $this->getDirtyForUpdate();
        if ($dirty !== []) {
            $this->setKeysForSaveQuery($query)->toBase()->update($dirty);
            $this->syncChanges();
            $this->fireModelEvent('updated', false);
        }

        return true;
    }

    public function approveBudget(): self
    {
        return $this->transitionBudgetState(BudgetState::Preparation, BudgetState::Approved);
    }

    public function annulApproval(): self
    {
        return $this->transitionBudgetState(BudgetState::Approved, BudgetState::Preparation);
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

    /** @return HasMany<BudgetApproval, $this> */
    public function budgetApprovals(): HasMany
    {
        return $this->hasMany(BudgetApproval::class);
    }

    /** @return HasMany<BudgetRectification, $this> */
    public function budgetRectifications(): HasMany
    {
        return $this->hasMany(BudgetRectification::class);
    }

    /** @return HasMany<BudgetClosure, $this> */
    public function budgetClosures(): HasMany
    {
        return $this->hasMany(BudgetClosure::class);
    }

    private function transitionBudgetState(BudgetState $from, BudgetState $to): self
    {
        if ($this->budget_state !== $from) {
            throw new \DomainException('BUDGET_STATE_CONFLICT');
        }

        $this->forceFill([
            'budget_state' => $to,
            'lock_version' => ((int) $this->lock_version) + 1,
        ]);
        $this->budgetStateTransition = true;
        try {
            $this->save();
        } finally {
            $this->budgetStateTransition = false;
        }

        return $this;
    }
}

/**
 * @extends Builder<PlanningYear>
 */
final class PlanningYearBuilder extends Builder
{
    /** @param array<string, mixed> $values */
    public function update(array $values): int
    {
        if (array_key_exists('budget_state', $values)) {
            throw new \DomainException('BUDGET_STATE_CONFLICT');
        }

        return parent::update($values);
    }

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
