# Piano integrato Laravel replatform

Status: `CURRENT INTEGRATED DESIGN — /speckit.analyze PASSED; IMPLEMENTATION READY`
Historical authoring branch/base: `plan/replatform-3.0.1` / `72262851ba4cf459654ec1a2684fa870b91664e4`
Constitution: 5.0.0
Product decisions: Q-001–Q-041, PD-REV-001, PD-BUD-001, PD-GEN-001, PD-SET-001, PD-DEL-001

## 1. Obiettivo

Implementare Master Plan IT come modular monolith Laravel 13/Filament 5, multi-tenant in un solo database, con:

- una sola sorgente economica corrente: Expense e righe correnti/non eliminate;
- revision history operativa separata;
- BudgetVersion immutabili separate;
- un kernel economico condiviso ma non monolitico;
- permessi tenant configurabili;
- contratti idempotenti e controllabili;
- output semantici identici;
- migrazione e operations diagnosticabili;
- runtime compatibile con hosting PHP/MySQL senza worker permanente.

## 2. Stack bloccato dal piano

- PHP 8.3.32;
- Laravel 13.22.0;
- Laravel Sail 1.64.0;
- MySQL 8.4.10 LTS;
- Filament 5.7.3;
- Livewire 4.3.3;
- Tailwind fornito dal toolchain Filament;
- Chart.js 4.x bloccato dal lock frontend;
- Pest + Larastan/PHPStan + Pint + Dusk limitato;
- `spatie/laravel-permission` 8.3.0;
- `bezhansalleh/filament-shield` 4.3.1;
- `mansoor/filament-versionable` 5.1;
- `overtrue/laravel-versionable` 6.0.0;
- `openspout/openspout` 4.32.0 writer-only;
- `spatie/laravel-backup` 10.3.0 condizionato al gate Composer PHP 8.3.32;
- nessun server PDF package.

Le dipendenze sono installate e bloccate soltanto durante implementazione. `--ignore-platform-reqs`, floating tags e downgrade silenziosi sono vietati.

## 3. Constitution check

| Principio | Design response | Gate |
|---|---|---|
| C-01 evidence | Spec/plan/task/test IDs e decisioni tecniche tracciate | source traceability + analyze |
| C-02 money | DECIMAL(19,6), business DECIMAL(19,2), BCMath value objects | accounting suite, no float scan |
| C-03 sole source | current non-deleted expense rows only | architecture/query tests |
| C-04 explicit operations | named Actions own transactions | no economic observer/package callbacks |
| C-05 current/revision/audit | one current record, snapshot versions outside current | deletion/restore/current exclusion tests |
| C-06 shared hosting | monolith, sync queue, one cron, precompiled assets | hosting preflight |
| C-07 configurable RBAC | Spatie teams + Shield; protected platform abilities | allow/deny/cross-tenant suite |
| C-08 semantic dataset | EconomicDataset shared across presentation/output | parity tests |
| C-09 migration | staging, identity map, quarantine, reconciliation | dry-run/idempotency tests |
| C-10 no decorative abstraction | no repositories/CQRS/event bus/generic settings | architecture tests/review |
| C-11 tenant isolation | explicit tenant_id and fail-closed context | IDOR/query/file/command tests |
| C-12 version contracts | operational versions != BudgetVersion | isolation/immutability tests |
| C-13 generation | source key + exception + resume/manual year | generation matrix |

No constitutional violation is accepted by this plan. Package lock failure or a discovered semantic conflict blocks the relevant feature and requires an ADR amendment.

## 4. Target component map

