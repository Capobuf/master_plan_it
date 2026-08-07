<?php

namespace App\Domain\Expenses\Actions\Concerns;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\Expenses\Data\SaveExpenseData;
use App\Domain\Expenses\Data\SaveExpenseRowData;
use App\Domain\Expenses\Enums\ActualConfirmationState;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Expenses\Services\ExpenseAggregateValidator;
use App\Domain\Revisions\Actions\BeginRevisionBatch;
use App\Domain\Revisions\Actions\LinkVersionToRevisionBatch;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
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
        $actorKey=$actor->getKey();$actorOriginal=$actor->getRawOriginal($actor->getKeyName());$contextActorKey=$context->actor->getKey();$contextActorOriginal=$context->actor->getRawOriginal($context->actor->getKeyName());$tenantKey=$context->tenant->getKey();$tenantOriginal=$context->tenant->getRawOriginal($context->tenant->getKeyName());
        if(!$actor->exists||$actorKey===null||$actorKey!==$actorOriginal||!$context->actor->exists||$contextActorKey!==$contextActorOriginal||$contextActorKey!==$actorKey||!$context->tenant->exists||$tenantKey!==$tenantOriginal||(int)$tenantKey!==$context->tenantId){throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');}
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
     * @param list<SaveExpenseRowData> $rows
     * @param list<array{id: int, lock_version: int}> $deletedRows
     * @return list<Expense|ExpenseRow>
     */
    private function saveAggregate(Expense $expense, Tenant $tenant, SaveExpenseData $data, array $rows, User $actor, array $deletedRows = []): array
    {
        $validated = app(ExpenseAggregateValidator::class)->validate($tenant, $data, $rows, $expense->exists ? $expense : null);
        $expense->fill($validated['header']);
        $expense->tenant_id = $tenant->getKey();
        $expense->save();

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
        $changed = [$expense];
        foreach ($validated['rows'] as $attributes) {
            $id = $attributes['id'];
            unset($attributes['id']);
            $expected = $attributes['expected_lock_version'];
            unset($attributes['expected_lock_version']);
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
            $oldType=$row->exists?$row->type:null;$wasSystemManaged=$row->exists&&$row->type===ExpenseType::Actual&&$row->is_system_managed;
            $row->fill($attributes);
            $row->forceFill($serverAttributes);
            if ($wasSystemManaged && $row->isDirty()) {
                $serverAttributes['is_system_managed'] = false;
                $serverAttributes['manual_override_at'] = CarbonImmutable::now('UTC');
                $row->forceFill(['is_system_managed'=>false,'manual_override_at'=>$serverAttributes['manual_override_at']]);
            }
            if (! $row->exists) {
                $serverAttributes['confirmation_state'] = $attributes['type'] === ExpenseType::Actual
                    ? ActualConfirmationState::ToConfirm : null;
                $serverAttributes['confirmed_by_user_id'] = null;
                $serverAttributes['confirmed_at'] = null;
                $serverAttributes['is_system_managed'] = false;
                $row->tenant_id = $tenant->getKey();
                $row->expense_id = $expense->getKey();
            } else {
                if($oldType!==$attributes['type']){
                    if($attributes['type']===ExpenseType::Actual){$serverAttributes['confirmation_state']=ActualConfirmationState::ToConfirm;$serverAttributes['confirmed_by_user_id']=null;$serverAttributes['confirmed_at']=null;$serverAttributes['is_system_managed']=false;$serverAttributes['manual_override_at']=null;}
                    else{$serverAttributes['confirmation_state']=null;$serverAttributes['confirmed_by_user_id']=null;$serverAttributes['confirmed_at']=null;$serverAttributes['is_system_managed']=false;$serverAttributes['manual_override_at']=null;}
                }
                $attributes['lock_version'] = (int) $row->lock_version + 1;
            }
            $row->fill($attributes);
            $row->forceFill($serverAttributes);
            $row->save();
            $submitted[(int) $row->getKey()] = true;
            $changed[] = $row;
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
            $row->delete();
            $changed[] = $row;
        }
        return $changed;
    }

    /** @param list<Expense|ExpenseRow> $models */
    private function revisions(User $actor, TenantContext $context, RevisionOperation $operation, string $correlationId, Expense $root, array $models): void
    {
        $batch = app(BeginRevisionBatch::class)->execute($actor, $context, $operation, null, $correlationId, $root, null);
        $sequence = 1;
        foreach ($models as $model) {
            $version = $model->latestVersions()->first();
            if ($version instanceof Version) {
                app(LinkVersionToRevisionBatch::class)->execute($batch, $version, $sequence++);
            }
        }
    }

    /** @param array<string, mixed> $properties */
    private function audit(string $event, string $correlationId, User $actor, Tenant $tenant, Expense $expense, array $properties = []): void
    {
        app(AuditRecorder::class)->record($event, $correlationId, new AuditProperties($properties), $actor, (int) $tenant->getKey(), $expense);
    }
}
