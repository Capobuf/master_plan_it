# Permission catalogue

Status: `PROPOSED TARGET — STABLE IDENTIFIERS`  
Backend: Spatie Permission teams (`tenant_id`) + Laravel Policies/Gates + Filament Shield UI

## Rules

- Permission identifiers are global, stable and code-owned.
- Tenant roles group permissions within one tenant.
- Permissions are additive; absence denies.
- Role names never drive business behavior, except the protected global Administrator boundary.
- Tenant role UI cannot create arbitrary permission strings.
- Domain invariants and tenant ownership remain mandatory after permission allow.
- Direct user permissions are not exposed at launch.

## Protected platform abilities

Not assignable to tenant roles:

```text
platform.tenants.view
platform.tenants.create
platform.tenants.update
platform.tenants.deactivate
platform.tenants.reactivate
platform.users.manage
platform.roles.manage
platform.settings.manage
deletion-reason-setting.manage
platform.audit.view-global
platform.migration.run
platform.portability.import
platform.backup.run
platform.restore.run
platform.deploy.view
```

## Tenant abilities

### Dashboard and audit

```text
dashboard.view
audit.view
notification.view
```

### Planning years

```text
planning-year.view
planning-year.create
planning-year.deactivate
planning-year.reactivate
```

### Cost centers

```text
cost-center.view
cost-center.create
cost-center.update
cost-center.delete
cost-center.deactivate
cost-center.reactivate
cost-center.view-revisions
cost-center.restore-revision
```

### Vendors

```text
vendor.view
vendor.create
vendor.update
vendor.delete
vendor.deactivate
vendor.reactivate
vendor.view-revisions
vendor.restore-revision
```

### Expenses

```text
expense.view
expense.create
expense.update
expense.delete
expense.view-revisions
expense.restore-revision
expense.confirm-actual
expense.print
expense.export
```

`expense.update` does not imply `expense.confirm-actual`, delete or restore.

### Attachments

```text
attachment.view
attachment.upload
attachment.delete
```

Parent-resource permission is also required.

Attachment-quota management is not a tenant ability. It uses protected `platform.settings.manage` and global Administrator validation.

Deletion-reason configuration uses the separate protected `deletion-reason-setting.manage` ability. It is held only by the global Administrator, applies to one explicitly selected tenant at a time and grants no project, contract or term delete ability.

### Projects

```text
project.view
project.create
project.update
project.delete
project.view-revisions
project.restore-revision
```

### Contracts and generation

```text
contract.view
contract.create
contract.update
contract.delete
contract.view-revisions
contract.restore-revision
contract.generate-occurrence
contract.suppress-generation
contract.resume-generation
contract.view-generation-history
```

### Current Budget and reporting

```text
budget.view
budget.select-reference
report.view
report.print
report.export-filtered
report.export-complete
```

### Budget versions

```text
budget-version.view
budget-version.create
budget-version.update-draft
budget-version.publish
budget-version.compare
budget-version.select-reference
```

Published version mutation/deletion is not a permission because it is prohibited.

### Scenarios

```text
scenario.view
scenario.create
scenario.update
scenario.archive
scenario.delete
scenario.compare
```

### Tenant portability export

```text
tenant-portability.export
```

Assignability is controlled by Administrator; import remains protected platform operation.

## Seeded role templates

### Administrator

The protected tenantless global role receives the complete stable catalogue. Protected operations still require the global boundary; tenant operations still require an explicit selected tenant plus their normal Policy, ownership and domain-invariant checks. This is not a `Gate::before` or invariant bypass.

### Editor

Receives ordinary tenant business permissions: planning-year view but not create/deactivate/reactivate; full vendor and cost-center lifecycle; Expense/attachment/project/contract work; generation controls; reports/export; BudgetVersion/scenario management; and tenant audit view. Protected platform and deletion-reason-setting abilities are excluded. Planning-year lifecycle abilities remain tenant-scoped catalogue entries that Administrator may assign deliberately to a custom role; they are not part of the seeded Editor template.

### Viewer

Receives view, attachment download, audit view, report/print/export and BudgetVersion/scenario read/compare. No create/update/delete/confirm/publish/generation controls.

Seeders are idempotent and update catalogue membership deliberately; they never overwrite Administrator-customized tenant roles after tenant creation.

## Policy mapping

Each Policy method maps to one identifier and repeats tenant/current-state checks. Non-resource Actions use explicit Gate names matching catalogue entries.

Shield generation must be configured to preserve these identifiers. Any generated CRUD name not listed here is rejected or mapped explicitly before deployment.

## Tests

For every ability:

1. granted same-tenant allow;
2. missing permission deny;
3. other-tenant deny without existence leak;
4. inactive tenant/deactivated user deny;
5. protected ability cannot be assigned through tenant RoleResource;
6. permission cannot bypass an invariant;
7. changing role assignment changes access without changing domain code.
