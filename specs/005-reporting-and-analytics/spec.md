# Feature 005 — Reporting, budget versions, and analytics

Status: `CLARIFIED AND APPROVED; IMPLEMENTATION BLOCKED UNTIL /speckit.analyze PASSES`  
Logical owner: Product Owner with domain approval  
Actor: tenant user with explicit permission  
Dependencies: Feature 003, Feature 004 and Feature 007  
Additional decisions: Q-034, Q-036, Q-038, Q-040

## Problem

The product needs one rolling current Budget per tenant/year, immutable named BudgetVersion snapshots, shared what-if scenarios and consistent dashboards/reports/exports without treating contracts, operational revisions, deleted records or scenarios as implicit current economic sources.

The same rules must serve Budget, dashboard, reports, print and export without creating either a monolithic query object or a fragmented calculator for every KPI.

## Objective

Provide exact tenant-scoped current Budget, historical/manual evidence, project buckets, Plafond treatment, named version capture/comparison, shared scenarios, print/export and guided empty states from one shared server-side economic dataset contract.

## User stories

### US-005-01 — Current rolling Budget

An authorized user views one current tenant/year Budget calculated from current non-deleted expense rows, with official Net/Gross basis and separate economic components.

### US-005-02 — Historical year

An authorized user reconstructs a previous year from available data or creates a Manual BudgetVersion that explicitly declares total-only, partial or full detail.

### US-005-03 — Create named BudgetVersion

An authorized user captures the current tenant/year dataset or prepares an approved manual draft, names it and publishes it as an immutable version such as `Budget approved`.

### US-005-04 — Select reference and compare

An authorized user selects a reference version and compares current-versus-version, version-versus-version or compatible cross-year datasets without modifying source data.

### US-005-05 — What-if scenario

An authorized user creates and shares a persistent tenant scenario that never modifies official expenses or current totals.

### US-005-06 — Print and export

An authorized user explicitly prints or exports either the current filtered result or the complete selected report/year scope, always for one tenant.

## Acceptance scenarios

### AC-005-01 — Current dataset source

Given current, revised, deleted, generated and suppressed records, the current Budget reads only current non-deleted Expense rows. Projects/contracts are context only. Operational revisions, audit, tombstones, generation exceptions, scenarios and BudgetVersion snapshot rows are excluded.

### AC-005-02 — Independent economic components

Estimate, Quote and Actual are represented independently. Actual Da confermare and Confermata are separately visible. No mandatory Estimate→Quote→Actual workflow is inferred by the report.

### AC-005-03 — Project buckets

Estimate/Quote without project or linked to Approved enter `primary`; Proposed enters `proposed`; Idea enters `idea`; Deferred/Rejected enter `excluded`. Every Actual attributed to the year remains `primary` regardless of later project stage. `potential` equals primary + proposed + idea and is marked non-official.

### AC-005-04 — Plafond

For each Plafond, allocated, consumed, residual and overrun reconcile exactly. Primary contribution equals allocated + overrun; consumption covered by allocation is not added again.

### AC-005-05 — Official basis

Given tenant basis Net or Gross, the primary displayed/comparison value uses that basis while the dataset retains exact Net, VAT and Gross. Changing the tenant setting does not modify a published BudgetVersion.

### AC-005-06 — Publish BudgetVersion

Given a tenant/year and declared snapshot scope, publishing captures normalized exact rows, source IDs/revision IDs, bucket/component grouping, Net/VAT/Gross totals, official basis, currency/language/timezone, format version, checksum, actor and timestamp. The published version is immutable.

### AC-005-07 — Manual historical version

Given incomplete historical evidence, a Manual draft may contain total-only, partial or full detail. Publishing validates available values and marks missing dimensions unavailable. It never invents rows or labels the version Approved without explicit evidence.

### AC-005-08 — Reference and comparison

A tenant/year may select one BudgetVersion as comparison reference. Current-versus-version and version-versus-version align stable dimensions, expose additions/removals/changes and exact variances. Cross-year comparison is allowed only for compatible dimensions. Selection never substitutes the current data.

### AC-005-09 — Scenario

A scenario belongs to one tenant, is persistent and shared, is visibly non-official and is included only when explicitly selected in scenario views.

### AC-005-10 — Empty tenant

