<?php

namespace App\Policies;

use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Models\Contract;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantOwnership;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Auth\Access\Response;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\PermissionRegistrar;

final class ContractPolicy
{
    use AuthorizesTenantOwnership;

    public function __construct(private readonly TenantContext $context, private readonly PermissionRegistrar $registrar, private readonly PlatformAdministrator $administrator) {}

    public function viewAny(User $user): Response { return $this->collection($user, 'contract.view'); }
    public function view(User $user, Contract $contract): Response { return $this->record($user, 'contract.view', $contract); }
    public function create(User $user): Response { return $this->collection($user, 'contract.create'); }
    public function update(User $user, Contract $contract): Response { return $this->record($user, 'contract.update', $contract); }
    public function delete(User $user, Contract $contract): Response { return $this->record($user, 'contract.delete', $contract); }
    public function generateOccurrence(User $user, Contract $contract): Response { return $this->record($user, 'contract.generate-occurrence', $contract); }
    public function suppressGeneration(User $user, Contract $contract): Response { return $this->record($user, 'contract.suppress-generation', $contract); }
    public function resumeGeneration(User $user, Contract $contract): Response { return $this->record($user, 'contract.resume-generation', $contract); }
    public function viewRevisions(User $user, Contract $contract): Response { return $this->record($user, 'contract.view-revisions', $contract); }

    private function record(User $user, string $ability, Contract $contract): Response
    {
        $response = $this->authorizeTenantOwnership($user, $ability, $this->context, $contract, $this->registrar, $this->administrator);
        return $response->denied() ? $response : $this->active();
    }

    private function collection(User $user, string $ability): Response
    {
        $persisted = User::query()->whereKey($user->getRawOriginal($user->getKeyName()))->where('is_active', true)->first();
        if (! $persisted instanceof User || ! $this->hasAbility($persisted, $ability)) {
            return Response::deny('PERMISSION_DENIED');
        }
        if ($persisted->tenant_id !== null && (int) $persisted->tenant_id !== $this->context->tenantId) {
            return Response::deny('TENANT_CONTEXT_REQUIRED');
        }
        return $this->active();
    }

    private function active(): Response
    {
        $tenant = Tenant::query()->whereKey($this->context->tenantId)->first();
        return $tenant?->state === TenantState::Active ? Response::allow() : Response::deny($tenant === null ? 'TENANT_CONTEXT_REQUIRED' : 'TENANT_INACTIVE');
    }

    private function hasAbility(User $user, string $ability): bool
    {
        try {
            return $user->tenant_id === null
                ? $this->administrator->allows($user, $ability)
                : $this->registrar->getPermissionsTeamId() === (int) $user->tenant_id && $user->checkPermissionTo($ability, 'web');
        } catch (PermissionDoesNotExist) { return false; }
    }
}
