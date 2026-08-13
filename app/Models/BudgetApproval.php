<?php

namespace App\Models;

use App\Domain\Budget\Enums\BudgetApprovalStatus;
use Carbon\CarbonImmutable;
use Database\Factories\BudgetApprovalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use LogicException;

#[Fillable([
    'tenant_id', 'planning_year_id', 'status', 'effective_date', 'recorded_at',
    'approved_by_user_id', 'approved_by_name', 'approval_note', 'currency_code', 'budget_basis',
    'total_net_amount', 'total_vat_amount', 'total_gross_amount', 'total_official_amount',
    'contributor_count', 'composition_schema_version', 'projection_version',
    'composition_fingerprint', 'approval_revision_batch_id', 'correlation_id',
])]
class BudgetApproval extends Model
{
    /** @use HasFactory<BudgetApprovalFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => BudgetApprovalStatus::class,
            'effective_date' => 'immutable_date',
            'recorded_at' => 'immutable_datetime',
            'total_net_amount' => 'decimal:2',
            'total_vat_amount' => 'decimal:2',
            'total_gross_amount' => 'decimal:2',
            'total_official_amount' => 'decimal:2',
            'contributor_count' => 'integer',
            'annulled_at' => 'immutable_datetime',
        ];
    }

    /** @param Builder<static> $query */
    protected function performUpdate(Builder $query): bool
    {
        throw new LogicException('Budget approvals cannot be updated generically.');
    }

    protected function performDeleteOnModel(): void
    {
        throw new LogicException('Budget approvals cannot be deleted.');
    }

    public function annul(
        CarbonImmutable $annulledAt,
        User $actor,
        string $note,
        RevisionBatch $revisionBatch,
        string $correlationId,
    ): self {
        if ($this->status !== BudgetApprovalStatus::Active) {
            throw new \DomainException('BUDGET_STATE_CONFLICT');
        }

        $normalizedNote = trim($note);
        if ($normalizedNote === '') {
            throw new \InvalidArgumentException('Annulment note must not be blank.');
        }

        $terminalAttributes = [
            'status' => BudgetApprovalStatus::Annulled,
            'annulled_at' => $annulledAt,
            'annulled_by_user_id' => $actor->getKey(),
            'annulled_by_name' => (string) $actor->name,
            'annulment_note' => $normalizedNote,
            'annulment_revision_batch_id' => $revisionBatch->getKey(),
            'annulment_correlation_id' => $correlationId,
            'updated_at' => $this->freshTimestampString(),
        ];

        $affected = $this->newModelQuery()
            ->whereKey($this->getKey())
            ->where('status', BudgetApprovalStatus::Active->value)
            ->toBase()
            ->update($terminalAttributes);

        if ($affected !== 1) {
            throw new \DomainException('BUDGET_STATE_CONFLICT');
        }

        $this->forceFill($terminalAttributes);
        $this->syncOriginalAttributes(array_keys($terminalAttributes));

        return $this;
    }

    /** @param QueryBuilder $query */
    public function newEloquentBuilder($query): BudgetApprovalBuilder
    {
        return new BudgetApprovalBuilder($query);
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
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function annuller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annulled_by_user_id');
    }

    /** @return BelongsTo<RevisionBatch, $this> */
    public function approvalRevisionBatch(): BelongsTo
    {
        return $this->belongsTo(RevisionBatch::class, 'approval_revision_batch_id');
    }

    /** @return BelongsTo<RevisionBatch, $this> */
    public function annulmentRevisionBatch(): BelongsTo
    {
        return $this->belongsTo(RevisionBatch::class, 'annulment_revision_batch_id');
    }

    /** @return HasMany<BudgetApprovalItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(BudgetApprovalItem::class);
    }
}

/** @extends Builder<BudgetApproval> */
final class BudgetApprovalBuilder extends Builder
{
    /** @param array<string, mixed> $values */
    public function update(array $values): never
    {
        throw new LogicException('Budget approvals cannot be updated generically.');
    }

    public function delete(): never
    {
        throw new LogicException('Budget approvals cannot be deleted.');
    }

    public function forceDelete(): never
    {
        throw new LogicException('Budget approvals cannot be deleted.');
    }
}