```text
app/
├── Domain/
│   ├── Shared/
│   │   ├── Data/
│   │   ├── Exceptions/
│   │   └── Support/
│   ├── Money/
│   │   ├── Data/
│   │   └── Services/
│   ├── Tenancy/
│   │   ├── Actions/
│   │   ├── Data/
│   │   └── Queries/
│   ├── IdentityAccess/
│   │   ├── Actions/
│   │   └── Queries/
│   ├── Audit/
│   │   ├── Actions/
│   │   └── Queries/
│   ├── Revisions/
│   │   ├── Actions/
│   │   ├── Data/
│   │   └── Queries/
│   ├── MasterData/
│   │   ├── Actions/
│   │   └── Queries/
│   ├── Expenses/
│   │   ├── Actions/
│   │   ├── Data/
│   │   └── Queries/
│   ├── Contracts/
│   │   ├── Actions/
│   │   └── Queries/
│   ├── Projects/
│   │   ├── Actions/
│   │   └── Queries/
│   ├── Economics/
│   │   ├── Data/
│   │   ├── Queries/
│   │   └── Services/
│   ├── BudgetVersions/
│   │   ├── Actions/
│   │   └── Queries/
│   ├── Scenarios/
│   │   ├── Actions/
│   │   └── Queries/
│   ├── Reporting/
│   │   ├── Data/
│   │   ├── Exports/
│   │   └── Queries/
│   ├── Notifications/
│   ├── Migration/
│   │   ├── Actions/
│   │   ├── Data/
│   │   ├── Importers/
│   │   └── Queries/
│   └── Operations/
│       ├── Actions/
│       └── Data/
├── Filament/
│   ├── Resources/
│   ├── Pages/
│   └── Widgets/
├── Models/
├── Policies/
├── Console/Commands/
└── Support/
```

Directories are created only when the first concrete class exists. Most domains start with a few Actions, one query and DTOs; the map is not permission to scaffold empty layers.

## 5. Responsibility rules

### Models

Persistence relations, casts, scopes without business side effects, domain-specific deletion infrastructure and optimistic lock fields. Expense/master-data restoration and terminal project/contract/term tombstones follow their owning contracts; a generic soft-delete UI is not authoritative. No totals, sync, audit or revision side effects in observers/accessors.

### Actions

One use case, explicit typed input, policy/tenant/invariant checks, one documented transaction boundary, audit and revision batch correlation, stable domain exceptions.

### Queries

Reusable tenant-scoped read datasets. They never mutate and never bypass permission/scope. Reporting queries return DTOs, not Eloquent models as public contracts.

### Filament/Livewire

Collect/validate UI input, authorize, call Actions/Queries, render returned state. No formulas or persistence orchestration.

### Packages

Infrastructure and UI only. Package restore, role generation, backup or notification callbacks cannot become domain authority.

## 6. Shared kernel economic design

Initial files:

```text
app/Domain/Economics/Data/EconomicScope.php
app/Domain/Economics/Data/EconomicLine.php
app/Domain/Economics/Data/EconomicSummary.php
app/Domain/Economics/Data/EconomicDataset.php
app/Domain/Economics/Queries/EconomicDatasetQuery.php
app/Domain/Economics/Services/EconomicEngine.php
```

- Query: tenant/current/non-deleted I/O, projection, filters, detail/output scope.
- Engine: one deterministic pass for components, project buckets, Plafond, Net/VAT/Gross.
- DTOs: immutable contracts.
- Consumers: tenant dashboard, Budget page, report, print/export, BudgetVersion capture.
- Scenario/BudgetVersion: explicit alternative dataset adapters; no second current engine.

Extraction from `EconomicEngine` is allowed only for independent invariants/reuse/dependency/change reason. Line count alone is not a criterion.

## 7. Write transaction pattern

Every complex Action follows:

1. resolve authenticated actor and required permission;
2. resolve explicit tenant context;
3. validate input DTO and cross-tenant references;
4. load current rows with optimistic version where relevant;
5. begin transaction;
6. create `revision_batch` when versioned aggregate changes;
7. mutate current models through explicit code;
8. link vendor snapshots to batch;
9. write minimized `audit_event` with correlation ID;
10. commit;
11. return result DTO;
12. dispatch no asynchronous hidden side effect.

Deadlocks/concurrency conflicts surface as stable errors. No automatic retry is introduced until a measured need and an idempotency contract exist.

## 8. Read and output pattern

1. policy authorizes report/dataset/scope;
2. typed filter/scope DTO is created;
3. tenant-scoped query returns dataset DTO;
4. UI/KPI/chart/print/CSV/XLSX render the same DTO;
5. export records metadata but not payload;
6. filtered and complete report/year scopes are explicit and separately tested.

## 9. Data ownership and identifiers

- application-owned tables use unsigned BIGINT primary keys for minimum package/schema customization;
- every tenant aggregate root has `tenant_id` indexed and FK constrained;
- portability maps source stable IDs to target IDs through identity maps; it does not require preserving numeric PKs;
- legacy IDs are separate nullable/scoped columns or identity-map rows;
- generated occurrences use immutable normalized source keys with unique tenant index;
- optimistic models use unsigned `lock_version` default 1.

## 10. Security design

