# Implementation Plan: Plafond Singolo e Copertura Integrale

**Branch**: `agent/024-single-plafond-coverage` | **Date**: 2026-08-12 | **Spec**: [spec.md](spec.md)

**Delivery Status**: `VERIFIED CURRENT` on 2026-08-13; all 68 tasks and required gates complete.

**Input**: Feature specification from `specs/024-single-plafond-coverage/spec.md`

## Summary

Deliver one user-facing vertical Slice that creates the unique live Plafond for a Tenant, Planning
Year and allocation Cost Center, changes Allocation through signed dated/attributed ExpenseRows,
covers each Ordinary row wholly with one same-Tenant/same-Year Plafond, blocks covered Actuals and
allocation reductions that exceed capacity, and reconciles Document, Register, Budget and Report.

The design consolidates the Greenfield base schema, extends the existing Expense aggregate and
single EconomicEngine, and reuses the annual mutation guard. It creates Plafond only through
`POST /api/v1/plafonds`; generic Expense writes remain Ordinary and carry the single coverage
reference. The engine computes the four exact measures from the full annual dataset before filters.
All lifecycle-sensitive writes remain preparation-only until Slice 025–026.

## Technical Context

**Language/Version**: PHP 8.3.32; TypeScript 5.7.2; SQL for MySQL 8.4.10

**Primary Dependencies**: Laravel 13.22, Sanctum 4.3, BCMath, Eloquent/MySQL transactions,
overtrue/laravel-versionable 6, React 19, React Router 7, Axios 1.19, Tailwind CSS 4

**Storage**: MySQL 8.4.10; consolidated Greenfield `expenses` and `expense_rows` base migrations;
existing RevisionBatch/Item and Audit persistence

**Testing**: Pest 4 / PHPUnit 12, real-MySQL Accounting and Application suites, Xdebug economic
line/branch coverage gate, Vitest 3 + Testing Library, ESLint, TypeScript/Vite production build

**Target Platform**: API-only Laravel service and React browser application in the existing Docker
Compose Linux environment; desktop-first Workspace with maintained responsive behavior

**Project Type**: Multi-Tenant web application with Laravel business owner and React presentation
client

**Performance Goals**: No new throughput target is introduced. Every Plafond write must remain a
single bounded annual transaction; list/report endpoints retain existing pagination and compute the
annual projection once per request, not once per Plafond or row.

**Constraints**: Exact decimal money and Base-specific capacity; no float authority; one live
Plafond per Tenant/Year/Plafond-CC enforced in MySQL; full annual dataset before filters; no overrun,
partial coverage, coverage reservation or second engine; annual lock then roots/rows ascending; no
cross-Tenant leakage; no new permission vocabulary; no raw destructive reset commands

**Scale/Scope**: One vertical Slice across the existing Expense aggregate, annual projection,
dedicated Plafond API and four user surfaces. No fixed row-count assumption; correctness must hold
for every current row in the selected Tenant/Year and for concurrent writes to different Expenses.

## Authority and Planning Inputs

- Product authority: `specs/BUDGET-DOMAIN-REFINEMENT.md` and this Slice's accepted
  [specification](spec.md).
- Program design authority: `specs/022-application-workspace-ux/` artifacts.
- Verified implementation baseline: Slice 023 at `0d6c347289d886359923e582d6805f0accf489b8`.
- Current repository manifests, Compose configuration and constitution override the stale ancestor
  `/root/masterplan/AGENTS.md` where it describes the previous Frappe/Python repository. Only its
  compatible general principles—test each change and intentional commit summaries—remain useful;
  no commit is part of this planning phase.
- Architect/explorer evidence confirmed: missing DB live uniqueness; existing single funding FK and
  DB XOR; canonical `AnnualEconomicMutationGuard`; current engine lacks Plafond classification;
  current report filters too early; stale overrun fields remain in API/UI; Tenant basis update
  already uses Tenant → annual locks but lacks capacity revalidation.

## Constitution Check — Pre-Design Gate

