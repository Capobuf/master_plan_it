# Data model — Master data

## Tenant ownership

`planning_years`, `cost_centers`, and `vendors` are tenant-owned. Each table has required tenant ownership. Uniqueness and tree relationships are scoped to the same tenant. Cross-tenant parent or business references are invalid.

## `planning_years`

Required semantic fields: tenant, numeric year, start date, end date, active flag, lock version, timestamps. Periods may not overlap within the same tenant. Only Administrator creates or configures years.

## `cost_centers`

Required semantic fields: tenant, unique name within tenant, optional same-tenant parent, active state, lock version, timestamps. Parent graph is acyclic. Editor may create and update cost centers in the assigned tenant.

## `vendors`

Required semantic fields: tenant, unique name within tenant, optional VAT/contact data, active state, lock version, timestamps. Historical references remain readable after deactivation; inactive vendors are excluded from new selection. Editor may create and update vendors in the assigned tenant.

## Audit and migration

State and structural changes record actor and tenant. Imported legacy IDs are reconciled only inside the selected migration tenant. No global mutable master-data catalogue is approved.
