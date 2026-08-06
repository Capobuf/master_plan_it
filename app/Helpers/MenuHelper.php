<?php

namespace App\Helpers;

use App\Http\Middleware\AuthorizeApplicationAbility;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;

final class MenuHelper
{
    /** @return list<array{title:string,items:list<array{name:string,path:string,icon:string,visible:bool}>}> */
    public static function groups(): array
    {
        return [
            ['title' => 'Overview', 'items' => [self::item('Dashboard', 'operational.index', 'dashboard', 'dashboard.view')]],
            ['title' => 'Planning', 'items' => [
                self::item('Budget', 'operational.budget.index', 'wallet', 'budget.view'),
                self::item('Reports', 'operational.reports.index', 'chart', 'report.view'),
                self::item('Planning years', 'operational.planning-years.index', 'calendar', 'planning-year.view'),
            ]],
            ['title' => 'Operations', 'items' => [
                self::item('Expenses', 'operational.expenses.index', 'receipt', 'expense.view'),
                self::item('Contracts', 'operational.contracts.index', 'document', 'contract.view'),
                self::item('Vendors', 'operational.vendors.index', 'building', 'vendor.view'),
                self::item('Cost centers', 'operational.cost-centers.index', 'layers', 'cost-center.view'),
            ]],
            ['title' => 'Access', 'items' => [
                self::item('Users', 'operational.users.index', 'users', 'platform.users.manage'),
                self::item('Roles', 'operational.roles.index', 'shield', 'platform.roles.manage'),
            ]],
            ['title' => 'Platform', 'items' => [self::item('Tenants', 'platform.tenants.index', 'building', 'platform.tenants.view')]],
        ];
    }

    /** @return array{name:string,path:string,icon:string,visible:bool} */
    private static function item(string $name, string $route, string $icon, string $ability): array
    {
        return ['name' => $name, 'path' => route($route), 'icon' => $icon, 'visible' => self::allows($ability)];
    }

    private static function allows(string $ability): bool
    {
        $user = request()->user();
        if (! $user instanceof User) return false;
        if (str_starts_with($ability, 'platform.') && app(PlatformAdministrator::class)->allows($user, $ability)) return true;
        return app(AuthorizeApplicationAbility::class)->allows(request(), $user, $ability);
    }
}