| Principle | Result | Evidence / consequence |
|---|---|---|
| Minimal permanent documentation | PASS | All proposed behavior stays inside Slice 024 artifacts until implementation is verified. |
| Vertical temporary Spec Kit | PASS | The Slice crosses schema, Laravel, API, React and tests to deliver one complete user outcome. |
| Authority on current behavior | PASS | Slice 023 code/contract is treated as baseline; this plan specifies only the Plafond delta. |
| Product decisions belong to PO | PASS | XOR and cross-Cost-Center answers are fixed; no unresolved product choice or clarification remains. |
| Laravel sole business owner | PASS | One server projection and guarded Actions own formulas; React only presents typed values. |
| Exact money / current rows | PASS | Existing BCMath/Net-VAT-Gross contract is reused; only current non-deleted rows contribute. |
| Explicit complex mutations | PASS | Dedicated Plafond Actions and full Expense aggregate Actions own effects; no observer/model hook. |
| Minimum architecture | PASS | No repository, CQRS, event bus, service locator, microservice or second engine is introduced. |
| Tenant isolation and server authorization | PASS | Existing context/ability/non-leakage contract is extended to every route and relation. |
| Rollback, audit and verification | PASS | Annual lock, atomic Revision/Audit, real-MySQL concurrency and frontend parity gates are planned. |

No Constitution violation requires a complexity exception. Phase 0 may proceed.

## Project Structure

### Documentation (this feature)

```text
specs/024-single-plafond-coverage/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── api-contract.md
└── checklists/
    └── requirements.md
```

`tasks.md` is Phase 2 output and is not created by `speckit-plan`.

### Source Code (repository root)

```text
app/
├── Domain/
│   ├── Budget/
│   │   ├── Queries/AnnualBudgetQuery.php
│   │   └── Services/AnnualEconomicMutationGuard.php
│   ├── Economics/
│   │   ├── Data/{EconomicLine,ProjectedEconomicLine,AnnualEconomicProjection}.php
│   │   └── Services/EconomicEngine.php
│   ├── Expenses/
│   │   ├── Actions/{CreateExpense,UpdateExpense,DeleteExpense,RestoreExpenseRevision}.php
│   │   ├── Data/
│   │   ├── Enums/ExpenseType.php
│   │   ├── Queries/{ExpenseDetailQuery,PreviewExpenseQuery}.php
│   │   └── Services/ExpenseAggregateValidator.php
│   ├── Reporting/Queries/{EconomicDatasetQuery,AnnualEconomicReportQuery}.php
│   └── Tenancy/Actions/UpdateTenantSettings.php
├── Http/
│   ├── Controllers/Api/V1/
│   └── Resources/
└── Models/{Expense,ExpenseRow}.php

database/
├── migrations/2026_08_03_020001_create_expenses_table.php
├── migrations/2026_08_03_020002_create_expense_rows_table.php
├── factories/
└── seeders/

routes/api/v1/

frontend/src/
├── api/{expenses,budget,reports}.ts
├── components/
│   ├── expenses/
│   ├── budget/
│   └── reports/
├── pages/
└── navigation/

tests/
├── Accounting/
├── Architecture/
├── Feature/
└── Support/
```

**Structure Decision**: Keep the current Laravel/React application. Add the smallest Plafond-specific
Actions/query/controller/resource/UI composition needed while extending shared Expense and economic
types. Do not create a parallel bounded context, frontend state engine or generic allocation layer.

## Phase 0 Output — Research

[research.md](research.md) resolves twelve design decisions:

1. one dedicated Plafond creation route;
2. generated nullable active-slot DB uniqueness consolidated Greenfield;
3. `allocation_adjustment` as a non-zero signed row using existing `spend_date` and server-owned
   immutable `created_by_user_id`;
4. coverage through the existing Ordinary Expense aggregate;
5. one engine and the four measures;
6. full annual dataset before presentation filters;
7. shared annual lock and confirmation revalidation;
8. Base-change capacity revalidation;
9. preparation-only transitional lifecycle;
10. existing delete/live-root revision restore invariants without Trash redesign;
11. reused permissions, stable errors and atomic evidence;
12. four-surface UI parity and removal of stale overrun vocabulary.

There are no unresolved clarification markers.

## Phase 1 Outputs — Design and Contracts

- [data-model.md](data-model.md) defines Expense kind/row matrices, generated live-slot uniqueness,
  exact derived measures, relationships, lock order and valid mutation transitions.
- [contracts/api-contract.md](contracts/api-contract.md) defines the sole create route, Plafond
  list/detail/adjustment/report endpoints, Ordinary coverage delta, exact money/error shapes,
  authorization and consumer removal contract.
- [quickstart.md](quickstart.md) provides protected reset, targeted verification scenarios, complete
  Docker gate commands and expected results.

## Implementation Strategy

### Stage 1 — Consolidate the Greenfield Schema

