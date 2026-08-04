# Feature 005 — Reporting, budget versions, and analytics

Status: `CLARIFIED AND APPROVED; IMPLEMENTATION READY; IMPLEMENTATION NOT STARTED`
Logical owner: Product Owner with domain approval  
Actor: tenant user with explicit permission  
Dependencies: Feature 003, Feature 004 and Feature 007  
Additional decisions: Q-034, Q-036, Q-038, Q-040

## Problem

The product needs one rolling current Budget per tenant/year, immutable named BudgetVersion snapshots, shared what-if scenarios and consistent dashboards/reports/exports without treating contracts, operational revisions, deleted records or scenarios as implicit current economic sources.

The same rules must serve Budget, dashboard, reports, print and export without creating either a monolithic query object or a fragmented calculator for every KPI.

## Objective

Provide exact tenant-scoped current Budget, historical/manual evidence, project buckets, Plafond treatment, named version capture/comparison, shared scenarios, print/export and guided empty states from one shared server-side economic dataset contract.

## Clarifications

### Session 2026-08-04

- Q: Quali limiti di righe si applicano a CSV, XLSX e stampa? → A: Nessun limite applicativo. CSV e XLSX elaborano incrementalmente il dataset selezionato e gli output si affidano ai limiti effettivi del server; il benchmark verifica le prestazioni ma non determina soglie di rifiuto.
- Q: Come si evita che un errore del server produca un download CSV/XLSX parziale? → A: Il file viene scritto incrementalmente in un percorso temporaneo privato e reso scaricabile solo dopo il completamento; errore o interruzione impediscono il download, attivano la pulizia compensativa e invitano l'utente a restringere i filtri.
- Q: Quali soglie deve superare il benchmark con 10.000 righe? → A: Pagina/report p95 entro 2 secondi, CSV entro 10 secondi, XLSX entro 20 secondi, stampa entro 10 secondi, picco di memoria entro 128 MiB e massimo 5 query per richiesta.
- Q: Su quale ambiente sono vincolanti i tempi assoluti del benchmark? → A: Sul profilo di hosting reale verificato. In CI sono vincolanti parità, massimo 5 query e 128 MiB; i tempi sono registrati ma non fanno fallire test eseguiti su runner variabili.

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

### AC-005-14 — Uncapped output

Given an authorized filtered or complete report/year scope of any row count, CSV, XLSX and print apply no application-defined row threshold. CSV and XLSX consume the canonical ordered dataset incrementally without loading the complete row collection into memory. The performance benchmark records observed SQL count, memory and elapsed time but never changes, widens or truncates the requested dataset and never establishes a rejection threshold.

### AC-005-15 — Complete-or-error export delivery

Given an authorized CSV or XLSX request, generation writes incrementally to a request-scoped private temporary artifact. Response download begins only after the complete artifact has been finalized and verified. Query, writer, storage, timeout-detection or finalization failure exposes no partial download, records safe correlation metadata, removes every artifact it can remove immediately, surfaces and records any cleanup failure, and returns a stable error that suggests narrowing the selected filters without changing them automatically. Successful response completion removes the temporary artifact.

### AC-005-16 — Reference performance gate

Given the approved deterministic fixture of 10,000 current rows for one tenant/year on the verified reference environment, warmed benchmark runs pass only when page/report p95 is at most 2 seconds, CSV completion at most 10 seconds, XLSX completion at most 20 seconds, print rendering at most 10 seconds, peak PHP memory at most 128 MiB, and each measured request executes at most 5 SQL queries. Parity, scope and exact values must remain unchanged; larger datasets are not rejected by an application row threshold.

### AC-005-17 — Performance environments

Given the deterministic 10,000-row fixture, the verified target-hosting profile is the authoritative gate for the absolute time thresholds in AC-005-16. CI fails on parity, scope, ordering, more than 5 SQL queries or more than 128 MiB peak PHP memory; it records elapsed times as non-blocking evidence because runner capacity is variable. Release remains blocked until the target-hosting benchmark passes every absolute threshold.

### AC-005-18 — Accessible and compatible reporting

