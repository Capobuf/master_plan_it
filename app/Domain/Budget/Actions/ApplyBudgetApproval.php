<?php

namespace App\Domain\Budget\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\Budget\Data\ApplyApprovalData;
use App\Domain\Budget\Data\ApprovalChangeData;
use App\Domain\Budget\Enums\ApprovalKind;
use App\Domain\Budget\Enums\BudgetState;
use App\Domain\Money\Money;
use App\Domain\Revisions\Actions\BeginRevisionBatch;
use App\Domain\Revisions\Actions\LinkVersionToRevisionBatch;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\ApprovalItem;
use App\Models\ApprovalOperation;
use App\Models\Expense;
use App\Models\PlanningYear;
use App\Models\User;
use App\Models\Version;
use App\Policies\ExpensePolicy;
use App\Support\Authorization\PlatformAdministrator;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

final class ApplyBudgetApproval
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function execute(
        User $actor,
        TenantContext $context,
        PlanningYear $target,
        ApplyApprovalData $data,
        string $correlationId,
    ): ApprovalOperation {
        $this->policy($context)->manageBudget($actor)->authorize();

        if ($data->items === []) {
            throw ValidationException::withMessages(['items' => 'At least one expense approval is required.']);
        }
        if (mb_strlen(trim((string) $data->reason)) > 500) {
            throw ValidationException::withMessages(['reason' => 'The reason may not exceed 500 characters.']);
        }

        return DB::transaction(function () use ($actor, $context, $correlationId, $data, $target): ApprovalOperation {
            $year = PlanningYear::query()
                ->where('tenant_id', $context->tenantId)
                ->lockForUpdate()
                ->find($target->getKey());
            $persistedActor = User::query()->whereKey($actor->getRawOriginal($actor->getKeyName()))->where('is_active', true)->first();

            if (! $year instanceof PlanningYear || ! $persistedActor instanceof User || (int) $year->lock_version !== $data->budgetLockVersion) {
                throw new DomainException('STALE_VERSION');
            }

            $changes = collect($data->items);
            if ($changes->contains(fn ($item) => ! $item instanceof ApprovalChangeData)
                || $changes->pluck('expenseId')->unique()->count() !== $changes->count()) {
                throw ValidationException::withMessages(['items' => 'Each expense may appear only once.']);
            }

            $ids = $changes->pluck('expenseId')->sort()->values()->all();
            $expenses = Expense::query()
                ->where('tenant_id', $context->tenantId)
                ->where('planning_year_id', $year->getKey())
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            if ($expenses->count() !== count($ids)) {
                throw (new ModelNotFoundException)->setModel(Expense::class, $ids);
            }

            $basis = $context->budgetBasis->value;
            $normalized = [];
            $previousAmounts = [];
            foreach ($changes as $change) {
                $expense = $expenses->get($change->expenseId);
                if (! $expense instanceof Expense || (int) $expense->lock_version !== $change->expectedLockVersion) {
                    throw new DomainException('STALE_VERSION');
                }
                try {
                    $amount = Money::fromDecimal($change->approvedAmount, $context->currencyCode)->amount();
                } catch (\Throwable) {
                    throw ValidationException::withMessages(['items' => 'Approved amounts must be exact decimals.']);
                }
                if (bccomp($amount, '0', 2) < 0) {
                    throw ValidationException::withMessages(['items' => 'Approved amounts cannot be negative.']);
                }
                $previousAmounts[(int) $expense->getKey()] = $expense->approved_amount === null ? null : (string) $expense->approved_amount;
                $normalized[(int) $expense->getKey()] = $amount;
            }

            $kind = ApprovalOperation::query()
                ->where('tenant_id', $context->tenantId)
                ->where('planning_year_id', $year->getKey())
                ->exists() ? ApprovalKind::Variation : ApprovalKind::Initial;
            $year->forceFill([
                'budget_state' => $year->budget_state === BudgetState::Preparation ? BudgetState::Approved : $year->budget_state,
                'lock_version' => $year->lock_version + 1,
            ])->save();

            foreach ($expenses as $expense) {
                $expense->forceFill([
                    'approved_amount' => $normalized[(int) $expense->getKey()],
                    'approved_basis' => $basis,
                    'lock_version' => $expense->lock_version + 1,
                ])->save();
            }

            $batch = app(BeginRevisionBatch::class)->execute(
                $persistedActor,
                $context,
                RevisionOperation::Update,
                trim((string) $data->reason) ?: null,
                $correlationId,
                $year,
                null,
            );
            $sequence = 1;
            foreach (collect([$year])->concat($expenses) as $model) {
                $version = $model->versions()->orderByDesc('id')->first();
                if ($version instanceof Version) {
                    app(LinkVersionToRevisionBatch::class)->execute($batch, $version, $sequence++);
                }
            }

            $recordedAt = CarbonImmutable::now('UTC');
            $operation = ApprovalOperation::query()->create([
                'tenant_id' => $context->tenantId,
                'planning_year_id' => $year->getKey(),
                'kind' => $kind,
                'effective_date' => $data->effectiveDate,
                'recorded_at' => $recordedAt,
                'actor_user_id' => $persistedActor->getKey(),
                'reason' => trim((string) $data->reason) ?: null,
                'budget_basis' => $basis,
                'revision_batch_id' => $batch->getKey(),
                'correlation_id' => $correlationId,
            ]);

            foreach ($expenses as $expense) {
                $previous = $previousAmounts[(int) $expense->getKey()];
                $next = $normalized[(int) $expense->getKey()];
                ApprovalItem::query()->create([
                    'approval_operation_id' => $operation->getKey(),
                    'tenant_id' => $context->tenantId,
                    'planning_year_id' => $year->getKey(),
                    'expense_id' => $expense->getKey(),
                    'previous_amount' => $previous,
                    'new_amount' => $next,
                    'delta_amount' => bcsub($next, $previous === null ? '0.00' : (string) $previous, 2),
                    'cost_center_id' => $expense->cost_center_id,
                    'project_id' => $expense->project_id,
                    'contract_id' => $expense->contract_id,
                    'expense_kind' => (string) $expense->getRawOriginal('kind'),
                    'budget_basis' => $basis,
                ]);
            }

            $this->auditRecorder->record(
                'budget.approval-applied',
                $correlationId,
                new AuditProperties(['kind' => $kind->value, 'items' => $expenses->count()]),
                $persistedActor,
                $context->tenantId,
                $year,
                $recordedAt,
            );

            return $operation->fresh(['items', 'planningYear']);
        });
    }

    private function policy(TenantContext $context): ExpensePolicy
    {
        return new ExpensePolicy($context, app(PermissionRegistrar::class), app(PlatformAdministrator::class));
    }
}
