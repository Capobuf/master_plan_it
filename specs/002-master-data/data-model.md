# Data model — Feature 002 Master data

Status: `PROPOSED TARGET`  
Shared conventions: `docs/replatform/data-model-overview.md`

## `planning_years`

- tenant ID;
- numeric calendar-year label;
- active state;
- `lock_version`;
- timestamps;
- unique `(tenant_id, year_label)`;
- index `(tenant_id, active, year_label)`.

January 1 and December 31 are derived from `year_label` and are not editable columns. Planning years support create/deactivate/reactivate only, remain historically resolvable, are never permanently deleted and have no operational revision rows.

## `cost_centers`

- tenant ID;
- nullable parent cost-center ID;
- name;
- active state;
- `lock_version`;
- timestamps;
- unique `(tenant_id, name)`;
- index `(tenant_id, parent_id, active)`.

Parent must share tenant. Cycle, active-descendant and depth checks are Action-owned. Root is level one and level four is rejected. Siblings are read by case-insensitive ascending name then ID. Delete is allowed only with no current/historical domain reference and no descendant; deactivation/reactivation remains available.

## `vendors`

- tenant ID;
- name;
- optional VAT number, email, phone, address fields;
- active state;
- `lock_version`;
- timestamps;
- unique `(tenant_id, name)`;
- index `(tenant_id, active, name)`.

Delete is allowed only when no current or historical domain record references the vendor; otherwise deactivate/reactivate preserves historical readability.

## Revision ownership

Cost centers and vendors implement Overtrue version snapshots and use `revision_batches`/`revision_batch_items`. Versioned fields exclude timestamps, lock version and technical package metadata. Restore produces a new current version via owning Action.

## Relations

All business FKs are restrictive. Inactive referenced values remain resolvable. Selectors default to active rows and explicitly include the currently referenced inactive row where needed.
