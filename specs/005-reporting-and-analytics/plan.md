# Implementation plan — Feature 005 Reporting, BudgetVersion and analytics

Status: `PLAN COMPLETE; INTEGRATED ANALYSIS PASSED; IMPLEMENTATION READY; IMPLEMENTATION NOT STARTED`
Dependencies: Features 001, 003 and 007 for the manual-Expense current Budget; Feature 004 only for later project-stage enrichment; shared economic kernel

## Summary

Implement one current rolling Budget dataset, tenant dashboard, report/drill-down, scenarios, immutable named BudgetVersion snapshots, comparisons, Blade print view and CSV/OpenSpout XLSX output. Every current formula is owned by the shared Economics query/engine. No persisted current Budget total, server PDF package or presentation-layer recalculation.

## TailAdmin UI standard

Any Blade/TailAdmin dashboard, Budget or report surface follows [`docs/replatform/tailadmin-ui-standard.md`](../../docs/replatform/tailadmin-ui-standard.md): search official TailAdmin first, choose the best native fit, and document exceptions before implementation.

## Constitution check

Passes C-02, C-03, C-04, C-07, C-08, C-10, C-11 and C-12. Current data, operational revisions, scenarios and BudgetVersion snapshots remain distinct.

## Target files

### Economic kernel

Exactly the six initial files defined in `economic-engine-architecture.md`:

- four immutable DTOs;
- `EconomicDatasetQuery`;
- `EconomicEngine`.

Shared Money/VAT/allocation services are reused, not copied.

### Current Budget/dashboard/report

- `AnnualBudget` context model and migration;
- `TenantDashboardQuery` composes one economic dataset plus non-economic alerts;
- operational Blade current-Budget and economic-report pages rendered within the shared TailAdmin tenant layout;
- `EconomicReportFilterData` and typed grouping/order enums;
- ApexCharts adapter that renders server-calculated number copies and owns explicit initialization/cleanup through the TailAdmin/Alpine lifecycle.

### Scenarios

- `Scenario`, `ScenarioRow` models;
- create/update/archive/delete Actions;
- `ScenarioDatasetQuery` returns explicit alternative dataset DTO;
- no `ScenarioEconomicEngine`.

### BudgetVersion

- `BudgetVersion`, `BudgetVersionRow` models;
- `CreateBudgetVersionDraft`, `UpdateManualBudgetVersionDraft`, `RebuildBudgetVersionDraft`, `PublishBudgetVersion`, `DuplicateBudgetVersion`, `SelectBudgetReference`;
- `BudgetVersionDatasetQuery`;
- `CompareBudgetSources` consumes already-resolved source DTOs.

### Output

- `EconomicDatasetCsvExporter` using incremental writes to a request-scoped private temporary artifact;
- `EconomicDatasetXlsxExporter` using OpenSpout 4.32 writer-only;
- dedicated Blade economic print page and print CSS;
- export/print controllers and ordinary Laravel requests accepting the same typed dataset request.

No `ReportPdfRenderer` interface at launch because there is no implementation or server-PDF requirement.

## Current dataset flow

1. Policy validates tenant, report and requested output scope.
2. `EconomicScope` is built from tenant/year/basis/filters/groups/order/detail.
3. `EconomicDatasetQuery` projects current non-deleted rows and invokes `EconomicEngine` once.
4. Engine classifies components/project buckets/Plafond and returns exact summary/groups/rows.
5. UI, charts, print and exports consume this DTO unchanged.

`filtered` preserves active narrowing filters. `complete_report_year` preserves tenant, dataset, report, year, authorization, order, language, timezone, currency and basis, but removes transient narrowing filters. The selected scope is explicit in UI and output metadata.

## Kernel formulas

- types remain Estimate/Quote/Actual;
- Actual ToConfirm and Confirmed separately accumulated;
- project buckets follow Q-040;
- all Actual for year remain primary;
- potential = primary + proposed + idea, labelled non-official;
- Plafond allocated/consumed/residual/overrun and primary contribution allocated + overrun;
- Net/VAT/Gross always retained; official basis selects primary display/comparison values;
- no contract/project independent amount.

The Engine has private classification/accumulation methods initially. Extraction requires independent invariants/reuse/dependency/change reason.

## BudgetVersion transaction

`CreateBudgetVersionDraft` creates an empty/manual draft or captures current dataset. Current capture runs inside MySQL `REPEATABLE READ`:

