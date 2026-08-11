<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\IdentityAccess\Actions\CreateTenantRole;
use App\Domain\IdentityAccess\Actions\DeleteTenantRole;
use App\Domain\IdentityAccess\Actions\UpdateTenantRole;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AssignableAbilityResource;
use App\Http\Resources\Api\V1\TenantRoleResource;
use App\Support\Authorization\PermissionCatalogue;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

final class TenantRoleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $roles = TenantOwnedRecordQuery::forTenant($this->tenantContext($request), Role::class)
            ->where('guard_name', 'web')
            ->with('permissions:id,name')
            ->orderByRaw('LOWER(name)')
            ->orderBy('id')
            ->paginate(min(max($request->integer('per_page', 15), 1), 100));

        return TenantRoleResource::collection($roles);
    }

    public function show(Request $request, int $role): TenantRoleResource
    {
        $target = TenantOwnedRecordQuery::findOrFail($this->tenantContext($request), Role::class, $role);
        $target->load('permissions:id,name');

        return TenantRoleResource::make($target);
    }

    public function abilities(Request $request): AnonymousResourceCollection
    {
        $items = array_values(array_map(static fn (string $ability): array => [
            'name' => $ability,
            'label' => str($ability)->replace(['-', '.'], ' ')->title()->toString(),
        ], PermissionCatalogue::tenantAbilities()));
        $perPage = min(max($request->integer('per_page', 100), 1), 100);
        $page = max($request->integer('page', 1), 1);
        $paginator = new LengthAwarePaginator(
            array_slice($items, ($page - 1) * $perPage, $perPage),
            count($items),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return AssignableAbilityResource::collection($paginator);
    }

    public function store(Request $request, CreateTenantRole $createTenantRole): TenantRoleResource
    {
        $validated = $this->validated($request);
        try {
            $role = $createTenantRole->execute(
                $this->actor($request),
                $this->tenantContext($request),
                (string) $validated['name'],
                array_values($validated['abilities']),
                $this->correlationId($request),
            );
        } catch (DomainException $exception) {
            $this->rethrowSafeRoleInputError($exception);
            throw $exception;
        }
        $role->load('permissions:id,name');

        return TenantRoleResource::make($role);
    }

    public function update(Request $request, int $role, UpdateTenantRole $updateTenantRole): TenantRoleResource
    {
        $validated = $this->validated($request);
        try {
            $updated = $updateTenantRole->execute(
                $this->actor($request),
                $this->tenantContext($request),
                TenantOwnedRecordQuery::findOrFail($this->tenantContext($request), Role::class, $role),
                (string) $validated['name'],
                array_values($validated['abilities']),
                $this->correlationId($request),
            );
        } catch (DomainException $exception) {
            $this->rethrowSafeRoleInputError($exception);
            throw $exception;
        }
        $updated->load('permissions:id,name');

        return TenantRoleResource::make($updated);
    }

    public function destroy(Request $request, int $role, DeleteTenantRole $deleteTenantRole): Response
    {
        $this->rejectUnexpectedFields($request, []);
        $deleteTenantRole->execute(
            $this->actor($request),
            $this->tenantContext($request),
            TenantOwnedRecordQuery::findOrFail($this->tenantContext($request), Role::class, $role),
            $this->correlationId($request),
        );

        return response()->noContent();
    }

    /** @return array{name: string, abilities: list<string>} */
    private function validated(Request $request): array
    {
        $this->rejectUnexpectedFields($request, ['name', 'abilities']);

        /** @var array{name: string, abilities: list<string>} $validated */
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['present', 'array'],
            'abilities.*' => ['required', 'string', 'distinct'],
        ]);

        return $validated;
    }

    /** @param list<string> $allowed */
    private function rejectUnexpectedFields(Request $request, array $allowed): void
    {
        $unexpected = array_diff(array_keys($request->all()), $allowed);
        if ($unexpected !== []) {
            throw ValidationException::withMessages(array_fill_keys($unexpected, 'This field is not allowed for this operation.'));
        }
    }

    private function rethrowSafeRoleInputError(DomainException $exception): void
    {
        if ($exception->getMessage() === 'PLATFORM_ABILITY_PROTECTED') {
            throw ValidationException::withMessages([
                'abilities' => 'Protected platform abilities cannot be assigned to tenant roles.',
            ]);
        }
    }
}
