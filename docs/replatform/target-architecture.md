# Target architecture

Status: `APPROVED TARGET — API-ONLY AMENDMENT 2026-08-07; IMPLEMENTATION IN PROGRESS`
Authority: Constitution 6.0.0; ADR-036; `replatform-plan.md`; `technical-research.md`

## Runtime

- PHP 8.3.32;
- Laravel 13.22.0;
- Laravel Sail 1.64.0;
- MySQL 8.4.10 LTS, InnoDB, utf8mb4, strict mode;
- Laravel API-only runtime with JSON resources/DTOs under `/api/v1`;
- Sanctum SPA session authentication, `statefulApi()` and `auth:sanctum`;
- React/TypeScript with official TailAdmin React Free as a separate frontend deployable;
- a public frontend reverse proxy forwards relative `/api/v1/*` and `/sanctum/*` requests to
  Laravel on a private loopback/internal origin;
- sync queue and one scheduler cron where required by backend operations;
- no Laravel Blade application pages, Tailwind, Alpine, ApexCharts, Vite, npm frontend runtime,
  wildcard CORS or public developer API.

Exact dependency resolution is the first implementation gate; failure blocks and amends the plan, never silently falls back.

## Application boundary

Laravel remains one modular monolith and one database, deployed separately from the frontend.
Tenants use explicit `tenant_id`; tenant users belong to one tenant. Administrator is protected
global identity and enters explicit tenant context without impersonation. The browser knows only
relative same-origin paths; `API_INTERNAL_ORIGIN` is proxy configuration and is never sent to
client JavaScript.

RBAC: Spatie Permission 8.3.0 teams keyed by `tenant_id` plus Filament Shield 4.3.1. Team context supports permissions but does not replace business query scoping. Policies/Gates authorize; Actions enforce tenant and domain invariants.

## Domain boundaries

```text
Domain/
├── Shared
├── Money
├── Tenancy
├── IdentityAccess
├── Audit
├── Revisions
├── MasterData
├── Expenses
├── Projects
├── Contracts
├── Economics
├── BudgetVersions
├── Scenarios
├── Reporting
├── Notifications
├── Migration
└── Operations
```

Directories/classes are created only for concrete responsibility. No repository layer, CQRS, event bus, service locator or empty pseudo-DDD scaffolding.

- Actions own complex writes/transactions.
- Queries own reusable tenant-scoped read DTOs.
- Models own persistence/relations/casts without domain side effects.
- API controllers authorize, validate, load data and invoke Actions/Queries; API Resources/DTOs
  transform operation-oriented responses. The React client renders only server-authoritative
  data and uses `abilities` for presentation/navigation hints. No presentation layer owns
  authoritative formulas and no second backend/business layer is permitted.
- Packages provide infrastructure/UI only.

## Current/history/data-set separation

Distinct mechanisms:

1. current non-deleted domain records;
2. operational snapshot versions plus revision batches;
3. minimized audit events with configurable global retention;
4. immutable BudgetVersion economic snapshots;
5. explicit non-official scenarios;
6. generation exceptions as non-economic control state.

Only current non-deleted Expense rows enter current economic totals.

## Shared economic kernel

Initial implementation is exactly:

- `EconomicDatasetQuery` for tenant/current/non-deleted I/O/projection;
- `EconomicEngine` for pure bucket/Plafond/Net-VAT-Gross formulas;
- four immutable DTOs: scope, line, summary, dataset.

Dashboard, current Budget, reports, print/export and current snapshot capture consume the same dataset. Scenarios/BudgetVersion use explicit alternative resolvers; there is no second current engine.

## Versioning

Mansoor 5.1 + Overtrue 6.0 snapshots, subject to executable lock/smoke. Application `revision_batches` correlates aggregate changes. Package restore is not domain authority: Restore Actions rebuild typed input and revalidate current permissions, tenant, references and invariants.

Published BudgetVersion is application-owned and never restored through model-version tooling.

## Data/operations

- typed singleton `platform_settings`; no generic settings package;
- native Filesystem plus application-owned current attachment membership, complete revision manifests and immutable private payload versions; unchanged bytes reuse one version, distinct retained versions count once toward quota, and permanent Expense deletion purges their bytes; no Media Library;
- application audit table; no audit package/export at launch;
- native database notifications + optional sync mail;
- CSV authoritative import/export; OpenSpout 4.32 writer-only for XLSX;
- API output resources remain presentation-neutral; print/export presentation belongs to the
  authorized client while Laravel owns export data and file authorization;
- Spatie Backup 10.3 conditional on PHP 8.3 Composer resolution and host preflight.

## Testing/release

Sail canonical environment; separate persistent `master_plan_it_test`; no implicit reset traits/commands. Static, accounting and application layers with bounded Dusk. GitHub Actions builds one immutable ZIP from exact verified commit; host deploys unchanged.

## Performance

Reference 10,000 current rows per tenant/year. Scalar projections, composite tenant/year indexes, one engine pass, dashboard detail-none and server pagination. No current-total cache/materialization before benchmark and EXPLAIN evidence.

## Migration

One Frappe site → one selected tenant via versioned CSV package, staging, identity map, quarantine, dry-run, explicit exclusions, bounded apply batches and reconciliation. Legacy replacement state is migration evidence, not target current lifecycle.

## Remaining cutover evidence

Real source anomalies, final host profile and signed report parity inventory. These do not alter the planned core architecture but block cutover.
