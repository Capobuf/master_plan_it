<?php

namespace App\Http\Controllers\Operational;

use App\Domain\IdentityAccess\Actions\AssignTenantRoles;
use App\Domain\IdentityAccess\Actions\CreateTenantUser;
use App\Domain\IdentityAccess\Actions\DeactivateTenantUser;
use App\Domain\IdentityAccess\Actions\ResetTenantUserPassword;
use App\Domain\IdentityAccess\Actions\UpdateTenantUser;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthorizeApplicationAbility;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

final class TenantUserController extends Controller
{
    public function __construct(private readonly AuthorizeApplicationAbility $authorizeAbility) {}

    public function index(Request $request): View
    {
        $context = $this->tenantContext($request);
        $search = trim((string) $request->query('q', ''));
        $users = TenantOwnedRecordQuery::forTenant($context, User::class)
            ->with('roles:id,name')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByRaw('LOWER(name)')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('operational.users.index', [
            'users' => $this->paginatedUsers($users),
            'filters' => ['q' => $search],
            'abilities' => $this->abilities($request),
        ]);
    }

    public function create(Request $request): View
    {
        return view('operational.users.create', [
            'roles' => $this->roleOptions($this->tenantContext($request)),
            'abilities' => $this->abilities($request),
        ]);
    }

    public function store(Request $request, CreateTenantUser $createTenantUser): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'integer', 'distinct'],
        ]);
        $context = $this->tenantContext($request);

        $createTenantUser->execute(
            $this->actor($request),
            $context,
            $validated['name'],
            $validated['email'],
            $validated['password'],
            $this->roles($context, $validated['roles']),
            $this->correlationId($request),
        );

        return redirect()
            ->route('operational.users.index')
            ->with('success', 'User created.');
    }

    public function edit(Request $request, int $user): View
    {
        $context = $this->tenantContext($request);
        $target = $this->user($context, $user);
        $target->unsetRelation('roles');

        return view('operational.users.edit', [
            'user' => $this->userProps($target, true),
            'roles' => $this->roleOptions($context),
            'abilities' => $this->abilities($request),
        ]);
    }

    public function update(
        Request $request,
        int $user,
        UpdateTenantUser $updateTenantUser,
        AssignTenantRoles $assignTenantRoles,
    ): RedirectResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'integer', 'distinct'],
        ]);
        $actor = $this->actor($request);
        $context = $this->tenantContext($request);
        $target = $this->user($context, $user);
        $roles = $this->roles($context, $validated['roles']);
        $correlationId = $this->correlationId($request);

        DB::transaction(function () use (
            $actor,
            $assignTenantRoles,
            $context,
            $correlationId,
            $roles,
            $target,
            $updateTenantUser,
            $validated,
        ): void {
            $updated = $updateTenantUser->execute(
                $actor,
                $context,
                $target,
                $validated['name'],
                $validated['email'],
                $correlationId,
            );

            $assignTenantRoles->execute($actor, $context, $updated, $roles, $correlationId);
        });

        return redirect()
            ->route('operational.users.edit', $user)
            ->with('success', 'User updated.');
    }

    public function deactivate(
        Request $request,
        int $user,
        DeactivateTenantUser $deactivateTenantUser,
    ): RedirectResponse {
        $context = $this->tenantContext($request);

        $deactivateTenantUser->execute(
            $this->actor($request),
            $context,
            $this->user($context, $user),
            [],
            $this->correlationId($request),
        );

        return redirect()
            ->route('operational.users.index')
            ->with('success', 'User deactivated.');
    }

    public function resetPassword(
        Request $request,
        int $user,
        ResetTenantUserPassword $resetTenantUserPassword,
    ): RedirectResponse {
        $validated = $request->validate([
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);
        $context = $this->tenantContext($request);

        $resetTenantUserPassword->execute(
            $this->actor($request),
            $context,
            $this->user($context, $user),
            $validated['password'],
            $this->correlationId($request),
        );

        return redirect()
            ->route('operational.users.edit', $user)
            ->with('success', 'Password reset and existing sessions invalidated.');
    }

    private function user(TenantContext $context, int $id): User
    {
        /** @var User $user */
        $user = TenantOwnedRecordQuery::findOrFail($context, User::class, $id);

        return $user;
    }

    /** @param list<int> $ids
     * @return list<Role>
     */
    private function roles(TenantContext $context, array $ids): array
    {
        return array_map(function (int $id) use ($context): Role {
            /** @var Role $role */
            $role = TenantOwnedRecordQuery::findOrFail($context, Role::class, $id);

            return $role;
        }, $ids);
    }

    /** @return list<array{value: int, label: string}> */
    private function roleOptions(TenantContext $context): array
    {
        return TenantOwnedRecordQuery::forTenant($context, Role::class)
            ->where('guard_name', 'web')
            ->orderByRaw('LOWER(name)')
            ->orderBy('id')
            ->get()
            ->map(fn (Role $role): array => [
                'value' => (int) $role->getKey(),
                'label' => (string) $role->name,
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    private function userProps(User $user, bool $refreshRoles = false): array
    {
        if ($refreshRoles) {
            $user->unsetRelation('roles');
        }

        return [
            'id' => (int) $user->getKey(),
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'roles' => $user->roles
                ->pluck('name')
                ->map(static fn (mixed $name): string => (string) $name)
                ->values()
                ->all(),
            'roleIds' => $user->roles
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->values()
                ->all(),
            'isActive' => (bool) $user->is_active,
            'lockVersion' => (int) $user->lock_version,
        ];
    }

    /** @param LengthAwarePaginator<int, User> $users
     * @return array<string, mixed>
     */
    private function paginatedUsers(LengthAwarePaginator $users): array
    {
        return [
            'data' => array_map(fn (User $user): array => $this->userProps($user), $users->items()),
            'currentPage' => $users->currentPage(),
            'lastPage' => $users->lastPage(),
            'perPage' => $users->perPage(),
            'total' => $users->total(),
            'from' => $users->firstItem(),
            'to' => $users->lastItem(),
            'links' => array_map(static fn (array $link): array => [
                'url' => $link['url'],
                'label' => $link['label'],
                'active' => $link['active'],
            ], $users->linkCollection()->all()),
        ];
    }

    /** @return array<string, bool> */
    private function abilities(Request $request): array
    {
        $canManage = $this->authorizeAbility->allows(
            $request,
            $this->actor($request),
            'platform.users.manage',
        );

        return [
            'create' => $canManage,
            'update' => $canManage,
            'deactivate' => $canManage,
            'resetPassword' => $canManage,
        ];
    }
}
