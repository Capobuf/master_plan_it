# Data model — Expense domain

## `expenses`

| Column | MySQL | Nullable/default | Constraint/index | Legacy source |
|---|---|---|---|---|
| id | bigint unsigned | no | PK | generated |
| legacy_id | varchar(140) | yes | unique | MPIT Expense.name |
| kind | varchar(16) | no | enum check in application/index | expense_kind |
| title | varchar(255) | no | index | expense_title |
| planning_year_id | bigint unsigned | no | FK restrict/index | year |
| cost_center_id | bigint unsigned | no | FK restrict/index | cost_center |
| uses_plafond | boolean | no/false | — | uses_plafond |
| plafond_expense_id | bigint unsigned | yes | self FK restrict/index | plafond_expense |
| is_extra | boolean | no/false | — | is_extra |
| legacy_project_id | varchar(140) | yes | index | project |
| legacy_contract_id | varchar(140) | yes | index | contract |
| notes | text | yes | — | notes |
| lock_version | int unsigned | no/0 | — | target |
| created_at/updated_at/deleted_at | timestamp | deleted nullable | index deleted | audit metadata |

## `expense_rows`

| Column | MySQL | Nullable/default | Constraint/index | Legacy source |
|---|---|---|---|---|
| id | bigint unsigned | no | PK | generated |
| legacy_id | varchar(140) | yes | unique | child name |
| expense_id | bigint unsigned | no | FK restrict/index | parent |
| position | int unsigned | no | unique expense+position | idx |
| state | varchar(16) | no/Active | index | row_state |
| phase | varchar(16) | Plafond may null | index | row_phase |
| vendor_id | bigint unsigned | Ordinary required | FK restrict/index | vendor |
| description | varchar(255) | no | — | row_description |
| quantity | decimal(19,6) | no/1 | — | qty |
| unit_price | decimal(19,6) | yes | — | unit_price |
| entered_amount | decimal(19,6) | no | — | amount |
| amount_includes_vat | boolean | no/false | — | same |
| vat_rate | decimal(7,4) | yes | — | vat_rate |
| amount_net/vat/gross | decimal(19,2) | no/0 | — | computed fields |
| spend_date | date | yes | index | spend_date |
| start_date/end_date | date | yes | indexes | period fields |
| distribution | varchar(8) | yes | — | all/start/end |
| replaces_row_id | bigint unsigned | yes | self FK restrict/index | replaces_row_name |
| external_reference | varchar(255) | yes | unique when non-null | external_reference |
| notes | text | yes | — | row_notes |
| attachment_path | varchar(1024) | yes | — | attachments |
| lock_version | int unsigned | no/0 | — | target |
| timestamps | timestamp | no | — | metadata |

## `expense_row_audits`

Append-only: `expense_row_id`, `actor_id`, `operation`, `old_values`, `new_values`, `correlation_id`, `created_at`. JSON is allowed here because audit snapshots are not relational business state.

## Constraints implemented in Actions

- Plafond rows cannot use period distribution and Plafond headers cannot carry context/funding flags.
- Ordinary context project/contract foreign keys are added in feature 004 and are mutually exclusive.
- Replacement target same expense, not Actual, not self, acyclic.
- Generated external reference is immutable after insert.
