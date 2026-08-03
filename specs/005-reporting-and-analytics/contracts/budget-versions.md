# Contract — Named budget versions

Feature: `005-reporting-and-analytics`  
Status: `CLARIFIED — PLAN REQUIRED`  
Purpose: draft, publish, immutable snapshot, comparison, and output semantics.

## Dataset identity

A named budget version belongs to exactly one tenant and one planning year. It is not an operational model revision and is not the rolling current budget.

Supported source modes:

- `CurrentSnapshot`: capture the approved current economic dataset and filters;
- `Manual`: create/edit snapshot rows inside a Draft without changing Expense data.

Supported kinds:

- `Manual`;
- `Approved`;
- `Snapshot`.

`kind` describes business intent; `source mode` describes how Draft rows were produced.

## Create draft

Input includes actor, tenant context, year, name, optional description, kind, source mode, normalized filters, and expected source dataset version where required.

The Action:

1. authorizes `budget-version.create`;
2. validates tenant/year and name uniqueness;
3. resolves one exact source dataset or empty valid dataset;
4. copies normalized rows and exact monetary values into Draft snapshot rows;
5. records source record/revision IDs where available;
6. calculates exact draft totals and checksum candidate;
7. writes one transaction and audit event.

## Manual draft editing

Only Draft versions may be edited. Manual row editing uses typed fields, decimal strings, tenant-owned references/labels, and the same monetary validation used by approved version calculations. It never creates or updates Expense records.

A current-snapshot draft may be refreshed only through an explicit `Rebuild draft from current` action that replaces Draft snapshot rows after confirmation. Published versions cannot be rebuilt.

## Publish

Publishing:

1. authorizes publish/create ability as finalized in the permission catalogue;
2. locks the Draft;
3. validates every row, grouping, total, source metadata, tenant/year, and checksum;
4. captures currency, language, timezone, filters, format version, actor, and timestamp;
5. changes status to Published;
6. prevents every later content mutation.

If validation fails, nothing is published and the Draft remains unchanged.

## Immutability

A Published version cannot be edited, rebuilt, restored through model revision tooling, or have snapshot rows changed. Correcting it requires creating another version, optionally by duplicating the old snapshot into a new Draft.

Deleting a Published version is not part of the launch contract. Optional archival may hide it from default selectors without altering content and must be decided in `/speckit.plan` only if needed for ordinary use.

## Comparison

Supported comparisons:

- current rolling dataset versus one Published version;
- one Published version versus another Published version;
- current Actual subset versus one Published Approved version.

Comparison inputs include tenant, left dataset identity, right dataset identity, approved filters/grouping, locale, and currency context.

The result aligns stable dimensions and returns exact left/right values, absolute difference, and percentage only where denominator behavior is defined. Added/removed rows are explicit; missing values are never silently treated as another dimension.

## Output

Screen, KPI, table, chart, print, CSV, and XLSX consume the same comparison or version dataset contract. Every output visibly identifies:

- tenant;
- year;
- version name/kind/status;
- published timestamp and author;
- selected comparison side(s);
- filters;
- currency/locale/timezone;
- checksum/format version where appropriate.

## Authorization and tenant isolation

Separate abilities cover view, create Draft, edit Draft, publish, duplicate, compare, print, and export. Every version/snapshot row belongs to the current tenant. Other-tenant IDs fail without existence leakage.

## Audit

Audit create, draft rebuild, manual draft edit, publish, duplicate, and optional archive. Do not log entire snapshot payloads; store IDs, counts, exact totals, checksum, filters summary, actor, and correlation ID.

## Test contract

1. current snapshot captures exact approved rows/totals;
2. manual Draft never mutates Expense data;
3. Published content is immutable through UI, Actions, direct IDs, and model-version restore paths;
4. source Expense changes do not change a Published version;
5. current-versus-version and version-versus-version return exact deterministic differences;
6. screen/print/CSV/XLSX equality;
7. empty dataset publishes valid empty snapshot with defined zero totals;
8. tenant and permission allow/deny coverage;
9. failed publish rolls back completely;
10. checksum changes on any Draft content change and matches the Published snapshot.
