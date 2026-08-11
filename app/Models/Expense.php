<?php

namespace App\Models;

use App\Domain\Expenses\Enums\ExpenseClosureOutcome;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Enums\ExpenseState;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Overtrue\LaravelVersionable\Versionable;
use Overtrue\LaravelVersionable\VersionStrategy;

#[Fillable([
    'planning_year_id',
    'cost_center_id',
    'kind',
    'title',
    'notes',
    'project_id',
    'contract_id',
    'state',
    'closure_outcome',
    'current_planning_row_id',
    'moved_from_expense_id',
    'credit_for_expense_id',
    'lock_version',
])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory, SoftDeletes, Versionable;

    /** @var list<string> */
    protected array $versionable = [
        'tenant_id',
        'kind',
        'planning_year_id',
        'cost_center_id',
        'title',
        'notes',
        'project_id',
        'contract_id',
        'approved_amount',
        'approved_basis',
        'state',
        'closure_outcome',
        'closed_at',
        'closed_by_user_id',
        'current_planning_row_id',
        'moved_from_expense_id',
        'credit_for_expense_id',
        'lock_version',
    ];

    protected VersionStrategy $versionStrategy = VersionStrategy::SNAPSHOT;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ExpenseKind::class,
            'state' => ExpenseState::class,
            'closure_outcome' => ExpenseClosureOutcome::class,
            'approved_amount' => 'decimal:2',
            'closed_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<PlanningYear, $this>
     */
    public function planningYear(): BelongsTo
    {
        return $this->belongsTo(PlanningYear::class);
    }

    /**
     * @return BelongsTo<CostCenter, $this>
     */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /**
     * @return HasMany<ExpenseRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(ExpenseRow::class);
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<ExpenseRow, $this> */
    public function currentPlanningRow(): BelongsTo
    {
        return $this->belongsTo(ExpenseRow::class, 'current_planning_row_id');
    }

    /** @return BelongsTo<Expense, $this> */
    public function movedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'moved_from_expense_id');
    }

    /** @return BelongsTo<Expense, $this> */
    public function creditFor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'credit_for_expense_id');
    }

    /** @return HasMany<ApprovalItem, $this> */
    public function approvalItems(): HasMany
    {
        return $this->hasMany(ApprovalItem::class);
    }
}
