<?php

namespace App\Models;

use Database\Factories\PlanningYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;

#[Fillable([
    'tenant_id',
    'year_label',
    'active',
    'lock_version',
])]
class PlanningYear extends Model
{
    /** @use HasFactory<PlanningYearFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year_label' => 'integer',
            'active' => 'boolean',
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
