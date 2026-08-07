<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tenancy\Actions\CreateTenant;
use App\Domain\Tenancy\Actions\DeactivateTenant;
use App\Domain\Tenancy\Actions\EnterTenantContext;
use App\Domain\Tenancy\Actions\LeaveTenantContext;
use App\Domain\Tenancy\Actions\ReactivateTenant;
use App\Domain\Tenancy\Actions\UpdateTenant;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthorizeApplicationAbility;
use App\Http\Resources\Api\V1\TenantResource;
use App\Models\Tenant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

final class TenantController extends Controller
{
    public function __construct(private readonly AuthorizeApplicationAbility $authorizeAbility) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeAbility($request, 'platform.tenants.view');
        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        return TenantResource::collection(
            Tenant::query()
                ->orderByRaw('LOWER(name)')
                ->orderBy('id')
                ->paginate($perPage)
        );
    }

    public function show(Request $request, Tenant $tenant): TenantResource
    {
        $this->authorizeAbility($request, 'platform.tenants.view');

        return TenantResource::make($tenant);
    }

    public function store(Request $request, CreateTenant $createTenant): TenantResource
    {
        $this->authorizeAbility($request, 'platform.tenants.create');
        $this->rejectUnexpectedFields($request, array_keys($this->tenantRules()));
        $tenant = $createTenant->execute($this->actor($request), $this->tenantInput($request), $this->correlationId($request));

        return TenantResource::make($tenant);
    }

    public function update(Request $request, Tenant $tenant, UpdateTenant $updateTenant): TenantResource
    {
        $this->authorizeAbility($request, 'platform.tenants.update');
        $this->rejectUnexpectedFields($request, [...array_keys($this->tenantRules(true)), 'lock_version']);
        $validated = $request->validate([
            ...$this->tenantRules(true),
            'lock_version' => ['required', 'integer', 'min:1'],
        ]);
        $expectedLockVersion = (int) $validated['lock_version'];
        unset($validated['lock_version']);

        $updated = $updateTenant->execute($this->actor($request), $tenant, $validated, $expectedLockVersion, $this->correlationId($request));

        return TenantResource::make($updated);
    }

    public function deactivate(Request $request, Tenant $tenant, DeactivateTenant $deactivateTenant): TenantResource
    {
        $this->authorizeAbility($request, 'platform.tenants.deactivate');
        $validated = $request->validate([
            'confirmation_code' => ['required', 'string', 'max:255'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ]);

        $updated = $deactivateTenant->execute(
            $this->actor($request),
            $tenant,
            (int) $validated['lock_version'],
            (string) $validated['confirmation_code'],
            $this->correlationId($request),
        );

        return TenantResource::make($updated);
    }

    public function reactivate(Request $request, Tenant $tenant, ReactivateTenant $reactivateTenant): TenantResource
    {
        $this->authorizeAbility($request, 'platform.tenants.reactivate');
        $validated = $request->validate(['lock_version' => ['required', 'integer', 'min:1']]);

        $updated = $reactivateTenant->execute(
            $this->actor($request),
            $tenant,
            (int) $validated['lock_version'],
            $this->correlationId($request),
        );

        return TenantResource::make($updated);
    }

    public function enter(Request $request, Tenant $tenant, EnterTenantContext $enterTenantContext): TenantResource
    {
        $this->authorizeAbility($request, 'platform.tenants.view');
        $context = $enterTenantContext->execute($this->actor($request), $tenant, $request->session(), $this->correlationId($request));

        return TenantResource::make($context->tenant);
    }

    public function leave(Request $request, LeaveTenantContext $leaveTenantContext): Response
    {
        $this->authorizeAbility($request, 'platform.tenants.view');
        $leaveTenantContext->execute($this->actor($request), $request->session(), $this->correlationId($request));

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function tenantInput(Request $request): array
    {
        return $request->validate($this->tenantRules());
    }

    /** @return array<string, list<string>> */
    private function tenantRules(bool $partial = false): array
    {
        $presence = $partial ? 'sometimes' : 'required';

        return [
            'name' => [$presence, 'string', 'max:255'],
            'code' => [$presence, 'string', 'max:255'],
            'currency_code' => [$presence, 'string', 'regex:/^[A-Za-z]{3}$/D'],
            'language_code' => [$presence, 'string', 'regex:/^[A-Za-z]{1,10}$/D'],
            'timezone' => [$presence, 'string', 'timezone'],
            'default_vat_rate' => [$presence, 'string', 'regex:/^[0-9]{1,6}(?:\.[0-9]{1,6})?$/D'],
        ];
    }

    private function authorizeAbility(Request $request, string $ability): void
    {
        if (! $this->authorizeAbility->allows($request, $this->actor($request), $ability)) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }
    }

    /** @param list<string> $allowed */
    private function rejectUnexpectedFields(Request $request, array $allowed): void
    {
        $unexpected = array_diff(array_keys($request->all()), $allowed);

        if ($unexpected !== []) {
            throw ValidationException::withMessages(
                array_fill_keys($unexpected, 'This field is not allowed for this operation.'),
            );
        }
    }
}
