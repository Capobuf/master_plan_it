<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['approval_operation_id', 'tenant_id', 'planning_year_id', 'expense_id', 'previous_amount', 'new_amount', 'delta_amount', 'cost_center_id', 'project_id', 'contract_id', 'expense_kind', 'budget_basis'])]
class ApprovalItem extends Model
{
    protected function casts(): array
    {
        return [
            'previous_amount' => 'decimal:2',
            'new_amount' => 'decimal:2',
            'delta_amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<ApprovalOperation, $this> */
    public function operation(): BelongsTo
    {
        return $this->belongsTo(ApprovalOperation::class, 'approval_operation_id');
    }

    /** @return BelongsTo<Expense, $this> */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}
