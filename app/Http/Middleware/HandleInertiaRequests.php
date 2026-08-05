<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\Data\TenantContext;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use App\Support\Diagnostics\CorrelationId;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(
        private readonly PlatformAdministrator $platformAdministrator,
        private readonly AuthorizeApplicationAbility $authorizeAbility,
    ) {}

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn (): ?array => $this->authenticatedUser($request),
            ],
            'tenant' => [
                'current' => fn (): ?array => $this->currentTenant($request),
            ],
            'navigation' => fn (): array => $this->navigation($request),
            'flash' => [
                'success' => fn (): ?string => $request->session()->get('success'),
                'error' => fn (): ?string => $request->session()->get('error'),
            ],
            'diagnostics' => [
                'correlationId' => fn (): string => CorrelationId::resolveFor($request)->value(),
            ],
        ];
    }

    /** @return array{id: int, name: string, email: string, isPlatformAdministrator: bool}|null */
    private function authenticatedUser(Request $request): ?array
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            return null;
        }

        return [
            'id' => (int) $actor->getKey(),
            'name' => (string) $actor->name,
            'email' => (string) $actor->email,
            'isPlatformAdministrator' => $this->platformAdministrator->hasProtectedRole($actor),
        ];
    }

    /** @return array{id: int, name: string, code: string, currency: string, language: string, timezone: string}|null */
    private function currentTenant(Request $request): ?array
    {
        $context = $request->attributes->get(TenantContext::class);

        if (! $context instanceof TenantContext) {
            return null;
        }

        return [
            'id' => $context->tenantId,
            'name' => (string) $context->tenant->name,
            'code' => (string) $context->tenant->code,
            'currency' => $context->currencyCode,
            'language' => $context->languageCode,
            'timezone' => $context->timezone,
        ];
    }

    /**
     * @return array{
     *     canViewPlatformTenants: bool,
     *     canViewDashboard: bool,
     *     canManageUsers: bool,
     *     canManageRoles: bool,
     *     canViewPlanningYears: bool,
     *     canViewCostCenters: bool,
     *     canViewVendors: bool,
     *     canViewExpenses: bool
     * }
     */
    private function navigation(Request $request): array
    {
        $actor = $request->user();
        $context = $request->attributes->get(TenantContext::class);

        if (! $actor instanceof User) {
            return $this->emptyNavigation();
        }

        $hasTenantContext = $context instanceof TenantContext;

        return [
            'canViewPlatformTenants' => $this->platformAdministrator->allows($actor, 'platform.tenants.view'),
            'canViewDashboard' => $hasTenantContext
                && $this->authorizeAbility->allows($request, $actor, 'dashboard.view'),
            'canManageUsers' => $hasTenantContext
                && $this->platformAdministrator->allows($actor, 'platform.users.manage'),
            'canManageRoles' => $hasTenantContext
                && $this->platformAdministrator->allows($actor, 'platform.roles.manage'),
            'canViewPlanningYears' => $hasTenantContext
                && $this->authorizeAbility->allows($request, $actor, 'planning-year.view'),
            'canViewCostCenters' => $hasTenantContext
                && $this->authorizeAbility->allows($request, $actor, 'cost-center.view'),
            'canViewVendors' => $hasTenantContext
                && $this->authorizeAbility->allows($request, $actor, 'vendor.view'),
            'canViewExpenses' => $hasTenantContext
                && $this->authorizeAbility->allows($request, $actor, 'expense.view'),
        ];
    }

    /**
     * @return array{
     *     canViewPlatformTenants: false,
     *     canViewDashboard: false,
     *     canManageUsers: false,
     *     canManageRoles: false,
     *     canViewPlanningYears: false,
     *     canViewCostCenters: false,
     *     canViewVendors: false,
     *     canViewExpenses: false
     * }
     */
    private function emptyNavigation(): array
    {
        return [
            'canViewPlatformTenants' => false,
            'canViewDashboard' => false,
            'canManageUsers' => false,
            'canManageRoles' => false,
            'canViewPlanningYears' => false,
            'canViewCostCenters' => false,
            'canViewVendors' => false,
            'canViewExpenses' => false,
        ];
    }
}