Given dashboard, current Budget, report, comparison, scenario, print and export-control surfaces, all functionality is keyboard reachable with visible focus, programmatic names, WCAG 2.2 AA contrast and identifiable errors. Every chart has an equivalent non-visual table or text alternative containing the same server-calculated values. These behaviors remain usable at 360, 768 and 1280 CSS-pixel viewports in the latest two stable Chrome, Edge and Firefox releases and the current stable Safari release.

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
| FR-005-026 | CSV, XLSX and print shall have no application-defined row limit and shall never truncate output. CSV and XLSX shall process the canonical ordered dataset incrementally. Benchmark results shall be verification evidence only and shall not create runtime row thresholds. | AC-005-12, AC-005-14 |
| FR-005-027 | CSV/XLSX generation shall write incrementally to a private request-scoped temporary artifact and shall start download only after complete finalization. Failure shall expose no partial artifact, preserve the requested scope, return a stable error with filter-narrowing guidance, and perform immediate cleanup; cleanup failure shall be surfaced and recorded. Successful delivery shall remove its temporary artifact. | AC-005-12, AC-005-15 |
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

## Non-functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| NFR-005-PERF-01 | On the verified target-hosting profile with the deterministic 10,000-row tenant/year fixture, page/report p95 shall be at most 2 seconds; complete CSV at most 10 seconds; complete XLSX at most 20 seconds; print rendering at most 10 seconds; peak PHP memory at most 128 MiB; and each measured request at most 5 SQL queries. CI shall enforce parity, scope, ordering, the memory ceiling and query ceiling while recording time as non-blocking evidence. Meeting this gate shall not introduce a runtime row cap, cache, materialized current total or semantic change. | AC-005-14, AC-005-15, AC-005-16, AC-005-17 |
| NFR-005-A11Y-01 | Reporting and analytics surfaces shall meet WCAG 2.2 level AA, including keyboard operation, visible focus, programmatic labels, contrast, error identification and equivalent non-visual alternatives for every chart. | AC-005-18 |
| NFR-005-COMPAT-01 | Reporting and analytics surfaces shall remain usable at 360, 768 and 1280 CSS-pixel viewports in the latest two stable Chrome, Edge and Firefox releases and current stable Safari. | AC-005-18 |

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
| INV-ECO-003 | Every Actual attributed to the year remains primary despite later project stage. | DomainConflict | TEST-005-009 |
| INV-PLF-003 | Plafond covered consumption is not counted twice. | DomainConflict | TEST-005-010 |
| INV-BAS-002 | Net/VAT/Gross remain exact regardless of selected official basis. | DomainConflict | TEST-005-011 |
| INV-BUD-001 | Published BudgetVersion is immutable. | DomainConflict | TEST-005-012 |
| INV-BUD-002 | BudgetVersion rows/totals/checksum are exact and internally consistent. | DomainConflict | TEST-005-013 |
| INV-BUD-003 | BudgetVersion never mutates or substitutes current Expense records. | DomainConflict | TEST-005-014 |
| INV-BUD-004 | Missing historical dimensions are never fabricated. | DomainConflict | TEST-005-015 |
| INV-SCN-001 | Scenario values never enter official current totals. | DomainConflict | TEST-005-016 |
| INV-EMPTY-001 | Empty state never invents economic values. | DomainConflict | TEST-005-017 |
| INV-REP-007 | Output row count equals the complete authorized selected dataset; no application threshold truncates or rejects it, and CSV/XLSX processing does not materialize the complete row collection. | DomainConflict | TEST-005-018 |
| INV-REP-008 | CSV/XLSX delivery is complete-or-error: no response exposes a partial artifact, and temporary output cannot become a permanent public or untracked file. | DomainConflict | TEST-005-019 |
| INV-REP-009 | The 10,000-row reference benchmark satisfies NFR-005-PERF-01 without changing dataset scope, values, order or uncapped output behavior. | DomainConflict | TEST-005-020 |
| INV-UX-005 | Every reporting chart has a keyboard-reachable WCAG 2.2 AA non-visual equivalent containing the same server-calculated values. | DomainConflict | TEST-005-021 |
| INV-COMPAT-005 | Reporting controls, tables and chart alternatives remain usable in the approved browser and viewport matrix. | DomainConflict | TEST-005-022 |

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
- output parity includes a dataset larger than the benchmark reference without application-defined truncation or row-limit rejection.
- the deterministic 10,000-row benchmark satisfies every NFR-005-PERF-01 time, memory and query threshold.

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
- application-defined CSV, XLSX or print row caps.

## Clarification result

Q-019, Q-020, Q-027, Q-028, Q-033, Q-034, Q-036, Q-038, Q-040 and PD-BUD-001 remain closed; Q-039 is superseded by Q-013. All four decisions in the 2026-08-04 clarification session and the approved cross-feature accessibility/browser standards are encoded and propagated through the specification, plan, contracts, tasks, checklists, and cross-feature registries.