When tenant/year has no matching rows, report pages remain accessible, mathematically defined KPIs show zero, charts/tables are empty, guidance links to prerequisites and no example data is invented.

### AC-005-11 — Single-tenant output scope

Every dashboard, drill-down, version, scenario, print, CSV and XLSX contains exactly one tenant. For print/export, actor explicitly selects `filtered` or `complete_report_year`; metadata identifies scope. Global overview/export contains approved operational fields only.

### AC-005-12 — Semantic equality

For `filtered`, screen/KPI/chart/print/CSV/XLSX use identical active filters and exact totals. For `complete_report_year`, print/CSV/XLSX use the same complete selected dataset. No output silently changes scope or formula.

### AC-005-13 — Shared kernel

Dashboard tenant, current Budget, reports, print/export and BudgetVersion capture consume the same `EconomicDataset`. No consumer recalculates bucket, Plafond or monetary totals.

## Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| FR-005-001 | The current Budget shall be one calculated tenant/year context and shall not persist an independently synchronized current total. | AC-005-01 |
| FR-005-002 | Current dashboard filters shall include tenant, year and optional cost center plus approved filters. | AC-005-01 |
| FR-005-003 | Each KPI shall expose formula, dataset type, official basis, empty-state behavior and drill-down. | AC-005-02, AC-005-10 |
| FR-005-010 | Current economic position shall not add project or contract values independently. | AC-005-01 |
| FR-005-011 | Available/current Budget formulas shall be implemented once by the shared economic kernel and use current Expense data only. | AC-005-01, AC-005-13 |
| FR-005-012 | Operational revisions, audit, deleted records, generation exceptions, scenarios and BudgetVersion rows shall be excluded from current totals. | AC-005-01 |
| FR-005-013 | Estimate, Quote and Actual shall remain independent dataset components; Actual confirmation states shall remain distinguishable. | AC-005-02 |
| FR-005-014 | Project buckets and potential shall follow Q-040 exactly. | AC-005-03 |
| FR-005-015 | Plafond contribution shall equal allocated + max(consumed − allocated, 0); covered consumption shall not be double counted. | AC-005-04 |
| FR-005-016 | Tenant official basis shall be Net or Gross, default Net; every dataset/version shall retain Net, VAT and Gross. | AC-005-05 |
| FR-005-020 | Table, KPI, chart, print, CSV and XLSX for one selected dataset/scope shall share one query/result contract. | AC-005-12, AC-005-13 |
| FR-005-021 | Filtered output shall preserve active filters, order, locale, currency, timezone, official basis and dataset identity. | AC-005-12 |
| FR-005-022 | Every economic output shall contain exactly one tenant. | AC-005-11 |
| FR-005-023 | Global overview/export shall contain approved operational tenant metadata only and no behavioral telemetry or cross-tenant economics. | AC-005-11 |
| FR-005-024 | Actor shall explicitly select filtered or complete selected report/year scope; complete scope ignores only transient narrowing filters and preserves tenant/report/year/dataset/authorization/order/locale/currency/timezone/basis. | AC-005-11, AC-005-12 |
| FR-005-025 | Output metadata and audit shall record selected scope; no output shall silently widen or narrow its dataset. | AC-005-11, AC-005-12 |
| FR-005-030 | Scenario view/manage abilities shall be permission-controlled. Scenarios shall be persistent, tenant-owned, shared, labelled non-official and excluded from official totals. | AC-005-09 |
| FR-005-040 | An authorized actor shall create a tenant/year BudgetVersion draft from current data or approved manual snapshot rows. | AC-005-06, AC-005-07 |
| FR-005-041 | Publishing shall freeze exact snapshot rows, dimensions, totals, official basis, metadata, source references, checksum, actor and timestamp. | AC-005-06 |
| FR-005-042 | Published BudgetVersion shall be immutable; correction shall create another version. | AC-005-06 |
| FR-005-043 | Manual BudgetVersion may be total-only, partial or full; missing dimensions shall be unavailable, not zero or invented. | AC-005-07 |
| FR-005-044 | No historical version shall be labelled Approved without explicit evidence. | AC-005-07 |
| FR-005-045 | Tenant/year may select one BudgetVersion reference; selection shall not mutate or substitute current Expense records. | AC-005-08 |
| FR-005-046 | System shall compare current-versus-version, version-versus-version and dimension-compatible cross-year sources without modifying them. | AC-005-08 |
| FR-005-047 | BudgetVersion view/create/publish/select-reference/compare operations shall be separately permission-controlled. | AC-005-06, AC-005-08 |
| FR-005-050 | Empty reports shall show valid zeros/empty datasets and guidance without fabricated values. | AC-005-10 |
| FR-005-060 | Report/export generation shall record safe actor, tenant, dataset type, selected output scope, filters, basis, output type and correlation metadata without payload contents. | AC-005-12 |
| FR-005-070 | Dashboard, current Budget, reports, print/export and version capture shall consume the shared economic kernel result; presentation layers shall not recalculate authoritative values. | AC-005-13 |

