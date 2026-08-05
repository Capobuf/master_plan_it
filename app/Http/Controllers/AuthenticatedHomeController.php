<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\Data\TenantContext;
use App\Http\Middleware\AuthorizeApplicationAbility;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AuthenticatedHomeController
{
    /** @var array<string, string> */
    private const OPERATIONAL_DESTINATIONS = [
        'dashboard.view' => '/operational',
        'planning-year.view' => '/operational/planning-years',
        'cost-center.view' => '/operational/cost-centers',
        'vendor.view' => '/operational/vendors',
        'expense.view' => '/operational/expenses',
    ];

    public function __construct(
        private readonly PlatformAdministrator $platformAdministrator,
        private readonly AuthorizeApplicationAbility $authorizeAbility,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        $context = $request->attributes->get(TenantContext::class);

        if ($this->platformAdministrator->hasProtectedRole($actor) && ! $context instanceof TenantContext) {
            return redirect('/platform/tenants');
        }

        if (! $context instanceof TenantContext) {
            throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
        }

        foreach (self::OPERATIONAL_DESTINATIONS as $ability => $destination) {
            if ($this->authorizeAbility->allows($request, $actor, $ability)) {
                return redirect($destination);
            }
        }

        throw new AuthorizationException('PERMISSION_DENIED');
    }
}
