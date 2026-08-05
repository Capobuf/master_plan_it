# Contract — Canonical economic dataset

Feature: `005-reporting-and-analytics`  
Status: `PROPOSED TARGET — PLAN COMPLETE`  
Purpose: current Budget, dashboard, reports, print, export and current-side comparison.

## Input

`EconomicScope` contains:

- authorized tenant and planning year;
- dataset identity `current`;
- official basis `net|gross`;
- typed filters, grouping and order;
- output scope `filtered|complete_report_year`;
- detail mode `none|page|all`;
- page/per-page only for `page`.

Tenant and authorization are resolved before query execution. User-supplied tenant override is never accepted.

## Current source

Only current non-deleted Expense and ExpenseRow records. Manual rows without project/contract context are a complete valid current dataset and do not require Feature 004. Project/contract/master data are optional read context; T005-027 adds project-stage projection after Feature 004. Operational versions, audit, deleted rows, generation exceptions, scenarios and BudgetVersion rows are excluded.

## Output

`EconomicDataset` contains:

- tenant/year/dataset/output metadata;
- normalized effective filters/grouping/order;
- official basis and exact Net/VAT/Gross components;
- `EconomicSummary`;
- grouped summaries;
- rows according to detail mode;
- pagination metadata when applicable.

Every monetary value is a normalized decimal string. Chart adapters may create non-authoritative numeric copies only after server calculation.

## Formula contract

`EconomicEngine` alone implements:

- independent Estimate/Quote/Actual components;
- Actual ToConfirm/Confirmed split;
- project `primary|proposed|idea|excluded` buckets;
- all Actual for year in primary;
- non-official potential;
- Extra;
- Plafond allocated/consumed/residual/overrun and no-double-count contribution;
- Net/VAT/Gross totals and grouping reconciliation.

No consumer or SQL expression duplicates these rules.

## Output scopes

### `filtered`

Uses all authorized active filters displayed by the screen.

### `complete_report_year`

Keeps tenant, report/dataset, year, authorization, ordering contract, language, timezone, currency and basis; removes only transient narrowing filters. The actor selects it explicitly.

Metadata and audit record the scope. No output silently changes scope.

## Empty result

Returns exact defined zero summaries, empty groups/rows and prerequisite guidance codes. It never invents example rows.

## Performance

Dashboard uses detail none; reports use page; capture/export use all. The contract permits lazy row iteration only when summary, ordering, checksum and transaction snapshot remain identical.

## Authorization

Separate permissions cover view, filtered export, complete export and print. Every path includes one tenant. Other-tenant IDs return safe not-found/denial.

## Test contract

1. every rule has a pure engine fixture;
2. query excludes every non-current source;
3. tenant and permission isolation;
4. summary equals grouped/row reconciliation;
5. dashboard/report/print/CSV/XLSX/version capture parity;
6. filtered and complete scopes are distinct and explicit;
7. empty result defined;
8. 10,000-row target-host time, query, memory and EXPLAIN gates pass; CI blocks query/memory/parity regressions and records time;
9. no Eloquent model escapes as public dataset contract.
10. a larger output is complete and exact without row-limit rejection or full collection materialization.
