# Target architecture

Status: `CLARIFIED TARGET — PACKAGE/PHYSICAL PLAN REQUIRED`

## Fixed principles

- Laravel 13 modular monolith.
- PHP current stable branch supported by Laravel 13/Sail, with exact version locked by the development-foundation plan.
- MySQL 8.4 LTS and supported current MySQL family according to the approved development/test contract; InnoDB, `utf8mb4`, strict SQL mode.
- Filament 5 and Blade as the application/admin UI; Livewire only for stateful interaction; Alpine only for local visual state.
- Tailwind CSS through the Filament/Vite toolchain; do not retain Preline when Filament already supplies the needed component.
- Pest for unit/feature/architecture/accounting tests; small Laravel Dusk suite for browser-owned behavior.
- Chart.js only for charts.
- Vite at build time only; production receives compiled assets.
- Queue connection `sync`; one cron invokes Laravel scheduler; no Redis/WebSockets/permanent worker.

Exact runtime/database/CI versions are owned by PR #2 and must be reconciled after this clarification PR.

## Tenant and authorization boundary

- One application/database serves multiple customer tenants with explicit `tenant_id` ownership.
- `Administrator` is protected and global; it selects tenant context without impersonation.
- Tenant users belong to exactly one tenant and receive one or more tenant-scoped configurable roles.
- `Editor` and `Viewer` are seeded role templates, not hard-coded domain branches.
- Candidate authorization stack is `spatie/laravel-permission` with tenant/team scope plus `bezhansalleh/filament-shield`; exact releases and integration require TS-001/TS-002.
- Policies/Gates decide ability; domain Actions then enforce tenant, monetary, referential, versioning, and generation invariants.
- Missing/invalid/inactive/unauthorized context fails closed.
- Queries, policies, reports, exports, print, files, revisions, commands, notifications, and scheduled work enforce tenant scope.

## Current state and history boundary

Four mechanisms remain distinct:

1. current domain records;
2. operational model revisions;
3. minimized audit events retained according to the Administrator-only global setting, default 24 months;
4. named immutable budget-version snapshots.

Candidate operational revision UI/storage is `mansoor/filament-versionable` backed by `overtrue/laravel-versionable`, subject to TS-003. Aggregate operations use application-owned revision batch metadata when the package stores child models independently.

Current economic queries read only current non-deleted Expense rows. Deleted records, revisions, audit, scenarios, budget snapshots, and generation exceptions are never implicit economic sources.

## Modular monolith boundaries

```text
app/
├── Domain/
│   ├── Shared/
│   ├── Money/
│   ├── Tenancy/
│   ├── IdentityAccess/
│   ├── MasterData/
│   ├── Expenses/
│   ├── Contracts/
│   ├── Projects/
│   ├── Reporting/
│   ├── BudgetVersions/
│   ├── Notifications/
│   └── Migration/
├── Filament/
│   ├── Resources/
│   ├── Pages/
│   └── Widgets/
├── Models/
├── Policies/
├── Console/Commands/
└── Support/
```

These directories are logical planning targets, not permission to create one layer/class per noun. Use the smallest structure that preserves explicit responsibility.

`Domain/*/Actions` own complex writes and transactions. `Domain/*/Queries` own reusable typed datasets. Eloquent models describe persistence. Policies/Gates own authorization. Filament resources/pages call Actions/Queries and do not calculate authoritative economics.

## Dependency rules

- UI depends on policies plus Actions/Queries; domain code does not depend on Filament.
- Reporting reads current domain data or an explicitly selected scenario/budget-version dataset; it does not mutate source records.
- Budget-version publication copies exact typed snapshot rows through an application-owned Action; generic model versioning does not publish economic baselines.
- Contracts invoke Expense generation Actions. Expenses store nullable contract/project context and generation source identity but do not call contract UI/services.
- Generation exceptions are control state owned by Contracts and never queried as monetary rows.
- Migration/tenant portability stage raw data and invoke public domain Actions; they cannot bypass current invariants.
- Backup tooling owns archive mechanics only; the application owns scope, verification, status, and authorization.
- Notifications use native Laravel channels; failure is explicit and no package/queue hook hides it.
- Audit retention uses one typed global platform setting read by an explicit bounded scheduler command; no tenant-specific duplicate retention configuration is introduced.

## Persistence groups

The physical plan must cover at minimum:

- tenants, users, tenant roles/permissions/assignments;
- typed global platform settings, including `audit_retention_months`;
- tenant-owned master data;
- current expenses and rows with soft-deletion infrastructure;
- operational versions plus revision-batch metadata;
- contracts/projects/terms and generation exceptions/history;
- scenarios and rows;
- budget versions and immutable snapshot rows;
- attachments;
- minimized audit events and database notifications;
- migration/import staging, identity maps, quarantine, exclusions, reconciliation;
- backup/restore status and verification metadata where application persistence is required.

## Migration sequencing

Legacy project/contract identifiers remain separate reconciliation fields until same-tenant target references are created and verified.

Legacy Expense Row `state` and replacement links are migration inputs only. Planning must deterministically select the accepted current target record and preserve useful non-current evidence as operational revision/audit metadata without placing legacy parallel states into current economic tables.

## Package rule

A candidate package is adopted only after exact compatibility, tenancy behavior, license, maintenance, security, testability, and removal-path verification. Package callbacks/observers may not own economic writes. If a candidate fails, retain the approved product contract and implement the smallest native Laravel/Filament alternative.
