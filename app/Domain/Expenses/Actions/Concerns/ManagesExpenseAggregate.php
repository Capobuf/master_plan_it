<?php

namespace App\Domain\Expenses\Actions\Concerns;

use App\Domain\Attachments\Actions\PurgeAttachments;
use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\Expenses\Data\SaveExpenseData;
use App\Domain\Expenses\Data\SaveExpenseRowData;
use App\Domain\Expenses\Enums\ExpenseState;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Expenses\Services\ExpenseAggregateValidator;
use App\Domain\Revisions\Actions\BeginRevisionBatch;
use App\Domain\Revisions\Actions\LinkVersionToRevisionBatch;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Version;
use App\Policies\ExpensePolicy;
use App\Support\Authorization\PlatformAdministrator;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\PermissionRegistrar;

trait ManagesExpenseAggregate
{
    private function expensePolicy(TenantContext $context): ExpensePolicy
    {
        return new ExpensePolicy($context, app(PermissionRegistrar::class), app(PlatformAdministrator::class));
    }

    /** @return array{User,Tenant} */
    private function persistedContext(User $actor, TenantContext $context): array
    {
        $actorKey = $actor->getKey();
        $actorOriginal = $actor->getRawOriginal($actor->getKeyName());
        $contextActorKey = $context->actor->getKey();
        $contextActorOriginal = $context->actor->getRawOriginal($context->actor->getKeyName());
        $tenantKey = $context->tenant->getKey();
        $tenantOriginal = $context->tenant->getRawOriginal($context->tenant->getKeyName());
        if (! $actor->exists || $actorKey === null || $actorKey !== $actorOriginal || ! $context->actor->exists || $contextActorKey !== $contextActorOriginal || $contextActorKey !== $actorKey || ! $context->tenant->exists || $tenantKey !== $tenantOriginal || (int) $tenantKey !== $context->tenantId) {
            throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
        }
        $persistedActor = User::query()->whereKey($actor->getRawOriginal($actor->getKeyName()))->where('is_active', true)->first();
        $tenant = Tenant::query()->whereKey($context->tenantId)->first();
        if (! $persistedActor instanceof User || ! $tenant instanceof Tenant || (int) $context->tenant->getKey() !== $context->tenantId) {
            throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
        }
        if ($tenant->state !== TenantState::Active) {
            throw new AuthorizationException('TENANT_INACTIVE');
        }
        if ($persistedActor->tenant_id !== null && (int) $persistedActor->tenant_id !== (int) $tenant->getKey()) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        return [$persistedActor, $tenant];
    }

