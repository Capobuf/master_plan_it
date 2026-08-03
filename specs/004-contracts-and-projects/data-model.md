# Data model — Contracts and projects

## `projects`

`id`, unique nullable `legacy_id`, `title`, `cost_center_id` FK restrict, `stage`, nullable `deferred_to_year_id`, nullable `start_date/end_date`, `description`, `notes`, `lock_version`, timestamps/soft delete.

## `contracts`

`id`, unique nullable `legacy_id`, `description`, `vendor_id`, `cost_center_id`, nullable `project_id`, calculated `status`, `auto_renew`, calculated nullable `start_date/end_date/next_renewal_date`, `notes`, `attachment_path`, `lock_version`, timestamps/soft delete.

## `contract_terms`

| Column | Type | Rule |
|---|---|---|
| id/contract_id/position | bigint | PK, FK restrict, unique order |
| legacy_id | varchar(140) | scoped unique |
| from_date/to_date | date | from required; periods non-overlap |
| billing_cycle | varchar(16) | Monthly or Annual |
| entered_amount | decimal(19,6) | required |
| includes_vat | boolean | required |
| vat_rate | decimal(7,4) | strict VAT rule |
| amount_net/vat/gross | decimal(19,2) | calculated |
| monthly_amount_net | decimal(19,6) | calculated |
| is_auto_renewed | boolean | read-only through Action |
| renewed_from_term_id | bigint | self FK restrict, nullable |
| notes/attachment_path | text/varchar | optional |

## Expense context migration sequence

1. Create projects/contracts/terms.
2. Import or create identity maps.
3. Add nullable `project_id` and `contract_id` to `expenses` without foreign keys.
4. Backfill from `legacy_project_id`/`legacy_contract_id` through `legacy_id_map`.
5. Fail migration gate for unresolved non-null source references.
6. Add restrictive foreign keys and indexes.
7. Enforce mutual exclusion in `LinkExpenseContext` and database-compatible generated/check strategy only if verified on target MySQL version.