1. Update the base `expenses` migration with the nullable generated live-slot discriminator and
   unique key. Do not add a backfill or compatibility migration.
2. Update the base `expense_rows` migration and enum with `allocation_adjustment`, one
   `created_by_user_id` FK, and kind/date/zero/XOR checks compatible with existing Actual date rules.
3. Update model casts/relations plus factories/seeders so the protected reset creates only valid
   target aggregates.
4. Prove MySQL rejects concurrent duplicate live slots and invalid row shapes independently of
   application validation.

### Stage 2 — Extend the Single Economic Projection

1. Carry Expense kind, funding identity, Plafond/consumer Cost Centers and allocation row metadata
   through the existing dataset/line types.
2. Load the complete annual current dataset without early Cost Center filtering.
3. Extend `EconomicEngine` to classify Allocation once, selected covered planning only as
   `coverage_planned`, and covered Actual as annual Actual plus `consumed`; derive Available exactly.
4. Add per-Plafond projection and impact DTOs without persisting a second monetary source.
5. Remove overrun/Plafond-residual fields from maintained Budget and Report resources and adapters;
   preserve the unrelated general Budget residual.

### Stage 3 — Implement Guarded Mutations and API

1. Add dedicated create, adjustment preview and adjustment Actions plus read queries/resources for
   the endpoint shapes in the API contract.
2. Extend the existing Ordinary Expense write/preview validator with the funding relationship and
   final-dataset checks; do not expose Extra Budget classification.
3. Acquire the annual guard before roots/rows in ascending ID, then reconstruct the annual dataset
   and revalidate at confirmation. Preview never reserves capacity.
4. Apply preparation-only gating to Plafond/Allocation/coverage/delete/revision-restore effects.
5. Revalidate every year in the proposed Base during the already locked Tenant settings change.
6. Keep existing delete and live-root revision restore routes, blocking referenced Plafond deletion
   and all invalid restored aggregates. Do not add soft-deleted root restore.
7. Record exactly one aggregate Revision and business Audit per successful Plafond, Allocation or
   coverage mutation; roll back on any evidence failure.

### Stage 4 — Deliver the Four User Surfaces

1. Add Plafond create/register/detail and AllocationAdjustment/impact interactions using existing
   Workspace components and TailAdmin primitives.
2. Add full-coverage selection to Ordinary Expense rows; show both Cost Centers when different.
3. On `PLAFOND_INSUFFICIENT`, preserve all input, highlight the Plafond section, display the exact
   four amounts and present all four recovery choices.
4. Show Allocazione, Copertura Prevista, Consumato and Disponibile identically in Document, Register,
   Budget and Report; Report drill-down retains cross-Cost-Center contributors.
5. Remove **Sforamento** and stale overrun fallback from API types, Budget and Report UI/tests.

### Stage 5 — Verify the Complete Story

1. Add table-driven pure-engine tests for every row-type/kind/current/funding/sign/Base branch and
   include modified economic classes in the versioned coverage manifest.
2. Add real-MySQL Accounting tests for unique slot, exact formulas, full-dataset filtering,
   concurrent capacity, Base switch, delete and live-root revision restore.
3. Add Feature/API matrix tests for allow, missing ability, missing/foreign indistinguishability,
   inactive branches, state, stale, preview/no reservation, rollback and exact resources/errors.
4. Add frontend adapter/component/page tests for all four surfaces, loading/empty/error/permission,
   cross-CC labels, input retention, four recovery choices and removed overrun vocabulary.
5. Run the targeted commands and full gates in [quickstart.md](quickstart.md); do not report any gate
   green unless actually executed.

## Shared-Owner Coordination

The primary integration owner owns the shared migration baseline, `EconomicEngine` and projection
DTOs, Budget/Report datasets, common error mapping, permission catalogue, route aggregation and
program/permanent documentation. Work touching those files must be integrated as one coherent
change before parallel writers modify dependent adapters. The implementation Slice must not fork a
second engine or duplicate shared types to avoid ownership.

Recommended serialization:

1. primary/shared owner lands schema + projection contract and fixture changes;
2. backend aggregate/API owner builds Actions and resources against that exact contract;
3. frontend owner starts only when response fixtures/types are frozen;
4. test/review work may proceed read-only or on disjoint test files, but migration, shared economic
   DTO and route conflicts return to the primary owner.

## Agent Handoff Packets

### Packet A — Shared Schema and Economic Core

