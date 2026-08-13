<?php

namespace App\Models\Builders;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * @template TModel of Model
 *
 * @extends Builder<TModel>
 */
final class ImmutableModelBuilder extends Builder
{
    /** @var list<string> */
    private const FORWARDED_WRITE_METHODS = [
        'decrement', 'decrementeach', 'delete', 'forcedelete', 'increment', 'incrementeach',
        'incrementorcreate', 'restore', 'restoreorcreate', 'createorrestore', 'touch', 'truncate',
        'update', 'updatefrom', 'updateorcreate', 'updateorinsert', 'upsert',
    ];

    /** @param array<string, mixed> $values */
    public function update(array $values): never
    {
        $this->denyMutation();
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @param  array<int, string>|string  $uniqueBy
     * @param  array<int|string, string>|null  $update
     */
    public function upsert(array $values, $uniqueBy, $update = null): never
    {
        $this->denyMutation();
    }

    /** @param array<int, string>|string|null $column */
    public function touch($column = null): never
    {
        $this->denyMutation();
    }

    public function increment($column, $amount = 1, array $extra = []): never
    {
        $this->denyMutation();
    }

    public function decrement($column, $amount = 1, array $extra = []): never
    {
        $this->denyMutation();
    }

    /** @param array<string, int|float|numeric-string> $columns */
    public function incrementEach(array $columns, array $extra = []): never
    {
        $this->denyMutation();
    }

    /** @param array<string, int|float|numeric-string> $columns */
    public function decrementEach(array $columns, array $extra = []): never
    {
        $this->denyMutation();
    }

    /** @param array<string, mixed> $attributes */
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
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|callable(bool): array<string, mixed>  $values
     */
    public function updateOrInsert(array $attributes, array|callable $values = []): never
    {
        $this->denyMutation();
    }

    /** @param array<string, mixed> $values */
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

    /**
     * @param  string  $method
     * @param  array<int, mixed>  $parameters
     */
    public function __call($method, $parameters): mixed
    {
        if (in_array(strtolower($method), self::FORWARDED_WRITE_METHODS, true)) {
            $this->denyMutation();
        }

        return parent::__call($method, $parameters);
    }

    private function denyMutation(): never
    {
        throw new LogicException('Immutable records cannot be mutated generically.');
    }
}
