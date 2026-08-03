# Implementation plan — Feature 005 Reporting, BudgetVersion and analytics

Status: `READY FOR /speckit.tasks AFTER PLAN REVIEW`  
Dependencies: Features 001–004 and 007; shared economic kernel

## Summary

Implement one current rolling Budget dataset, tenant dashboard, report/drill-down, scenarios, immutable named BudgetVersion snapshots, comparisons, dedicated Blade print and CSV/OpenSpout XLSX output. Every current formula is owned by the shared Economics query/engine. No persisted current Budget total, server PDF package or presentation-layer recalculation.

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
- `CurrentBudgetPage` and `EconomicReportPage` Filament Pages;
- `EconomicReportFilterData` and typed grouping/order enums;
- Chart.js adapter that renders server-calculated number copies and destroys/recreates charts on Livewire lifecycle.

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

- `EconomicDatasetCsvExporter` using native streamed response;
- `EconomicDatasetXlsxExporter` using OpenSpout 4.32 writer-only;
- dedicated `resources/views/reports/economic-print.blade.php` and print CSS;
- export/print controllers or Filament Actions accepting the same typed dataset request.

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

CSV uses UTF-8, declared separator/newline and decimal strings. XLSX contains presentation values only; formulas are prohibited. OpenSpout receives DTO rows via iterator and does not query Eloquent.

Print HTML renders the same dataset and includes tenant/report/year/scope/basis/filter/version metadata. Browser print/Save as PDF is launch PDF path.

Output limits are explicit per format and fail before partial output; exact thresholds are established by benchmark task, not guessed in UI.

## Performance

Reference 10,000 current rows/tenant/year:

- scalar projection and appropriate composite indexes;
- one engine pass;
- dashboard detail none;
- page detail paginated;
- complete exports/version capture may use lazy iteration only if it preserves transaction snapshot and checksum order;
- no persistent current totals/cache;
- benchmark records SQL count, memory, p95 and EXPLAIN before optimization.

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

Same fixture asserts screen dataset, KPI, chart payload, print view, CSV, XLSX and captured version values. Filtered and complete scopes tested separately. XLSX round-trip reads are test-only if a reader dependency is already present; otherwise inspect generated cell XML/values without adding production reader.

Dusk only for chart lifecycle, explicit scope action and browser print smoke.

## Sequence

1. economic DTOs/engine pure tests;
2. current query integration/index benchmark;
3. annual Budget context and current pages/dashboard;
4. scenarios;
5. BudgetVersion schema/Actions/checksum;
6. source resolver/comparison;
7. print/CSV/XLSX adapters;
8. parity/performance/tenant/browser gates.

## Post-design check

Pass. One semantic kernel is shared without placing persistence, UI, exports or snapshot lifecycle in `EconomicEngine`.
