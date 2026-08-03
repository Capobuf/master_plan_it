# Data model — Reporting, scenarios, and budget versions

Status: `CLARIFIED LOGICAL MODEL — PHYSICAL PLAN REQUIRED`

## Current reporting

Current reporting creates no authoritative economic persistence. Query-time datasets read current non-deleted tenant-owned Expense rows and apply the same server-side exact calculations used by screens, print, CSV, and XLSX.

Current datasets never read operational revisions, audit, deleted records, scenarios, generation exceptions, or budget-version snapshot rows.

## `scenarios`

Logical fields:

- `id`, `tenant_id`;
- name and optional description;
- status: active/archived;
- normalized filters and scenario assumptions;
- created/updated actor and timestamps;
- `lock_version`.

Scenario detail rows use explicit typed dimensions and decimal monetary strings/columns defined in `/speckit.plan`. They never foreign-key-replace or mutate current Expense rows. Scenarios are tenant-shared and permission-controlled.

## `budget_versions`

| Field | Requirement |
|---|---|
| `id` | stable version identity |
| `tenant_id` | required tenant ownership |
| `planning_year_id` | required same-tenant year |
| `name` | tenant/year unique according to the plan's case/normalization rule |
| `description` | optional |
| `kind` | `Manual`, `Approved`, or `Snapshot` |
| `status` | `Draft` or `Published` |
| `format_version` | required schema version |
| `filters` | normalized typed JSON or relational filter representation |
| `currency/language/timezone` | captured output context |
| `created_by/created_at` | author metadata |
| `published_by/published_at` | required when Published |
| `checksum` | exact snapshot integrity |
| `lock_version` | draft concurrency only |

A Published row is immutable. It may be archived from ordinary selection only if the product plan defines archival without changing snapshot content.

## `budget_version_rows`

Snapshot rows are application-owned, not package model revisions.

Required semantic fields:

- budget version ID and tenant ID;
- stable row key and ordered position;
- grouping dimensions such as year/cost center/category/phase/vendor/project/contract where selected by the approved dataset;
- display labels captured for historical readability;
- source Expense/row IDs and source revision IDs when derived from current data;
- exact `DECIMAL(19,6)` source/intermediate fields where required;
- exact `DECIMAL(19,2)` business-result fields;
- row origin: current snapshot or manual draft input;
- normalized metadata needed for comparison.

Published snapshot rows are immutable and excluded from current totals.

## `budget_version_totals`

Persist only if `/speckit.plan` proves that stored totals are needed for integrity/performance. When persisted, every total is reproducible from snapshot rows, identified by stable grouping dimensions, and checked by checksum. Stored totals never replace row-level source of the version dataset.

## Comparison result

Comparison is a computed typed result, not authoritative persistence. It aligns two selected datasets by approved stable dimensions and returns:

- unchanged rows;
- added/removed rows;
- changed exact amounts;
- absolute and percentage variance where denominator semantics are defined;
- exact totals for each side and difference.

## Tenant scope

Every current report, scenario, budget version, snapshot row, comparison, print, and export requires exactly one tenant. Administrator global overview uses a separate operational dataset only.

## Empty state

An empty current or selected dataset is valid. The result contract returns zero only for mathematically defined aggregate values, empty row collections, explanatory missing-prerequisite codes, and available navigation actions. No sample economic row is persisted or returned as data.

## Audit

Report reads are not business mutations. Creating/publishing/archiving a scenario or budget version is audited. Export generation records actor, tenant, selected dataset identity, filters, output type, and correlation ID without logging the exported payload.
