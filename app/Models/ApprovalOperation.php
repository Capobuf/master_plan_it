<?php

namespace App\Models;

use App\Domain\Budget\Enums\ApprovalKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'planning_year_id', 'kind', 'effective_date', 'recorded_at', 'actor_user_id', 'reason', 'budget_basis', 'revision_batch_id', 'correlation_id'])]
class ApprovalOperation extends Model
{
    protected function casts(): array
    {
        return [
            'kind' => ApprovalKind::class,
            'effective_date' => 'date',
            'recorded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
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

    /** @return HasMany<ApprovalItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ApprovalItem::class);
    }
}
