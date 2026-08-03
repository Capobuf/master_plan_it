# Data model — Feature 005 Reporting, scenarios and BudgetVersion

Status: `PROPOSED TARGET`  
Shared conventions: `docs/replatform/data-model-overview.md`

## Current reporting

No authoritative current monetary table is created. `EconomicDatasetQuery` reads current non-deleted Expense rows and returns DTOs. Operational versions, audit, deleted rows, generation exceptions, scenarios and BudgetVersion rows are excluded.

## `annual_budgets`

- tenant ID;
- planning year ID;
- nullable selected reference BudgetVersion ID;
- `lock_version`, timestamps;
- unique `(tenant_id,planning_year_id)`.

Context only; no total columns.

## `budget_versions`

- annual budget, tenant and planning-year IDs;
- tenant/year normalized unique name;
- optional description;
- kind `manual|approved|snapshot`;
- status `draft|published`;
- source mode `manual|current_snapshot`;
- official basis `net|gross`;
- captured currency/language/timezone;
- normalized dataset/filter/output-scope JSON;
- dimension-availability JSON;
- exact summary JSON containing decimal strings;
- format version and SHA-256 checksum;
- created/published actors and timestamps;
- draft `lock_version`.

Published content is immutable by Policy/Action. Reference selection is stored on annual Budget, not in the version.

## `budget_version_rows`

- tenant and version IDs;
- stable row key and sequence;
- optional source Expense/row and source operational-version IDs;
- origin `current|manual`;
- captured type, confirmation state, bucket and funding flags;
- captured year/cost-center/vendor/project/contract/Plafond labels and nullable source IDs;
- Net/VAT/Gross at 2 decimals;
- optional allocation/dimension metadata;
- timestamps only if required for creation; no later updates on Published rows.

Labels are captured values and remain readable if current master data changes/deactivates. Snapshot rows never join current totals.

No separate totals table: exact summary JSON is reproducible and checksum-protected. Add a relational totals table only after a measured query need and plan amendment.

## `scenarios`

- tenant/year IDs;
- name/description;
- state `active|archived`;
- assumption/filter metadata;
- created/updated actors;
- `lock_version`, timestamps.

## `scenario_rows`

- tenant/scenario IDs;
- optional source current row ID;
- stable row key;
- explicit operation/origin;
- captured typed dimensions and labels;
- Net/VAT/Gross;
- timestamps.

Scenario rows never FK-replace or mutate Expense rows.

## Comparison

Computed DTO only; no comparison persistence. Source identity, dimension availability and stable row keys are required to align current/version/scenario datasets.

## Checksum

Canonical checksum input is versioned, sorted and normalized:

1. format version and captured context;
2. normalized filters/scope/basis/dimension availability;
3. ordered rows with decimal strings and labels;
4. exact summary.

JSON key order and decimal formatting are deterministic. Checksum tests use golden fixtures.

## Indexes

- annual Budget unique tenant/year;
- versions `(tenant_id,planning_year_id,status,created_at)` and normalized name unique;
- version rows `(budget_version_id,position)` and stable row key;
- scenarios `(tenant_id,planning_year_id,state)`;
- scenario rows `(scenario_id,stable_row_key)`.

Draft deletion may cascade snapshot rows. Published deletion is unavailable. Scenario deletion/archive follows permission and reference rules.
