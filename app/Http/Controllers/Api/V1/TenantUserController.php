<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\IdentityAccess\Actions\AssignTenantRoles;
use App\Domain\IdentityAccess\Actions\CreateTenantUser;
use App\Domain\IdentityAccess\Actions\DeactivateTenantUser;
use App\Domain\IdentityAccess\Actions\ResetTenantUserPassword;
use App\Domain\IdentityAccess\Actions\UpdateTenantUser;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TenantUserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

final class TenantUserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $context = $this->tenantContext($request);
        $search = trim((string) $request->query('q', ''));
        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        $users = TenantOwnedRecordQuery::forTenant($context, User::class)
            ->with('roles:id,name')
            ->when($search !== '', static function ($query) use ($search): void {
                $query->where(static function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByRaw('LOWER(name)')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return TenantUserResource::collection($users);
    }

    public function show(Request $request, int $user): TenantUserResource
    {
        $target = $this->user($request, $user);
        $target->load('roles:id,name');

        return TenantUserResource::make($target);
    }

    public function store(Request $request, CreateTenantUser $createTenantUser): TenantUserResource
    {
        $validated = $this->validated($request, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', Password::defaults()],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'integer', 'distinct'],
        ]);
        $context = $this->tenantContext($request);
        $user = $createTenantUser->execute(
            $this->actor($request),
            $context,
            (string) $validated['name'],
            (string) $validated['email'],
            (string) $validated['password'],
            $this->roles($context, $validated['roles']),
            $this->correlationId($request),
        );
        $user->load('roles:id,name');

        return TenantUserResource::make($user);
    }

    public function update(Request $request, int $user, UpdateTenantUser $updateTenantUser): TenantUserResource
    {
        $validated = $this->validated($request, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);
        $context = $this->tenantContext($request);
        $updated = $updateTenantUser->execute(
            $this->actor($request),
            $context,
            $this->user($request, $user),
            (string) $validated['name'],
            (string) $validated['email'],
            $this->correlationId($request),
        );
        $updated->load('roles:id,name');

        return TenantUserResource::make($updated);
    }

    public function updateRoles(Request $request, int $user, AssignTenantRoles $assignTenantRoles): TenantUserResource
    {
        $validated = $this->validated($request, [
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'integer', 'distinct'],
        ]);
        $context = $this->tenantContext($request);
        $updated = $assignTenantRoles->execute(
            $this->actor($request),
            $context,
            $this->user($request, $user),
            $this->roles($context, $validated['roles']),
            $this->correlationId($request),
        );
        $updated->load('roles:id,name');

        return TenantUserResource::make($updated);
    }

    public function deactivate(Request $request, int $user, DeactivateTenantUser $deactivateTenantUser): TenantUserResource
    {
        $this->rejectUnexpectedFields($request, []);
        $context = $this->tenantContext($request);
        $target = $this->user($request, $user);
        $deactivateTenantUser->execute(
            $this->actor($request),
            $context,
            $target,
            [],
            $this->correlationId($request),
        );
        $target->refresh()->load('roles:id,name');

        return TenantUserResource::make($target);
    }

    public function resetPassword(Request $request, int $user, ResetTenantUserPassword $resetTenantUserPassword): Response
    {
        $validated = $this->validated($request, [
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);
        $resetTenantUserPassword->execute(
            $this->actor($request),
            $this->tenantContext($request),
            $this->user($request, $user),
            (string) $validated['password'],
            $this->correlationId($request),
        );

        return response()->noContent();
    }

    private function user(Request $request, int $id): User
    {
        /** @var User $user */
        $user = TenantOwnedRecordQuery::findOrFail($this->tenantContext($request), User::class, $id);

        return $user;
    }

    /** @param list<int> $ids
     * @return list<Role>
     */
    private function roles(\App\Domain\Tenancy\Data\TenantContext $context, array $ids): array
    {
        return array_map(static fn (int $id): Role => TenantOwnedRecordQuery::findOrFail($context, Role::class, $id), $ids);
    }

    /** @param array<string, array<int, string|\Illuminate\Contracts\Validation\ValidationRule>> $rules
     * @return array<string, mixed>
     */
    private function validated(Request $request, array $rules): array
    {
        $this->rejectUnexpectedFields($request, array_keys($rules));

        return $request->validate($rules);
    }

    /** @param list<string> $allowed */
    private function rejectUnexpectedFields(Request $request, array $allowed): void
    {
        $unexpected = array_diff(array_keys($request->all()), $allowed);
        if ($unexpected !== []) {
            throw ValidationException::withMessages(array_fill_keys($unexpected, 'This field is not allowed for this operation.'));
        }
    }
}
