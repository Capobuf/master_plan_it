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

    /** @param list<SaveExpenseRowData> $rows */
    private function saveAggregate(Expense $expense, Tenant $tenant, SaveExpenseData $data, array $rows, User $actor): array
    {
        $validated = app(ExpenseAggregateValidator::class)->validate($tenant, $data, $rows, $expense->exists ? $expense : null);
        $expense->fill($validated['header']);
        $expense->tenant_id = $tenant->getKey();
        $expense->save();

        $existing = $expense->rows()->lockForUpdate()->get()->keyBy(fn (ExpenseRow $row): int => (int) $row->getKey());
        $kept = [];
        $changed = [$expense];
        foreach ($validated['rows'] as $attributes) {
            $id = $attributes['id'];
            unset($attributes['id']);
            $expected = $attributes['expected_lock_version'];
            unset($attributes['expected_lock_version']);
            $row = $id === null ? new ExpenseRow : $existing->get((int) $id);
            if (! $row instanceof ExpenseRow) {
                throw new DomainException('TENANT_RELATION_MISMATCH');
            }
            if ($row->exists && (int) $row->lock_version !== (int) $expected) {
                throw new DomainException('STALE_VERSION');
            }
            $oldType=$row->exists?$row->type:null;$wasSystemManaged=$row->exists&&$row->type===ExpenseType::Actual&&$row->is_system_managed;
            $row->fill($attributes);
            if ($wasSystemManaged && $row->isDirty()) {
                $attributes['is_system_managed'] = false;
                $attributes['manual_override_at'] = CarbonImmutable::now('UTC');
                $row->fill(['is_system_managed'=>false,'manual_override_at'=>$attributes['manual_override_at']]);
            }
            if (! $row->exists) {
                $attributes['confirmation_state'] = $attributes['type'] === ExpenseType::Actual
                    ? ActualConfirmationState::Confirmed : null;
                $attributes['confirmed_by_user_id'] = $attributes['type'] === ExpenseType::Actual ? $actor->getKey() : null;
                $attributes['confirmed_at'] = $attributes['type'] === ExpenseType::Actual ? CarbonImmutable::now('UTC') : null;
                $attributes['is_system_managed'] = false;
                $row->tenant_id = $tenant->getKey();
                $row->expense_id = $expense->getKey();
            } else {
                if($oldType!==$attributes['type']){
                    if($attributes['type']===ExpenseType::Actual){$attributes['confirmation_state']=ActualConfirmationState::Confirmed;$attributes['confirmed_by_user_id']=$actor->getKey();$attributes['confirmed_at']=CarbonImmutable::now('UTC');$attributes['is_system_managed']=false;$attributes['manual_override_at']=null;}
                    else{$attributes['confirmation_state']=null;$attributes['confirmed_by_user_id']=null;$attributes['confirmed_at']=null;$attributes['is_system_managed']=false;$attributes['manual_override_at']=null;}
                }
                $attributes['lock_version'] = (int) $row->lock_version + 1;
            }
            $row->fill($attributes);
            $row->save();
            $kept[(int) $row->getKey()] = true;
            $changed[] = $row;
        }
        foreach ($existing as $row) {
            if (! isset($kept[(int) $row->getKey()])) {
                $row->delete();
                $changed[] = $row;
            }
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

    private function audit(string $event, string $correlationId, User $actor, Tenant $tenant, Expense $expense, array $properties = []): void
    {
        app(AuditRecorder::class)->record($event, $correlationId, new AuditProperties($properties), $actor, (int) $tenant->getKey(), $expense);
    }
}
