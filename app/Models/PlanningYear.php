<?php

namespace App\Models;

use App\Domain\Budget\Enums\BudgetState;
use Closure;
use Database\Factories\PlanningYearFactory;
use Illuminate\Contracts\Database\Query\Expression;
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
    /** @var list<string> */
    private const FORWARDED_STATE_WRITE_METHODS = [
        'decrement', 'decrementeach', 'increment', 'incrementeach', 'incrementorcreate',
        'update', 'updatefrom', 'updateorcreate', 'updateorinsert', 'upsert',
    ];

    /** @param array<string, mixed> $values */
    public function update(array $values): int
    {
        $this->assertPayloadDoesNotContainBudgetState($values);

        return parent::update($values);
    }

    /**
     * @param  array<int|string, array<string, mixed>|mixed>  $values
     * @param  array<int|string, string>|string  $uniqueBy
     * @param  array<int|string, string>|null  $update
     */
    public function upsert(array $values, $uniqueBy, $update = null): int
    {
        $this->assertPayloadDoesNotContainBudgetState($values);
        if (is_array($update)) {
            $this->assertColumnsDoNotContainBudgetState($update);
        }

        return parent::upsert($values, $uniqueBy, $update);
    }

    public function increment($column, $amount = 1, array $extra = []): int
    {
        $this->assertColumnIsNotBudgetState($column);
        $this->assertPayloadDoesNotContainBudgetState($extra);

        return parent::increment($column, $amount, $extra);
    }

    public function decrement($column, $amount = 1, array $extra = []): int
    {
        $this->assertColumnIsNotBudgetState($column);
        $this->assertPayloadDoesNotContainBudgetState($extra);

        return parent::decrement($column, $amount, $extra);
    }

    /** @param array<string, int|float|numeric-string> $columns */
    public function incrementEach(array $columns, array $extra = []): int
    {
        $this->assertColumnsDoNotContainBudgetState($columns);
        $this->assertPayloadDoesNotContainBudgetState($extra);

        return parent::incrementEach($columns, $extra);
    }

    /** @param array<string, int|float|numeric-string> $columns */
    public function decrementEach(array $columns, array $extra = []): int
    {
        $this->assertColumnsDoNotContainBudgetState($columns);
        $this->assertPayloadDoesNotContainBudgetState($extra);

        return parent::decrementEach($columns, $extra);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|callable(bool): array<string, mixed>  $values
     */
    public function updateOrInsert(array $attributes, array|callable $values = []): bool
    {
        $this->assertPayloadDoesNotContainBudgetState($attributes);

        if (is_callable($values)) {
            $values = function (bool $exists) use ($values): array {
                $resolved = $values($exists);
                if (! is_array($resolved)) {
                    throw new \InvalidArgumentException('Update-or-insert values must resolve to an array.');
                }
                $this->assertPayloadDoesNotContainBudgetState($resolved);

                return $resolved;
            };
        } else {
            $this->assertPayloadDoesNotContainBudgetState($values);
        }

        return $this->toBase()->updateOrInsert($attributes, $values);
    }

    /** @param array<string, mixed> $attributes */
    public function updateOrCreate(array $attributes, Closure|array $values = []): PlanningYear
    {
        $this->assertPayloadDoesNotContainBudgetState($attributes);

        if ($values instanceof Closure) {
            $resolved = $values();
            $this->assertPayloadDoesNotContainBudgetState($resolved);
            $values = $resolved;
        } else {
            $this->assertPayloadDoesNotContainBudgetState($values);
        }

        return parent::updateOrCreate($attributes, $values);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $extra
     */
    public function incrementOrCreate(
        array $attributes,
        string $column = 'count',
        $default = 1,
        $step = 1,
        array $extra = [],
    ): PlanningYear {
        $this->assertPayloadDoesNotContainBudgetState($attributes);
        $this->assertColumnIsNotBudgetState($column);
        $this->assertPayloadDoesNotContainBudgetState($extra);

        return parent::incrementOrCreate($attributes, $column, $default, $step, $extra);
    }

    /** @param array<string, mixed> $values */
    public function updateFrom(array $values): int
    {
        $this->assertPayloadDoesNotContainBudgetState($values);

        return $this->toBase()->updateFrom($values);
    }

    public function delete(): never
    {
        $this->denyDeletion();
    }

    public function forceDelete(): never
    {
        $this->denyDeletion();
    }

    public function truncate(): never
    {
        $this->denyDeletion();
    }

    /**
     * @param  string  $method
     * @param  array<int, mixed>  $parameters
     */
    public function __call($method, $parameters): mixed
    {
        if (in_array(strtolower($method), self::FORWARDED_STATE_WRITE_METHODS, true)) {
            throw new \DomainException('BUDGET_STATE_CONFLICT');
        }

        return parent::__call($method, $parameters);
    }

    /** @param array<int|string, mixed> $payload */
    private function assertPayloadDoesNotContainBudgetState(array $payload): void
    {
        foreach ($payload as $column => $value) {
            if (is_string($column)) {
                $this->assertColumnIsNotBudgetState($column);
            }
            if (is_array($value)) {
                $this->assertPayloadDoesNotContainBudgetState($value);
            }
        }
    }

    /** @param array<int|string, mixed> $columns */
    private function assertColumnsDoNotContainBudgetState(array $columns): void
    {
        foreach ($columns as $column => $value) {
            if (is_string($column)) {
                $this->assertColumnIsNotBudgetState($column);
            }
            if (is_string($value)) {
                $this->assertColumnIsNotBudgetState($value);
            }
        }
    }

    private function assertColumnIsNotBudgetState(mixed $column): void
    {
        if ($column instanceof Expression) {
            $column = $column->getValue($this->getQuery()->getGrammar());
        }

        if (! is_string($column) || preg_match('/\bbudget_state\b/i', $column) === 1) {
            throw new \DomainException('BUDGET_STATE_CONFLICT');
        }
    }

    private function denyDeletion(): never
    {
        throw new \LogicException('Planning years cannot be permanently deleted.');
    }
}
