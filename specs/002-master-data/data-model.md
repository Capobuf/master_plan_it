# Data model — Feature 002 Master data

Status: `PROPOSED TARGET`  
Shared conventions: `docs/replatform/data-model-overview.md`

## `planning_years`

- tenant ID;
- numeric year label;
- start/end dates;
- active state;
- `lock_version`;
- timestamps;
- unique `(tenant_id, year_label)`;
- index `(tenant_id, start_date, end_date)`.

Overlap is enforced by `SavePlanningYear` inside a transaction.

## `cost_centers`

- tenant ID;
- nullable parent cost-center ID;
- name;
- active state;
- `lock_version`;
- timestamps;
- unique `(tenant_id, name)`;
- index `(tenant_id, parent_id, active)`.

Parent must share tenant. Cycle and active-descendant checks are Action-owned. Launch lifecycle is deactivate/reactivate, not permanent delete.

## `vendors`

- tenant ID;
- name;
- optional VAT number, email, phone, address fields;
- active state;
- `lock_version`;
- timestamps;
- unique `(tenant_id, name)`;
- index `(tenant_id, active, name)`.

Referenced vendor is never permanently deleted at launch.

## Revision ownership

Cost centers and vendors implement Overtrue version snapshots and use `revision_batches`/`revision_batch_items`. Versioned fields exclude timestamps, lock version and technical package metadata. Restore produces a new current version via owning Action.

## Relations

All business FKs are restrictive. Inactive referenced values remain resolvable. Selectors default to active rows and explicitly include the currently referenced inactive row where needed.
