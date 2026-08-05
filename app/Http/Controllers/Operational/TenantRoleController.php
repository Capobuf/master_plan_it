<?php

namespace App\Http\Controllers\Operational;

use App\Domain\IdentityAccess\Actions\CreateTenantRole;
use App\Domain\IdentityAccess\Actions\DeleteTenantRole;
use App\Domain\IdentityAccess\Actions\UpdateTenantRole;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthorizeApplicationAbility;
use App\Support\Authorization\PermissionCatalogue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

final class TenantRoleController extends Controller
{
    public function __construct(private readonly AuthorizeApplicationAbility $authorizeAbility) {}

    public function index(Request $request): Response
    {
        $roles = TenantOwnedRecordQuery::forTenant($this->tenantContext($request), Role::class)
            ->where('guard_name', 'web')
            ->with('permissions:id,name')
            ->orderByRaw('LOWER(name)')
            ->orderBy('id')
            ->get()
            ->map(fn (Role $role): array => $this->roleProps($role))
            ->all();

        return Inertia::render('Operational/Roles/Index', [
            'roles' => $roles,
            'abilities' => $this->crudAbilities($request),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Operational/Roles/Create', [
            'abilities' => $this->abilityOptions(),
            'crudAbilities' => $this->crudAbilities($request),
        ]);
    }

    public function store(Request $request, CreateTenantRole $createTenantRole): RedirectResponse
    {
        $validated = $request->validate($this->roleRules());

        $createTenantRole->execute(
            $this->actor($request),
            $this->tenantContext($request),
            $validated['name'],
            $validated['abilities'],
            $this->correlationId($request),
        );

        return redirect()
            ->route('operational.roles.index')
            ->with('success', 'Role created.');
    }

    public function edit(Request $request, int $role): Response
    {
        $target = $this->role($this->tenantContext($request), $role);
        $target->load('permissions:id,name');

        return Inertia::render('Operational/Roles/Edit', [
            'role' => $this->roleProps($target),
            'abilities' => $this->abilityOptions(),
            'crudAbilities' => $this->crudAbilities($request),
        ]);
    }

    public function update(
        Request $request,
        int $role,
        UpdateTenantRole $updateTenantRole,
    ): RedirectResponse {
        $validated = $request->validate($this->roleRules());
        $context = $this->tenantContext($request);

        $updateTenantRole->execute(
            $this->actor($request),
            $context,
            $this->role($context, $role),
            $validated['name'],
            $validated['abilities'],
            $this->correlationId($request),
        );

        return redirect()
            ->route('operational.roles.edit', $role)
            ->with('success', 'Role updated.');
    }

    public function destroy(
        Request $request,
        int $role,
        DeleteTenantRole $deleteTenantRole,
    ): RedirectResponse {
        $context = $this->tenantContext($request);

        $deleteTenantRole->execute(
            $this->actor($request),
            $context,
            $this->role($context, $role),
            $this->correlationId($request),
        );

        return redirect()
            ->route('operational.roles.index')
            ->with('success', 'Role deleted.');
    }

    /** @return array<string, list<string>> */
    private function roleRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['present', 'array'],
            'abilities.*' => ['required', 'string', 'distinct'],
        ];
    }

    private function role(TenantContext $context, int $id): Role
    {
        /** @var Role $role */
        $role = TenantOwnedRecordQuery::findOrFail($context, Role::class, $id);

        return $role;
    }

    /** @return list<array{value: string, label: string}> */
    private function abilityOptions(): array
    {
        return array_map(static fn (string $ability): array => [
            'value' => $ability,
            'label' => str($ability)->replace(['-', '.'], ' ')->title()->toString(),
        ], PermissionCatalogue::tenantAbilities());
    }

    /** @return array{id: int, name: string, abilities: list<string>} */
    private function roleProps(Role $role): array
    {
        return [
            'id' => (int) $role->getKey(),
            'name' => (string) $role->name,
            'abilities' => $role->permissions
                ->pluck('name')
                ->map(static fn (mixed $ability): string => (string) $ability)
                ->sort()
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, bool> */
    private function crudAbilities(Request $request): array
    {
        $canManage = $this->authorizeAbility->allows(
            $request,
            $this->actor($request),
            'platform.roles.manage',
        );

        return [
            'create' => $canManage,
            'update' => $canManage,
            'delete' => $canManage,
        ];
    }
}
