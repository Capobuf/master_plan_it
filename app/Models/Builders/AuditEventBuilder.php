<?php

namespace App\Models\Builders;

use App\Models\AuditEvent;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<AuditEvent>
 */
final class AuditEventBuilder extends Builder
{
    public function pruneBefore(DateTimeInterface $cutoff): int
    {
        return $this->toBase()
            ->where('occurred_at', '<', $cutoff)
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  (Closure(): array<string, mixed>)|array<string, mixed>  $values
     */
    public function updateOrCreate(array $attributes, Closure|array $values = []): never
    {
        $this->denyMutation();
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
    ): never {
        $this->denyMutation();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): never
    {
        $this->denyMutation();
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @param  array<int, string>|string  $uniqueBy
     * @param  array<int, string>|null  $update
     */
    public function upsert(array $values, $uniqueBy, $update = null): never
    {
        $this->denyMutation();
    }

    /**
     * @param  array<int, string>|string|null  $column
     */
    public function touch($column = null): never
    {
        $this->denyMutation();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function increment($column, $amount = 1, array $extra = []): never
    {
        $this->denyMutation();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function decrement($column, $amount = 1, array $extra = []): never
    {
        $this->denyMutation();
    }

    /**
     * @param  array<string, float|int|numeric-string>  $columns
     * @param  array<string, mixed>  $extra
     */
    public function incrementEach(array $columns, array $extra = []): never
    {
        $this->denyMutation();
    }

    /**
     * @param  array<string, float|int|numeric-string>  $columns
     * @param  array<string, mixed>  $extra
     */
    public function decrementEach(array $columns, array $extra = []): never
    {
        $this->denyMutation();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|callable(bool): array<string, mixed>  $values
     */
    public function updateOrInsert(array $attributes, array|callable $values = []): never
    {
        $this->denyMutation();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function updateFrom(array $values): never
    {
        $this->denyMutation();
    }

    public function delete(): never
    {
        $this->denyMutation();
    }

    public function forceDelete(): never
    {
        $this->denyMutation();
    }

    public function truncate(): never
    {
        $this->denyMutation();
    }

    private function denyMutation(): never
    {
        throw new \LogicException('Audit events are append-only.');
    }
}