1. begin transaction and set isolation before first read;
2. lock annual Budget context for reference/name coordination, not all expense rows;
3. establish snapshot with the economic query;
4. write version header and rows;
5. calculate canonical checksum over normalized metadata/rows/summary;
6. audit and commit.

`PublishBudgetVersion` locks draft, validates rows, availability, totals and checksum, captures basis/locale/timezone and marks Published. Policies and Actions prohibit later content mutation/deletion. No package model-revision restore path applies.

Manual versions store dimension availability explicitly. Total-only/partial data never receives invented labels/zero rows or Approved kind without explicit choice/evidence.

## Comparison

Define `BudgetSource` DTO variants current, version and scenario. Resolver Queries return a common comparison dataset containing stable row keys, labels, dimension availability and exact values.

`CompareBudgetSources`:

- requires same tenant;
- permits compatible years/dimensions;
- returns unchanged/added/removed/changed rows;
- calculates absolute variance and percentage only for defined non-zero denominator;
- never mutates sources or re-runs current formulas for snapshot rows.

## Output design

CSV uses UTF-8, declared separator/newline and decimal strings. XLSX contains presentation values only; formulas are prohibited. Both exporters receive canonical ordered DTO rows incrementally and never query Eloquent or materialize the complete row collection.

Print HTML renders the same dataset and includes tenant/report/year/scope/basis/filter/version metadata. Browser print/Save as PDF is launch PDF path.

CSV, XLSX and print have no application-defined row cap and never truncate the selected authorized dataset. CSV/XLSX write to a private request-scoped temporary artifact and begin download only after successful finalization. Failure exposes no partial response, preserves filters/scope, performs immediate cleanup, surfaces/records cleanup failure and suggests narrowing filters without doing so automatically. A successful response removes its artifact.

## Performance

Reference 10,000 current rows/tenant/year:

- scalar projection and appropriate composite indexes;
- one engine pass;
- dashboard detail none;
- page detail paginated;
- complete exports/version capture may use lazy iteration only if it preserves transaction snapshot and checksum order;
- no persistent current totals/cache;
- verified target hosting must pass page/report p95 ≤2 s, CSV ≤10 s, XLSX ≤20 s, print ≤10 s, peak PHP memory ≤128 MiB and ≤5 SQL queries per measured request;
- CI enforces parity/scope/order, ≤128 MiB and ≤5 queries, while elapsed times are recorded as non-blocking evidence;
- no benchmark result introduces a runtime row cap, cache, materialized total or semantic change.

## Tests

### Engine/query

Table fixtures for every type, confirmation state, project stage, Extra, Plafond, basis and rounding. Query tests prove current/deleted/revision/audit/scenario/version exclusion and tenant isolation.

### Version/scenario/comparison

- immutable publish;
- source changes do not change version;
- manual total-only/partial/full availability;
- checksum determinism;
- current/version/scenario isolation;
- reference selection;
- exact added/removed/changed comparison;
- failed publish rollback.

### Output parity

Same fixture asserts screen dataset, KPI, chart payload, print view, CSV, XLSX and captured version values. Filtered and complete scopes are tested separately, including output larger than the 10,000-row reference without row-limit rejection. Export failure proves complete-or-error temporary-file cleanup. XLSX round-trip reads are test-only if a reader dependency is already present; otherwise inspect generated cell XML/values without adding production reader.

Dusk covers chart lifecycle, explicit scope action, browser print smoke, keyboard/focus/error behavior, equivalent non-visual chart tables and the 360/768/1280 CSS-pixel matrix in the approved Chrome/Edge/Firefox/Safari versions. WCAG 2.2 AA is the acceptance standard.

## Sequence

1. Slice 2 pure economic DTO/engine tests over manual Expense inputs, then the current manual-Expense query and indexes;
2. operational current Budget filters/KPI/table/ApexCharts using exactly that dataset, without AnnualBudget/BudgetVersion or Feature 004 as prerequisites;
3. Slice 3 parity/authorization/isolation/responsive/keyboard/focus/loading/error/browser and representative-performance hardening;
4. Feature 004 project-stage query enrichment without creating a new monetary source;
5. scenarios and BudgetVersion schema/Actions/checksum;
6. source resolver/comparison, then print/CSV/XLSX adapters and their full gates.

## Post-design check

Pass. One semantic kernel is shared without placing persistence, UI, exports or snapshot lifecycle in `EconomicEngine`.
