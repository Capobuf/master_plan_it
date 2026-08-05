<?php

namespace App\Http\Controllers\Operational;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthorizeApplicationAbility;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    /** @var array<string, array{label: string, href: string}> */
    private const MODULES = [
        'planning-year.view' => ['label' => 'Planning years', 'href' => '/operational/planning-years'],
        'cost-center.view' => ['label' => 'Cost centers', 'href' => '/operational/cost-centers'],
        'vendor.view' => ['label' => 'Vendors', 'href' => '/operational/vendors'],
        'expense.view' => ['label' => 'Expenses', 'href' => '/operational/expenses'],
    ];

    public function __construct(
        private readonly AuthorizeApplicationAbility $authorizeAbility,
        private readonly PlatformAdministrator $platformAdministrator,
    ) {}

    public function __invoke(Request $request): Response
    {
        $actor = $this->actor($request);
        $modules = [];

        if ($this->platformAdministrator->allows($actor, 'platform.users.manage')) {
            $modules[] = ['label' => 'Users', 'href' => '/operational/users'];
        }

        if ($this->platformAdministrator->allows($actor, 'platform.roles.manage')) {
            $modules[] = ['label' => 'Roles', 'href' => '/operational/roles'];
        }

        foreach (self::MODULES as $ability => $module) {
            if ($this->authorizeAbility->allows($request, $actor, $ability)) {
                $modules[] = $module;
            }
        }

        return Inertia::render('Operational/Dashboard', ['modules' => $modules]);
    }
}
