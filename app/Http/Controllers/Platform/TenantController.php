<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Tenancy\Actions\CreateTenant;
use App\Domain\Tenancy\Actions\DeactivateTenant;
use App\Domain\Tenancy\Actions\EnterTenantContext;
use App\Domain\Tenancy\Actions\LeaveTenantContext;
use App\Domain\Tenancy\Actions\ReactivateTenant;
use App\Domain\Tenancy\Actions\UpdateTenant;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthorizeApplicationAbility;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TenantController extends Controller
{
    public function __construct(private readonly AuthorizeApplicationAbility $authorizeAbility) {}

    public function index(Request $request): Response
    {
        $tenants = Tenant::query()
            ->orderByRaw('LOWER(name)')
            ->orderBy('id')
            ->get()
            ->map(fn (Tenant $tenant): array => $this->tenantProps($tenant))
            ->all();

        return Inertia::render('Platform/Tenants/Index', [
            'tenants' => $tenants,
            'abilities' => $this->abilities($request),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Platform/Tenants/Create');
    }

    public function store(Request $request, CreateTenant $createTenant): RedirectResponse
    {
        $input = $request->validate($this->tenantRules());

        $tenant = $createTenant->execute(
            $this->actor($request),
            $input,
            $this->correlationId($request),
        );

        return redirect()
            ->route('platform.tenants.edit', $tenant)
            ->with('success', 'Tenant created.');
    }

    public function edit(Tenant $tenant): Response
    {
        return Inertia::render('Platform/Tenants/Edit', [
            'record' => $this->tenantProps($tenant),
        ]);
    }

    public function update(Request $request, Tenant $tenant, UpdateTenant $updateTenant): RedirectResponse
    {
        $validated = $request->validate([
            ...$this->tenantRules(),
            'lock_version' => ['required', 'integer', 'min:1'],
        ]);
        $expectedLockVersion = (int) $validated['lock_version'];
        unset($validated['lock_version']);

        $updateTenant->execute(
            $this->actor($request),
            $tenant,
            $validated,
            $expectedLockVersion,
            $this->correlationId($request),
        );

        return redirect()
            ->route('platform.tenants.edit', $tenant)
            ->with('success', 'Tenant updated.');
    }

    public function deactivate(
        Request $request,
        Tenant $tenant,
        DeactivateTenant $deactivateTenant,
    ): RedirectResponse {
        $validated = $request->validate([
            'confirmation_code' => ['required', 'string', 'max:255'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ]);

        $deactivateTenant->execute(
            $this->actor($request),
            $tenant,
            (int) $validated['lock_version'],
            $validated['confirmation_code'],
            $this->correlationId($request),
        );

        return redirect()
            ->route('platform.tenants.index')
            ->with('success', 'Tenant deactivated.');
    }

    public function reactivate(
        Request $request,
        Tenant $tenant,
        ReactivateTenant $reactivateTenant,
    ): RedirectResponse {
        $validated = $request->validate([
            'lock_version' => ['required', 'integer', 'min:1'],
        ]);

        $reactivateTenant->execute(
            $this->actor($request),
            $tenant,
            (int) $validated['lock_version'],
            $this->correlationId($request),
        );

        return redirect()
            ->route('platform.tenants.index')
            ->with('success', 'Tenant reactivated.');
    }

    public function enter(
        Request $request,
        Tenant $tenant,
        EnterTenantContext $enterTenantContext,
    ): RedirectResponse {
        $enterTenantContext->execute(
            $this->actor($request),
            $tenant,
            $request->session(),
            $this->correlationId($request),
        );

        return redirect()->route('home');
    }

    public function leave(Request $request, LeaveTenantContext $leaveTenantContext): RedirectResponse
    {
        $leaveTenantContext->execute(
            $this->actor($request),
            $request->session(),
            $this->correlationId($request),
        );

        return redirect()
            ->route('platform.tenants.index')
            ->with('success', 'Tenant context cleared.');
    }

    /** @return array<string, list<string>> */
    private function tenantRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255'],
            'currency_code' => ['required', 'string', 'size:3'],
            'language_code' => ['required', 'string', 'max:10'],
            'timezone' => ['required', 'string', 'timezone'],
            'default_vat_rate' => ['required', 'string', 'max:13'],
        ];
    }

    /** @return array<string, int|string> */
    private function tenantProps(Tenant $tenant): array
    {
        return [
            'id' => (int) $tenant->getKey(),
            'name' => (string) $tenant->name,
            'code' => (string) $tenant->code,
            'currency' => (string) $tenant->currency_code,
            'language' => (string) $tenant->language_code,
            'timezone' => (string) $tenant->timezone,
            'defaultVatRate' => (string) $tenant->default_vat_rate,
            'state' => (string) $tenant->getRawOriginal('state'),
            'lockVersion' => (int) $tenant->lock_version,
        ];
    }

    /** @return array<string, bool> */
    private function abilities(Request $request): array
    {
        $actor = $this->actor($request);

        return [
            'create' => $this->authorizeAbility->allows($request, $actor, 'platform.tenants.create'),
            'update' => $this->authorizeAbility->allows($request, $actor, 'platform.tenants.update'),
            'deactivate' => $this->authorizeAbility->allows($request, $actor, 'platform.tenants.deactivate'),
            'reactivate' => $this->authorizeAbility->allows($request, $actor, 'platform.tenants.reactivate'),
            'enter' => $this->authorizeAbility->allows($request, $actor, 'platform.tenants.view'),
        ];
    }
}