    /**
     * @param  list<SaveExpenseRowData>  $rows
     * @param  list<array{id: int, lock_version: int}>  $deletedRows
     * @return list<Expense|ExpenseRow>
     */
    private function saveAggregate(Expense $expense, Tenant $tenant, SaveExpenseData $data, array $rows, User $actor, array $deletedRows = []): array
    {
        $validated = app(ExpenseAggregateValidator::class)->validate($tenant, $data, $rows, $expense->exists ? $expense : null);
        $wasExisting = $expense->exists;
        $wasClosed = $wasExisting && $expense->state === ExpenseState::Closed;
        $originalEconomic = $wasExisting ? $expense->only(['planning_year_id', 'cost_center_id', 'kind', 'project_id', 'contract_id']) : [];
        $expense->fill($validated['header']);
        $expense->tenant_id = $tenant->getKey();
        if (! $wasExisting) {
            $expense->save();
        }

        $existing = $expense->rows()->lockForUpdate()->get()->keyBy(fn (ExpenseRow $row): int => (int) $row->getKey());
        $submittedIds = array_filter(array_column($validated['rows'], 'id'));
        $deletedIds = array_column($deletedRows, 'id');
        $positions = [];
        foreach ($existing as $existingRow) {
            if (! in_array((int) $existingRow->getKey(), $submittedIds, true)
                && ! in_array((int) $existingRow->getKey(), $deletedIds, true)) {
                $positions[(int) $existingRow->position] = true;
            }
        }
        foreach ($validated['rows'] as $attributes) {
            if (isset($positions[$attributes['position']])) {
                throw new DomainException('TENANT_RELATION_MISMATCH');
            }
            $positions[$attributes['position']] = true;
        }
        $submitted = [];
        $changed = $wasExisting ? [] : [$expense];
        $selectedPlanningRowId = null;
        $rowEconomicChanged = false;
        foreach ($validated['rows'] as $attributes) {
            $id = $attributes['id'];
            unset($attributes['id']);
            $expected = $attributes['expected_lock_version'];
            unset($attributes['expected_lock_version']);
            $isCurrentPlanning = (bool) $attributes['is_current_planning'];
            unset($attributes['is_current_planning']);
            $authoritativeAmounts = [
                'net_amount' => $attributes['net_amount'],
                'vat_amount' => $attributes['vat_amount'],
                'gross_amount' => $attributes['gross_amount'],
            ];
            $serverAttributes = $authoritativeAmounts;
            foreach (['confirmation_state', 'confirmed_by_user_id', 'confirmed_at', 'is_system_managed', 'manual_override_at', 'contract_term_id', 'source_key'] as $serverAttribute) {
                if (array_key_exists($serverAttribute, $attributes)) {
                    $serverAttributes[$serverAttribute] = $attributes[$serverAttribute];
                    unset($attributes[$serverAttribute]);
                }
            }
            unset($attributes['net_amount'], $attributes['vat_amount'], $attributes['gross_amount']);
            $row = $id === null ? new ExpenseRow : $existing->get((int) $id);
            if (! $row instanceof ExpenseRow) {
                throw new DomainException('TENANT_RELATION_MISMATCH');
            }
            if ($row->exists && (int) $row->lock_version !== (int) $expected) {
                throw new DomainException('STALE_VERSION');
            }
            $oldType = $row->exists ? $row->type : null;
            $wasSystemManaged = $row->exists && $row->is_system_managed;
            $beforeEconomic = $row->exists ? $row->only(['type', 'quantity', 'unit_price', 'entered_amount', 'amount_includes_vat', 'vat_rate', 'net_amount', 'vat_amount', 'gross_amount', 'is_extra', 'funded_plafond_expense_id', 'spend_date']) : [];
            $row->fill($attributes);
            $row->forceFill($serverAttributes);
            if ($wasSystemManaged && $row->isDirty()) {
                $serverAttributes['is_system_managed'] = false;
                $serverAttributes['manual_override_at'] = CarbonImmutable::now('UTC');
                $row->forceFill(['is_system_managed' => false, 'manual_override_at' => $serverAttributes['manual_override_at']]);
            }
            if (! $row->exists) {
                $serverAttributes['confirmation_state'] = null;
                $serverAttributes['confirmed_by_user_id'] = null;
                $serverAttributes['confirmed_at'] = null;
                $serverAttributes['is_system_managed'] = false;
                $row->tenant_id = $tenant->getKey();
                $row->expense_id = $expense->getKey();
            } else {
                if ($oldType !== $attributes['type']) {
                    $serverAttributes['confirmation_state'] = null;
                    $serverAttributes['confirmed_by_user_id'] = null;
                    $serverAttributes['confirmed_at'] = null;
                    $serverAttributes['is_system_managed'] = false;
                    $serverAttributes['manual_override_at'] = null;
                }
            }
            $row->fill($attributes);
            $row->forceFill($serverAttributes);
            $rowWasCreated = ! $row->exists;
            if ($rowWasCreated) {
                $row->save();
                $changed[] = $row;
            } elseif ($row->isDirty()) {
                $row->forceFill(['lock_version' => (int) $row->lock_version + 1])->save();
                $changed[] = $row;
            }
            if (! $rowWasCreated && $beforeEconomic !== $row->only(array_keys($beforeEconomic))) {
                $rowEconomicChanged = true;
            }
            if ($rowWasCreated) {
                $rowEconomicChanged = true;
            }
            if ($isCurrentPlanning) {
                $selectedPlanningRowId = (int) $row->getKey();
            }
            if ((int) $expense->current_planning_row_id === (int) $row->getKey()
                && ! in_array($row->type, [ExpenseType::Estimate, ExpenseType::Quote], true)) {
                $expense->current_planning_row_id = null;
                $rowEconomicChanged = true;
            }
            $submitted[(int) $row->getKey()] = true;
        }

        $deleted = [];
        foreach ($deletedRows as $deletedRow) {
            $row = $existing->get($deletedRow['id']);
            if (! $row instanceof ExpenseRow || isset($submitted[(int) $row->getKey()])) {
                throw new DomainException('TENANT_RELATION_MISMATCH');
            }
            if ((int) $row->lock_version !== $deletedRow['lock_version']) {
                throw new DomainException('STALE_VERSION');
            }
            $deleted[(int) $row->getKey()] = $row;
        }

        if ($existing->count() - count($deleted) + count(array_filter($rows, static fn (SaveExpenseRowData $row): bool => $row->id === null)) < 1) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        foreach ($deleted as $row) {
            if ((int) $expense->current_planning_row_id === (int) $row->getKey()) {
                $expense->current_planning_row_id = null;
            }
            $row->delete();
            $changed[] = $row;
            $rowEconomicChanged = true;
        }

        if ($selectedPlanningRowId !== null && (int) $expense->current_planning_row_id !== $selectedPlanningRowId) {
            $expense->current_planning_row_id = $selectedPlanningRowId;
            $rowEconomicChanged = true;
        }
        $headerEconomicChanged = $expense->only(array_keys($originalEconomic)) !== $originalEconomic;
        if ($wasClosed && ($headerEconomicChanged || $rowEconomicChanged)) {
            $expense->forceFill([
                'state' => ExpenseState::Open,
                'closure_outcome' => null,
                'closed_at' => null,
                'closed_by_user_id' => null,
            ]);
        }
        $rootBusinessChanged = $expense->isDirty();
        if ($wasExisting && ($rootBusinessChanged || $changed !== [])) {
            $expense->forceFill(['lock_version' => (int) $expense->lock_version + 1])->save();
            if ($rootBusinessChanged) {
                array_unshift($changed, $expense);
            }
        } elseif (! $wasExisting && $rootBusinessChanged) {
            $expense->save();
        }

        return $changed;
    }