- `TenantContext` is request-scoped and fail-closed;
- Administrator explicitly enters tenant context and keeps identity;
- tenant users cannot switch tenant;
- Spatie team context is set before authorization and reset between requests/tests;
- global platform abilities are not assignable to tenant roles;
- Policies/Gates protect resources; Actions repeat tenant/invariant checks;
- private attachments use authorized download controller and no public path;
- passwords/secrets never enter audit, revision, notification or export;
- direct object reference tests use identifiers from another tenant;
- complete exports still apply field-level product exclusions.

## 11. Performance design

Reference profile: 10,000 current expense rows per tenant/year.

- composite indexes begin with tenant/year/current state;
- read projections select scalar columns only;
- dashboard invokes economic query once with detail none;
- registers use server pagination;
- complete export/version capture uses cursor/lazy iteration only when compatible with snapshot consistency;
- current version publication uses one MySQL `REPEATABLE READ` transaction and establishes the snapshot before other reads;
- no persistent calculation cache/materialized total at launch;
- benchmarks and EXPLAIN evidence are required before adding cache/preaggregation.

## 12. Testing design

- static: composer validate, Pint, PHPStan/Larastan, architecture rules, frontend build;
- accounting unit: Money/VAT/allocation/engine table cases;
- accounting integration: MySQL current query, generation, revisions, BudgetVersion parity;
- application: policies, Actions, tenant context, lifecycle, exports, commands;
- browser: only JS/Filament geometry/focus/reinforced confirmation/print smoke;
- persistent `master_plan_it_test`, no implicit reset traits;
- each test owns identifiable data and transaction/targeted cleanup.

## 13. Migration strategy

1. export one active Frappe site into versioned CSV package;
2. validate manifest/checksums;
3. stage every row unchanged;
4. normalize to typed staging values;
5. map tenant, years, master data, projects/contracts and expenses;
6. select current legacy record deterministically;
7. preserve useful non-current evidence as operational snapshot/audit metadata without entering current totals;
8. quarantine collisions/unassignable rows;
9. dry-run reconciliation;
10. Product Owner approves exclusions;
11. reinforced apply through domain Actions;
12. post-apply reconciliation and sign-off.

No direct production Frappe DB connection, placeholder data or silent row loss.

## 14. Release/deployment strategy

- Sail verification and GitHub Actions quality gates;
- immutable ZIP from exact verified commit;
- production dependencies and Vite assets prebuilt;
- host preflight for PHP/extensions/MySQL/document root/cron/storage/mysqldump;
- installation backup Created then Verified by empty-environment restore rehearsal;
- forward migrations;
- tenant/auth/accounting smoke;
- activate release;
- rollback separates code artifact, database and shared files.

## 15. Implementation sequence

| Phase | Scope | Dependency | Exit gate |
|---|---|---|---|
| P0 | scaffold/runtime/Sail/test/CI | none | dependency lock + static suite |
| P1 | tenants/users/context/RBAC/settings/audit shell | P0 | cross-tenant and permission suite |
| P2 | years/vendors/cost centers/attachments/revision infrastructure | P1 | lifecycle/tree/restore tests |
| P3 | Money + Expense aggregate + Actual confirmation/revisions | P2 | complete accounting core |
| P4 | projects/contracts/terms/generation/notifications | P3 | generation matrix |
| P5 | economic kernel + dashboard/current Budget | P3/P4 | parity and performance profile |
| P6 | scenarios/BudgetVersion/comparisons/print/CSV/XLSX | P5 | immutability/output parity |
| P7 | migration/tenant portability/backup/deployment | P1–P6 | dry-run/restore/deployment rehearsal |

`/speckit.tasks` must decompose these phases by user story and exact symbols; this table is not an executable task list.

## 16. Open operational evidence

Not resolved by design and not blocking core implementation:

- real legacy export anomalies;
- final hosting account/path/capability profile;
- signed legacy report parity inventory.

They block cutover and exact deployment/report parity, not the approved architecture.

## 17. Work explicitly prohibited

- implementing from old task files;
- fixed role-name branching except protected Administrator boundary;
- float money;
- Eloquent observers with domain/economic effects;
- persistent current Budget totals;
- revision/audit tables queried as current;
- generic repositories, CQRS, event sourcing, internal APIs;
- additional UI kits;
- server PDF dependency at launch;
- XLSX import;
- implicit database reset;
- queue/Redis/WebSocket requirement;
- fallback dependency versions or ignored platform requirements.