**Input**: spec FR-001–FR-030, [research R02–R08](research.md),
[data model](data-model.md), contract economic shapes.

**Owned output**: consolidated base migrations, enum/model/DTO/engine/dataset changes, exact fixture
manifest and Accounting tests. Preserve full annual input before filtering and Budget residual.

**Must prove**: database active uniqueness; non-zero/date/author row matrix; exact Net/Gross four
measures; no double count; cross-CC contributors; concurrent overcommit prevention; Base-change
rollback; 100% applicable line/branch economic coverage.

**Do not do**: API/UI lifecycle extension, Extra Budget surface, compatibility migration, second
engine or raw reset.

### Packet B — Expense/Plafond Mutations and API

**Input**: frozen core types plus [API contract](contracts/api-contract.md).

**Owned output**: dedicated Plafond create/list/detail/adjustment/report capabilities; coverage delta
in existing Expense preview/create/update; guarded delete/live-root revision restore; exact resources,
errors, Revision/Audit and Feature/API tests.

**Must prove**: reused `expense.*` abilities; preparation-only state; root 404/body 422 no leakage;
preview no reservation; confirmation revalidation; roots/rows ascending; stable
`PLAFOND_INSUFFICIENT`; no partial data or success evidence.

**Do not do**: second Plafond creation route, `is_extra` request/UI, soft-deleted root restore, Trash
redesign, new permissions or approval/Rectification semantics.

### Packet C — React Four-Surface Parity

**Input**: frozen response fixtures from Packet B and contract removal list.

**Owned output**: Plafond Workspace, Ordinary coverage selector/impact, Budget and Report parity,
adapter/types/tests for Document, Register, Budget and Report.

**Must prove**: exact strings preserved; three distinct usage measures plus Allocation; both Cost
Centers shown; insufficient input retained; all four corrections visible; loading/empty/error/403;
no **Sforamento**, overrun field or local formula/fallback.

**Do not do**: infer availability, expose Extra classification, add partial allocation controls or
keep stale compatibility defaults.

### Packet D — Read-Only Security and Contract Review

**Input**: completed implementation and all artifacts.

**Output**: findings only, ranked by severity and linked to exact paths/scenarios; no competing
implementation.

**Must inspect**: every route/ability and inactive branch; missing/foreign equivalence; cross-CC
non-leakage; lock hierarchy/write skew; preview race; Revision/Audit redaction/cardinality; exact
error and four-consumer parity; removal of stale overrun vocabulary.

## Executable Quality Gates

Run from the repository root with Docker services available:

```bash
docker compose up -d laravel.test mysql frontend
docker compose exec -T -u sail laravel.test php artisan app:test-reset-greenfield --seed
docker compose exec -T -u sail laravel.test composer test:static
docker compose exec -T -u sail laravel.test composer test:accounting
docker compose exec -T -u sail laravel.test composer test:economic-coverage
docker compose exec -T -u sail laravel.test composer test:application
docker compose exec -T frontend npm run verify
```

Forbidden validation shortcuts: raw `migrate:fresh`, `db:wipe`, container-volume deletion, SQLite
substitution, bypassing strict MySQL or reporting historical Slice 023 counts as current results.

## Constitution Check — Post-Design Re-evaluation

| Principle | Result | Design evidence |
|---|---|---|
| Vertical Slice | PASS | Stages finish a usable Plafond path across all layers and four surfaces. |
| No invented product decisions | PASS | Research resolves only technical choices; lifecycle writes stop at the approved dependency boundary. |
| Laravel business owner / one engine | PASS | Full dataset enters one EconomicEngine; clients receive exact measures and errors. |
| Tenant isolation / server auth | PASS | Reused abilities, scoped identities, composite relations and no-leakage matrices are contractual. |
| Exact money / no duplicate source | PASS | AllocationAdjustment remains ExpenseRow and reuses Slice 023 decimal/VAT pipeline. |
| Explicit atomic mutations | PASS | Annual guard, ascending root/row locks, final-dataset validation, Revision and Audit share one transaction. |
| Minimum complexity | PASS | One dedicated creation surface, one funding FK, no allocation entity/repository/event bus/compatibility layer. |
| Tests and rollback | PASS BY PLAN | Quickstart requires MySQL uniqueness/concurrency, economic coverage, API security, UI parity and full gates. |
| Permanent documentation discipline | PASS | No permanent/shared document changes occur before verified implementation. |

Design is constitution-compliant with no justified violation and is ready for `$speckit-tasks`.
