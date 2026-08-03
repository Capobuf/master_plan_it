# Data model — Contracts and projects

## Tenant ownership

Projects and contracts belong to one tenant. Terms inherit the contract tenant. Cost centers, vendors, projects, generated expenses, renewals, and source identities must remain in the same tenant.

## `projects`

`id`, required `tenant_id`, unique nullable legacy ID scoped to tenant, title, same-tenant cost-center FK, stage, nullable same-tenant deferred-year FK, nullable dates, description, notes, lock version, timestamps/soft delete.

## `contracts`

`id`, required `tenant_id`, unique nullable legacy ID scoped to tenant, description, same-tenant vendor and cost-center FKs, nullable same-tenant project FK, calculated status, auto-renew flag, calculated dates, notes, attachment reference, lock version, timestamps/soft delete.

## `contract_terms`

| Column | Type | Rule |
|---|---|---|
| id/contract_id/position | bigint | PK, FK restrict, unique order inside contract |
| legacy_id | varchar(140) | unique inside tenant/source scope |
| from_date/to_date | date | from required; periods non-overlap |
| billing_cycle | varchar(16) | Monthly or Annual |
| entered_amount | decimal(19,6) | required |
| includes_vat | boolean | required |
| vat_rate | decimal(7,4) | strict VAT rule |
| amount_net/vat/gross | decimal(19,2) | calculated |
| monthly_amount_net | decimal(19,6) | calculated |
| is_auto_renewed | boolean | read-only through Action |
| renewed_from_term_id | bigint | same-tenant self FK restrict, nullable |
| notes/attachment reference | text/reference | optional and tenant-scoped |

## Expense context migration sequence

1. Create tenant-owned projects, contracts, and terms inside the selected tenant.
2. Import or create identity maps scoped to that tenant.
3. Add nullable project/contract references to tenant-owned expenses.
4. Backfill through same-tenant identity maps.
5. Block unresolved or cross-tenant source references.
6. Add restrictive foreign keys and indexes.
7. Enforce mutual exclusion and same-tenant ownership in the domain Action.