    /** @param list<array{id: int, lock_version: int}> $deletedRows */
    private function purgeDeletedRowAttachments(User $actor, TenantContext $context, array $deletedRows, string $correlationId): void
    {
        foreach ($deletedRows as $deletedRow) {
            $row = TenantOwnedRecordQuery::forTenant($context, ExpenseRow::class)
                ->withTrashed()
                ->whereKey($deletedRow['id'])
                ->first();
            if ($row instanceof ExpenseRow) {
                app(PurgeAttachments::class)
                    ->forParent($actor, $context, $row, $correlationId);
            }
        }
    }

    /** @param list<Expense|ExpenseRow> $models */
    private function revisions(
        User $actor,
        TenantContext $context,
        RevisionOperation $operation,
        string $correlationId,
        Expense $root,
        array $models,
        ?int $restoredFromBatchId = null,
    ): void {
        if ($models === []) {
            return;
        }
        $changed = [];
        foreach ($models as $model) {
            $changed[$model->getMorphClass().'#'.$model->getKey()] = true;
        }
        if ($operation !== RevisionOperation::Delete) {
            $models = [
                $root,
                ...$root->rows()->orderBy('position')->orderBy('id')->get()->all(),
            ];
        }
        $batch = app(BeginRevisionBatch::class)->execute(
            $actor,
            $context,
            $operation,
            null,
            $correlationId,
            $root,
            restoredFromBatchId: $restoredFromBatchId,
        );
        $sequence = 1;
        $seen = [];
        foreach ($models as $model) {
            $identity = $model->getMorphClass().'#'.$model->getKey();
            if (isset($seen[$identity])) {
                continue;
            }
            $seen[$identity] = true;
            $version = $model->versions()->orderByDesc('id')->first();
            if ($version instanceof Version) {
                app(LinkVersionToRevisionBatch::class)->execute(
                    $batch,
                    $version,
                    $sequence++,
                    changed: isset($changed[$identity]),
                );
            }
        }
    }

    /** @param array<string, mixed> $properties */
    private function audit(string $event, string $correlationId, User $actor, Tenant $tenant, Expense $expense, array $properties = []): void
    {
        app(AuditRecorder::class)->record($event, $correlationId, new AuditProperties($properties), $actor, (int) $tenant->getKey(), $expense);
    }
}
