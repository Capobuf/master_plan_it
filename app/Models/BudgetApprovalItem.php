<?php

namespace App\Models;

use App\Domain\Budget\Enums\ApprovalContributorKind;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Enums\ExpenseType;
use Database\Factories\BudgetApprovalItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use LogicException;

#[Fillable([
    'tenant_id', 'planning_year_id', 'budget_approval_id', 'budget_basis', 'source_identity',
    'source_lock_version', 'component_kind', 'expense_id', 'expense_row_id', 'expense_kind',
    'expense_title', 'row_type', 'row_description', 'cost_center_id', 'cost_center_name',
    'vendor_id', 'vendor_name', 'project_id', 'project_title', 'contract_id', 'contract_title',
    'net_amount', 'vat_amount', 'gross_amount', 'official_amount',
])]
class BudgetApprovalItem extends Model
{
    /** @use HasFactory<BudgetApprovalItemFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'source_lock_version' => 'integer',
            'component_kind' => ApprovalContributorKind::class,
            'expense_kind' => ExpenseKind::class,
            'row_type' => ExpenseType::class,
            'net_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'official_amount' => 'decimal:2',
        ];
    }

    /** @param Builder<static> $query */
    protected function performUpdate(Builder $query): bool
    {
        throw new LogicException('Budget approval items are immutable.');
    }

    protected function performDeleteOnModel(): void
    {
        throw new LogicException('Budget approval items are immutable.');
    }

    /** @param QueryBuilder $query */
    public function newEloquentBuilder($query): BudgetApprovalItemBuilder
    {
        return new BudgetApprovalItemBuilder($query);
    }

    /** @return BelongsTo<BudgetApproval, $this> */
    public function approval(): BelongsTo
    {
        return $this->belongsTo(BudgetApproval::class, 'budget_approval_id');
    }
}

/** @extends Builder<BudgetApprovalItem> */
final class BudgetApprovalItemBuilder extends Builder
{
    /** @param array<string, mixed> $values */
    public function update(array $values): never
    {
        throw new LogicException('Budget approval items are immutable.');
    }

    public function delete(): never
    {
        throw new LogicException('Budget approval items are immutable.');
    }

    public function forceDelete(): never
    {
        throw new LogicException('Budget approval items are immutable.');
    }
}