## Business invariants

| ID | Rule | Error | Test |
|---|---|---|---|
| INV-REP-001 | Current reports never double-count contracts/projects. | DomainConflict | TEST-005-001 |
| INV-REP-002 | Presentation forms share one semantic dataset for the selected scope. | DomainConflict | TEST-005-002 |
| INV-REP-003 | Metric formulas are server-side and decimal-safe. | DomainConflict | TEST-005-003 |
| INV-REP-004 | Permission and tenant filters limit every row/output. | Authorization/DomainConflict | TEST-005-004 |
| INV-REP-005 | No economic dataset contains multiple tenants. | DomainConflict | TEST-005-005 |
| INV-REP-006 | Output never silently changes filtered/complete scope. | DomainConflict | TEST-005-006 |
| INV-ECO-001 | One shared kernel owns current bucket, Plafond and monetary formulas. | DomainConflict | TEST-005-007 |
| INV-ECO-002 | Estimate, Quote and Actual remain independent components. | DomainConflict | TEST-005-008 |
| INV-PRJ-004 | Every Actual attributed to the year remains primary despite later project stage. | DomainConflict | TEST-005-009 |
| INV-PLF-003 | Plafond covered consumption is not counted twice. | DomainConflict | TEST-005-010 |
| INV-BAS-002 | Net/VAT/Gross remain exact regardless of selected official basis. | DomainConflict | TEST-005-011 |
| INV-BUD-001 | Published BudgetVersion is immutable. | DomainConflict | TEST-005-012 |
| INV-BUD-002 | BudgetVersion rows/totals/checksum are exact and internally consistent. | DomainConflict | TEST-005-013 |
| INV-BUD-003 | BudgetVersion never mutates or substitutes current Expense records. | DomainConflict | TEST-005-014 |
| INV-BUD-004 | Missing historical dimensions are never fabricated. | DomainConflict | TEST-005-015 |
| INV-SCN-001 | Scenario values never enter official current totals. | DomainConflict | TEST-005-016 |
| INV-EMPTY-001 | Empty state never invents economic values. | DomainConflict | TEST-005-017 |

## Success criteria

- filtered screen/KPI/chart/print/export totals are exactly equal;
- complete report/year print/CSV/XLSX totals equal the complete selected dataset;
- current Budget has no independently synchronized total;
- project buckets and Plafond reconcile exactly;
- changing official basis changes presentation/comparison basis without losing components;
- published BudgetVersion remains unchanged after current data/settings change;
- historical partial versions expose unavailable dimensions explicitly;
- comparison detects exact additions, removals and monetary variances;
- dashboard/Budget/report/output parity fixtures prove one shared kernel;
- cross-tenant and missing-permission tests cover reports, versions, scenarios, print and export.

## Out of scope

- cross-tenant economic analytics/comparisons;
- behavioral usage analytics;
- published-version in-place edits;
- private per-user scenarios;
- model revision storage used as BudgetVersion;
- presentation-side recalculation;
- contract/project values added directly to totals;
- synchronized persisted current Budget totals;
- mandatory Estimate→Quote→Actual workflow;
- audit export at launch.

## Clarification result

Q-019, Q-020, Q-027, Q-028, Q-033, Q-034, Q-036, Q-038, Q-040 and PD-BUD-001 are closed. Q-039 is superseded by Q-013. Existing Feature 005 plan, tasks, formulas, contracts and physical data model must be regenerated before implementation.
