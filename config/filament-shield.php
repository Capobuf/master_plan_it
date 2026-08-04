<?php

use App\Models\Tenant;
use App\Models\User;
use Filament\Pages\Dashboard;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;

$tenantAbilities = [
    'dashboard.view', 'audit.view', 'notification.view',
    'planning-year.view', 'planning-year.create', 'planning-year.deactivate', 'planning-year.reactivate',
    'cost-center.view', 'cost-center.create', 'cost-center.update', 'cost-center.delete', 'cost-center.deactivate', 'cost-center.reactivate', 'cost-center.view-revisions', 'cost-center.restore-revision',
    'vendor.view', 'vendor.create', 'vendor.update', 'vendor.delete', 'vendor.deactivate', 'vendor.reactivate', 'vendor.view-revisions', 'vendor.restore-revision',
    'expense.view', 'expense.create', 'expense.update', 'expense.delete', 'expense.view-revisions', 'expense.restore-revision', 'expense.confirm-actual', 'expense.print', 'expense.export',
    'attachment.view', 'attachment.upload', 'attachment.delete',
    'project.view', 'project.create', 'project.update', 'project.delete', 'project.view-revisions', 'project.restore-revision',
    'contract.view', 'contract.create', 'contract.update', 'contract.delete', 'contract.view-revisions', 'contract.restore-revision', 'contract.generate-occurrence', 'contract.suppress-generation', 'contract.resume-generation', 'contract.view-generation-history',
    'budget.view', 'budget.select-reference', 'report.view', 'report.print', 'report.export-filtered', 'report.export-complete',
    'budget-version.view', 'budget-version.create', 'budget-version.update-draft', 'budget-version.publish', 'budget-version.compare', 'budget-version.select-reference',
    'scenario.view', 'scenario.create', 'scenario.update', 'scenario.archive', 'scenario.delete', 'scenario.compare',
    'tenant-portability.export',
];

return [
    'shield_resource' => [
        'slug' => 'shield/roles',
        'show_model_path' => false,
        'cluster' => null,
        'tabs' => [
            'pages' => false,
            'widgets' => false,
            'resources' => false,
            'custom_permissions' => true,
        ],
    ],
    'tenant_model' => Tenant::class,
    'auth_provider_model' => User::class,
    'super_admin' => [
        'enabled' => false,
        'name' => 'Administrator',
        'define_via_gate' => false,
        'intercept_gate' => 'before',
    ],
    'panel_user' => [
        'enabled' => false,
        'name' => 'panel_user',
    ],
    'permissions' => [
        'separator' => '.',
        'case' => 'lower_snake',
        'generate' => false,
        'format_custom_permission_keys' => false,
    ],
    'policies' => [
        'path' => app_path('Policies'),
        'merge' => true,
        'generate' => false,
        'methods' => [
            'viewAny', 'view', 'create', 'update', 'delete', 'deleteAny', 'restore',
            'forceDelete', 'forceDeleteAny', 'restoreAny', 'replicate', 'reorder',
        ],
        'single_parameter_methods' => [
            'viewAny', 'create', 'deleteAny', 'forceDeleteAny', 'restoreAny', 'reorder',
        ],
    ],
    'localization' => [
        'enabled' => false,
        'key' => 'filament-shield::filament-shield.resource_permission_prefixes_labels',
    ],
    'resources' => [
        'subject' => 'model',
        'manage' => [],
        'exclude' => [],
    ],
    'pages' => [
        'subject' => 'class',
        'prefix' => 'view',
        'exclude' => [Dashboard::class],
    ],
    'widgets' => [
        'subject' => 'class',
        'prefix' => 'view',
        'exclude' => [AccountWidget::class, FilamentInfoWidget::class],
    ],
    'custom_permissions' => array_combine($tenantAbilities, $tenantAbilities),
    'discovery' => [
        'discover_all_resources' => false,
        'discover_all_widgets' => false,
        'discover_all_pages' => false,
    ],
    'register_role_policy' => false,
];
