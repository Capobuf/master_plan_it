# Contract — Authorization

Status: `CLARIFIED — PLAN REQUIRED`  
Authority: Constitution C-07, Feature 007, and `docs/replatform/versioning-permissions-and-operations-contract.md`

`Allow` always means server-side authorization in a valid scope plus successful domain-invariant validation. Navigation visibility never grants access.

## Protected platform boundary

| Operation | Administrator | Tenant role configuration |
|---|---:|---:|
| Create/deactivate/reactivate tenant | Allow | Not assignable |
| Manage tenant users and tenant roles | Allow | Not assignable |
| Switch tenant context | Allow | Not assignable |
| View global operational overview | Allow | Not assignable |
| Run installation backup/restore | Allow | Not assignable |
| Run legacy migration or tenant package import | Allow | Not assignable |
| Manage platform configuration | Allow | Not assignable |
| Emergency global Administrator password reset | Artisan-only | Not assignable |
| Bypass tenant/economic/versioning invariants | Deny | Not representable |

The protected Administrator role cannot be edited, renamed, tenant-scoped, or deleted.

## Tenant permission catalogue

Exact identifiers are finalized in `/speckit.plan`; every permission maps to one or more policy abilities and Actions.

| Permission family | Example abilities | Seeded Editor | Seeded Viewer |
|---|---|---:|---:|
| Expense read | list/view/current totals/revision list | Allow | Allow |
| Expense write | create/update/version/restore/delete | Allow | Deny |
| Expense output | print/export | Allow | Allow |
| Attachment read | view/download | Allow | Allow |
| Attachment write | upload/replace/delete where parent permits | Allow | Deny |
| Vendor | view/manage/reactivate | Allow | View |
| Cost center | view/manage/reactivate | Allow | View |
| Financial year | view/manage | View | View |
| Project | view/manage/version/restore | Allow | View |
| Contract | view/manage/version/restore | Allow | View |
| Contract generation | generate missing year/suppress/resume | Allow | Deny |
| Reports | dashboard/report/print/export | Allow | Allow |
| Scenario | view/manage | Allow | View |
| Budget version | view/create/compare | Allow | View |
| Audit | same-tenant view | Allow | Allow |
| Notifications | receive selected event families | Initial defaults defined in plan | Initial defaults defined in plan |

Administrator may alter seeded tenant templates and create additional roles. Permissions are additive across assigned tenant roles. Absence denies.

## Resource rules

- Every tenant-owned resource includes or derives one `tenant_id`.
- A tenant user belongs to exactly one tenant.
- Role and direct permission assignments are evaluated only inside the current tenant permission scope.
- Cross-tenant identifiers fail before protected fields, files, revisions, or existence are disclosed.
- A permission cannot make an invalid cross-tenant relation, duplicate generation source key, invalid monetary value, or prohibited platform operation valid.
- Deleting or restoring a record requires both the specific permission and all versioning/referential checks.
- Inactive tenant denies all tenant-user operations even when a role contains the permission.
- Deactivated user cannot authenticate or exercise permissions.

## Password operations

| Operation | Administrator | Authenticated tenant user | Unauthenticated tenant user |
|---|---:|---:|---:|
| Set initial tenant-user password | Allow | Deny | Deny |
| Reset another tenant user's password | Allow | Deny | Deny |
| Change own password | Allow | Allow | Deny |
| Request forgotten-password email | Not provided | Not provided | Not provided |

Passwords and password hashes never enter audit, revision history, tenant export, or notification payloads.

## Test contract

For each registered ability, tests cover:

1. granted same-tenant allow;
2. missing-permission deny;
3. other-tenant deny without existence leakage;
4. inactive-tenant and deactivated-user deny;
5. protected platform permission cannot be created/assigned through tenant role management;
6. permission grant does not bypass the corresponding domain invariant;
7. role-template changes affect authorization without changing domain code.
