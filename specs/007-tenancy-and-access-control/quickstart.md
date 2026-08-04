# Verification quickstart — Feature 007 Tenancy and access control

Future commands; none were executed during planning.

## Fixture

Create global Administrator, tenants A/B, active/inactive states, custom roles, Editor/Viewer templates and tenant users. Keep tenant A at the 2 GiB attachment-quota default, lower tenant B below its current-plus-historical attachment usage, and verify `deletion-reason-setting.manage` is protected from every tenant role. As Administrator, toggle it only for explicitly selected tenant A. Create one resource of every tenant-owned family plus attachments/revisions/report/version/scenario/import identities.

## Focused tests

```bash
./vendor/bin/sail artisan test --filter=TenantContext
./vendor/bin/sail artisan test --filter=TenantLifecycle
./vendor/bin/sail artisan test --filter=TenantRole
./vendor/bin/sail artisan test --filter=CrossTenantIsolation
```

## Acceptance path

1. Administrator enters tenant A; identity remains Administrator and tenant is visible.
2. Switch/leave context and verify Spatie team ID and loaded permissions reset.
3. Tenant user attempts tenant override and is denied.
4. Deactivate tenant A: tenant users blocked, Administrator can enter/reactivate.
5. Modify a custom role and verify authorization changes without domain-code changes.
6. Attempt to assign protected platform ability and receive `PLATFORM_ABILITY_PROTECTED`.
7. Use tenant B IDs in direct URLs, relations, revisions, files, reports, exports and commands; receive safe denial.
8. Deactivate user and verify authorship remains.
9. Open global dashboard and confirm no economic aggregation.
10. As Administrator with `platform.settings.manage`, set only tenant B's attachment quota to zero; verify tenant roles cannot do so, no payload is deleted, new payload bytes are denied and reuse-only revisions remain allowed.
11. As global Administrator, toggle tenant A's deletion-reason requirement with the dedicated protected ability; verify tenant B is unchanged, tenant-role assignment/access is denied, and prior deletion evidence is unchanged.

Focused browser:

```bash
./vendor/bin/sail artisan dusk --filter=TenantContextBrowserTest
./vendor/bin/sail artisan dusk --filter=RoleManagementBrowserTest
```

## Cleanup

Transactions or run-ID targeted cleanup only. Reset permission cache/context explicitly between tests; never reset the persistent test DB.

## Success

No request, Livewire action, command or scheduled operation leaks tenant context/permissions; protected abilities and invariants remain non-assignable. Tenant operational settings have the approved defaults, authority split and prospective-only effects.
