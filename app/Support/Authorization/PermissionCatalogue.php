<?php

namespace App\Support\Authorization;

final class PermissionCatalogue
{
    /** @var list<string> */
    private const PROTECTED_ABILITIES = [
        'platform.tenants.view', 'platform.tenants.create', 'platform.tenants.update', 'platform.tenants.deactivate', 'platform.tenants.reactivate',
        'platform.users.manage', 'platform.roles.manage', 'platform.settings.manage', 'deletion-reason-setting.manage', 'platform.audit.view-global',
        'platform.migration.run', 'platform.portability.import', 'platform.backup.run', 'platform.restore.run', 'platform.deploy.view',
    ];

    /** @var list<string> */
    private const TENANT_ABILITIES = [
        'dashboard.view', 'audit.view', 'notification.view',
        'tenant-settings.view', 'tenant-settings.update',
        'planning-year.view', 'planning-year.create', 'planning-year.update', 'planning-year.deactivate', 'planning-year.reactivate',
        'cost-center.view', 'cost-center.create', 'cost-center.update', 'cost-center.delete', 'cost-center.deactivate', 'cost-center.reactivate', 'cost-center.view-revisions', 'cost-center.restore-revision',
        'vendor.view', 'vendor.create', 'vendor.update', 'vendor.delete', 'vendor.deactivate', 'vendor.reactivate', 'vendor.view-revisions', 'vendor.restore-revision',
        'expense.view', 'expense.create', 'expense.update', 'expense.delete', 'expense.view-revisions', 'expense.restore-revision', 'expense.print', 'expense.export',
        'attachment.view', 'attachment.upload', 'attachment.delete',
        'project.view', 'project.create', 'project.update', 'project.delete', 'project.view-revisions', 'project.restore-revision',
        'contract.view', 'contract.create', 'contract.update', 'contract.delete', 'contract.view-revisions', 'contract.restore-revision', 'contract.generate-occurrence', 'contract.suppress-generation', 'contract.resume-generation', 'contract.view-generation-history',
        'budget.view', 'budget.select-reference', 'report.view', 'report.print', 'report.export-filtered', 'report.export-complete',
        'budget-version.view', 'budget-version.create', 'budget-version.update-draft', 'budget-version.publish', 'budget-version.compare', 'budget-version.select-reference',
        'scenario.view', 'scenario.create', 'scenario.update', 'scenario.archive', 'scenario.delete', 'scenario.compare',
        'tenant-portability.export',
    ];

    /** @var list<string> */
    private const EDITOR_ABILITIES = [
        'dashboard.view', 'audit.view', 'notification.view', 'planning-year.view', 'planning-year.update',
        'cost-center.view', 'cost-center.create', 'cost-center.update', 'cost-center.delete', 'cost-center.deactivate', 'cost-center.reactivate', 'cost-center.view-revisions', 'cost-center.restore-revision',
        'vendor.view', 'vendor.create', 'vendor.update', 'vendor.delete', 'vendor.deactivate', 'vendor.reactivate', 'vendor.view-revisions', 'vendor.restore-revision',
        'expense.view', 'expense.create', 'expense.update', 'expense.delete', 'expense.view-revisions', 'expense.restore-revision', 'expense.print', 'expense.export',
        'attachment.view', 'attachment.upload', 'attachment.delete',
        'project.view', 'project.create', 'project.update', 'project.delete', 'project.view-revisions', 'project.restore-revision',
        'contract.view', 'contract.create', 'contract.update', 'contract.delete', 'contract.view-revisions', 'contract.restore-revision', 'contract.generate-occurrence', 'contract.suppress-generation', 'contract.resume-generation', 'contract.view-generation-history',
        'budget.view', 'budget.select-reference', 'report.view', 'report.print', 'report.export-filtered', 'report.export-complete',
        'budget-version.view', 'budget-version.create', 'budget-version.update-draft', 'budget-version.publish', 'budget-version.compare', 'budget-version.select-reference',
        'scenario.view', 'scenario.create', 'scenario.update', 'scenario.archive', 'scenario.delete', 'scenario.compare', 'tenant-portability.export',
    ];

    /** @var list<string> */
    private const VIEWER_ABILITIES = [
        'dashboard.view', 'audit.view', 'notification.view', 'planning-year.view', 'cost-center.view', 'vendor.view', 'expense.view',
        'attachment.view', 'project.view', 'contract.view', 'budget.view', 'report.view', 'report.print', 'report.export-filtered', 'report.export-complete',
        'budget-version.view', 'budget-version.compare', 'scenario.view', 'scenario.compare', 'tenant-portability.export',
    ];

    /** @return list<string> */
    public static function protectedAbilities(): array
    {
        return self::PROTECTED_ABILITIES;
    }

    /** @return list<string> */
    public static function tenantAbilities(): array
    {
        return self::TENANT_ABILITIES;
    }

    /** @return list<string> */
    public static function editorAbilities(): array
    {
        return self::EDITOR_ABILITIES;
    }

    /** @return list<string> */
    public static function viewerAbilities(): array
    {
        return self::VIEWER_ABILITIES;
    }

    /** @return list<string> */
    public static function allAbilities(): array
    {
        return [...self::PROTECTED_ABILITIES, ...self::TENANT_ABILITIES];
    }

    public static function isProtected(string $ability): bool
    {
        return in_array($ability, self::PROTECTED_ABILITIES, true);
    }

    public static function isTenant(string $ability): bool
    {
        return in_array($ability, self::TENANT_ABILITIES, true);
    }

    /** @return list<string> */
    public static function authorizationCandidates(string $ability): array
    {
        return match ($ability) {
            'tenant-settings.view' => ['tenant-settings.view', 'tenant-settings.update'],
            default => [$ability],
        };
    }
}
