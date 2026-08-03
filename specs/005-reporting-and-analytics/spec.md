# Feature 005 — Reporting, budget versions, and analytics

Status: `CLARIFIED — PLAN REGENERATION REQUIRED`  
Logical owner: Product Owner with domain approval  
Actor: tenant user with explicit permission  
Dependencies: Feature 003, Feature 004, and Feature 007

## Problem

The product needs one rolling current economic view, immutable named budget versions, shared what-if scenarios, and consistent reports/exports without treating contracts, revisions, deleted records, or scenario data as current economic sources.

## Objective

Provide exact tenant-scoped dashboards, current economic position, named budget-version capture/comparison, shared scenarios, print/export, and guided empty states from explicit server-side dataset contracts.

## User stories

### US-005-01 — Current rolling budget

An authorized user views the current tenant/year position calculated from current non-deleted expense rows.

### US-005-02 — Create named budget version

An authorized user creates a draft snapshot, optionally adjusts manual snapshot rows where allowed, names it, and publishes it as an immutable version such as `Budget approved`.

### US-005-03 — Compare versions

An authorized user compares current values with one version or compares two versions using identical grouping and exact decimal calculations.

### US-005-04 — What-if scenario

An authorized user creates and shares a persistent tenant scenario that never modifies official expenses or current totals.

### US-005-05 — Print and export

An authorized user prints or exports the same filtered dataset shown on screen.

## Acceptance scenarios

### AC-005-01 — Current dataset

Given current, revised, deleted, generated, and suppressed records, the current dashboard reads only current non-deleted expense rows. Contracts/projects, operational revisions, audit, generation exceptions, scenarios, and budget-version snapshot rows are excluded.

### AC-005-02 — Publish budget version

Given a tenant/year and current filters, publishing captures normalized exact rows, source IDs/revision IDs, grouping, totals, currency/locale/timezone, format version, checksum, actor, and timestamp. The published version is immutable.

### AC-005-03 — Manual version draft

Given permission to create a budget version, the actor may prepare a manual draft using the approved schema. Publishing validates exact totals and freezes it. Editing a published baseline is denied; another version must be created.

### AC-005-04 — Comparison

Current-versus-version and version-versus-version comparison aligns rows using stable dimensions, reports additions/removals/changes, and computes exact variances without mutating either dataset.

### AC-005-05 — Scenario

A scenario belongs to one tenant, is persistent and shared, is visibly marked non-official, and is included only when explicitly selected in scenario views.

### AC-005-06 — Empty tenant

When the tenant/year has no matching economic rows, report pages remain accessible, mathematically defined KPIs show zero, charts/tables are empty, explanatory guidance links to prerequisites, and no example data is invented.

### AC-005-07 — Single-tenant output

Every dashboard, drill-down, version, scenario, print, CSV, and XLSX contains exactly one tenant. Administrator global overview/export contains only approved operational fields.

### AC-005-08 — Semantic equality

For identical filters and selected dataset type, screen, KPI, chart, print, CSV, and XLSX use the same server dataset and exact totals.

## Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| FR-005-001 | Current dashboard filters shall include tenant, year, and optional cost center plus feature-approved filters. | AC-005-01 |
| FR-005-002 | Each KPI shall expose formula, source dataset, empty-state behavior, and drill-down. | AC-005-01, AC-005-06 |
| FR-005-010 | Current economic position shall not add project or contract values independently. | AC-005-01 |
| FR-005-011 | Available/current budget formulas shall be defined once in the query contract and use current Expense data only. | AC-005-01 |
| FR-005-012 | Operational revisions, audit, deleted records, generation exceptions, scenarios, and budget versions shall be excluded from current totals. | AC-005-01 |
| FR-005-020 | Table, KPI, chart, print, CSV, and XLSX for one selected dataset shall share one query/result contract. | AC-005-08 |
| FR-005-021 | Outputs shall preserve filters, order, locale, currency, timezone, and selected dataset identity. | AC-005-08 |
| FR-005-022 | Every economic output shall contain exactly one tenant. | AC-005-07 |
| FR-005-023 | Global overview/export shall contain only approved operational tenant metadata and no behavioral telemetry or cross-tenant economics. | AC-005-07 |
| FR-005-030 | Scenario view/manage abilities shall be permission-controlled. Scenarios shall be persistent, tenant-owned, shared, labelled non-official, and excluded from official totals. | AC-005-05 |
| FR-005-040 | An authorized actor shall create a tenant/year `BudgetVersion` draft from current data or approved manual snapshot rows. | AC-005-02, AC-005-03 |
| FR-005-041 | Publishing shall freeze the exact snapshot, metadata, totals, source references, checksum, actor, and timestamp. | AC-005-02 |
| FR-005-042 | Published budget versions shall be immutable; a correction shall create a new version. | AC-005-03 |
| FR-005-043 | The system shall compare current-versus-version and version-versus-version without modifying source data. | AC-005-04 |
| FR-005-044 | Budget-version view/create/compare operations shall be separately permission-controlled. | AC-005-02, AC-005-04 |
| FR-005-050 | Empty reports shall show valid zeros/empty datasets and guidance without fabricated values. | AC-005-06 |
| FR-005-060 | Report/export generation shall record safe actor, tenant, dataset type, filters, output type, and correlation metadata without logging payload contents. | AC-005-08 |

## Business invariants

| ID | Rule | Error | Test |
|---|---|---|---|
| INV-REP-001 | Current reports never double-count contracts/projects. | DomainConflict | TEST-005-001 |
| INV-REP-002 | Presentation forms share one semantic dataset. | DomainConflict | TEST-005-002 |
| INV-REP-003 | Metric formulas are server-side and decimal-safe. | DomainConflict | TEST-005-003 |
| INV-REP-004 | Permission and tenant filters limit every visible row and output. | Authorization/DomainConflict | TEST-005-004 |
| INV-REP-005 | No economic dataset contains multiple tenants. | DomainConflict | TEST-005-005 |
| INV-BUD-001 | Published budget versions are immutable. | DomainConflict | TEST-005-006 |
| INV-BUD-002 | Budget-version rows/totals/checksum are exact and internally consistent. | DomainConflict | TEST-005-007 |
| INV-BUD-003 | Budget versions never mutate or substitute current Expense records. | DomainConflict | TEST-005-008 |
| INV-SCN-001 | Scenario values never enter official current totals. | DomainConflict | TEST-005-009 |
| INV-EMPTY-001 | Empty state never invents economic values. | DomainConflict | TEST-005-010 |

## Success criteria

- current register/KPI/chart/print/export totals are exactly equal for the same filters;
- a named approved budget version remains unchanged after source expenses change;
- comparison detects exact additions, removals, and monetary variances;
- empty-tenant views render without errors or invented data;
- cross-tenant and missing-permission tests cover reports, versions, scenarios, print, and export.

## Out of scope

- cross-tenant economic analytics or comparisons;
- behavioral usage analytics;
- published-version in-place edits;
- private per-user scenarios;
- using model revision storage as a budget snapshot;
- presentation-side recalculation;
- adding contracts/projects directly to totals.

## Clarification result

Q-019, Q-020, Q-027, Q-028, Q-033, and PD-BUD-001 are closed. Existing Feature 005 plan, tasks, formulas, contracts, and data model must be regenerated before implementation.
