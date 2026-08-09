<?php

namespace App\Policies;

use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Models\Expense;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantOwnership;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Auth\Access\Response;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\PermissionRegistrar;

final class ExpensePolicy
{
    use AuthorizesTenantOwnership;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PermissionRegistrar $permissionRegistrar,
        private readonly PlatformAdministrator $platformAdministrator,
    ) {}

    public function viewAny(User $user): Response
    {
        return $this->authorizeCollection($user, 'expense.view');
    }

    public function view(User $user, Expense $expense): Response
    {
        return $this->authorizeRecord($user, 'expense.view', $expense);
    }

    public function create(User $user): Response
    {
        return $this->authorizeCollection($user, 'expense.create');
    }

    public function update(User $user, Expense $expense): Response
    {
        return $this->authorizeRecord($user, 'expense.update', $expense);
    }

    public function manageBudget(User $user): Response
    {
        return $this->authorizeCollection($user, 'expense.update');
    }

    public function delete(User $user, Expense $expense): Response
    {
        return $this->authorizeRecord($user, 'expense.delete', $expense);
    }

    public function restoreRevision(User $user, Expense $expense): Response
    {
        return $this->authorizeRecord($user, 'expense.restore-revision', $expense);
    }

    public function viewRevisions(User $user, Expense $expense): Response
    {
        return $this->authorizeRecord($user, 'expense.view-revisions', $expense);
    }

    public function viewAttachment(User $user, Expense $expense): Response
    {
        return $this->authorizeRecordWithParentAbility($user, 'attachment.view', 'expense.view', $expense);
    }

    public function uploadAttachment(User $user, Expense $expense): Response
    {
        return $this->authorizeRecordWithParentAbility($user, 'attachment.upload', 'expense.update', $expense);
    }

    public function deleteAttachment(User $user, Expense $expense): Response
    {
        return $this->authorizeRecordWithParentAbility($user, 'attachment.delete', 'expense.delete', $expense);
    }

    public function viewRevisionAttachment(User $user, Expense $expense): Response
    {
        return $this->authorizeRecordWithParentAbility($user, 'attachment.view', 'expense.view-revisions', $expense);
    }

    public function print(User $user, Expense $expense): Response
    {
        return $this->authorizeRecord($user, 'expense.print', $expense);
    }

    public function export(User $user, Expense $expense): Response
    {
        return $this->authorizeRecord($user, 'expense.export', $expense);
    }

    private function authorizeRecord(User $user, string $ability, Expense $expense): Response
    {
        $response = $this->authorizeTenantOwnership(
            $user,
            $ability,
            $this->tenantContext,
            $expense,
            $this->permissionRegistrar,
            $this->platformAdministrator,
        );

        return $response->denied() ? $response : $this->authorizeActiveTenant();
    }

    private function authorizeRecordWithParentAbility(
        User $user,
        string $attachmentAbility,
        string $parentAbility,
        Expense $expense,
    ): Response {
        $parentResponse = $this->authorizeRecord($user, $parentAbility, $expense);

        if ($parentResponse->denied()) {
            return $parentResponse;
        }

        return $this->authorizeRecord($user, $attachmentAbility, $expense);
    }

    private function authorizeCollection(User $user, string $ability): Response
    {
        $persistedUser = $this->persistedActiveUser($user);

        if ($persistedUser === null || ! $this->hasExactAbility($persistedUser, $ability)) {
            return Response::deny('PERMISSION_DENIED');
        }

        if (! $this->contextMatches($persistedUser)) {
            return Response::deny('TENANT_CONTEXT_REQUIRED');
        }

        return $this->authorizeActiveTenant();
    }

    private function persistedActiveUser(User $user): ?User
    {
        $keyName = $user->getKeyName();
        $key = $user->getKey();
        $originalKey = $user->getRawOriginal($keyName);

        if (! $user->exists || $key === null || $key !== $originalKey) {
            return null;
        }

        return User::query()
            ->whereKey($originalKey)
            ->where('is_active', true)
            ->first();
    }

    private function hasExactAbility(User $user, string $ability): bool
    {
        try {
            if ($user->tenant_id === null) {
                return $this->platformAdministrator->allows($user, $ability);
            }

            return $this->permissionRegistrar->getPermissionsTeamId() === (int) $user->tenant_id
                && $user->checkPermissionTo($ability, 'web');
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    private function contextMatches(User $user): bool
    {
        $contextActor = $this->tenantContext->actor;
        $contextTenant = $this->tenantContext->tenant;

        $actorKey = $contextActor->getKey();
        $actorOriginalKey = $contextActor->getRawOriginal($contextActor->getKeyName());
        $tenantKey = $contextTenant->getKey();
        $tenantOriginalKey = $contextTenant->getRawOriginal($contextTenant->getKeyName());

        if (! $contextActor->exists
            || $actorKey === null
            || $actorKey !== $actorOriginalKey
            || $actorKey !== $user->getKey()) {
            return false;
        }

        if (! $contextTenant->exists
            || $tenantKey === null
            || $tenantKey !== $tenantOriginalKey
            || (int) $tenantKey !== $this->tenantContext->tenantId
            || ! Tenant::query()->whereKey($tenantOriginalKey)->exists()) {
            return false;
        }

        return $user->tenant_id === null || (int) $user->tenant_id === $this->tenantContext->tenantId;
    }

    private function authorizeActiveTenant(): Response
    {
        $contextTenant = $this->tenantContext->tenant;
        $tenantKey = $contextTenant->getKey();
        $tenantOriginalKey = $contextTenant->getRawOriginal($contextTenant->getKeyName());

        if (! $contextTenant->exists
            || $tenantKey === null
            || $tenantOriginalKey === null
            || $tenantKey !== $tenantOriginalKey
            || (int) $tenantOriginalKey !== $this->tenantContext->tenantId) {
            return Response::deny('TENANT_CONTEXT_REQUIRED');
        }

        $persistedTenant = Tenant::query()->whereKey($tenantOriginalKey)->first();

        if ($persistedTenant === null) {
            return Response::deny('TENANT_CONTEXT_REQUIRED');
        }

        return $persistedTenant->state === TenantState::Active
            ? Response::allow()
            : Response::deny('TENANT_INACTIVE');
    }
}
