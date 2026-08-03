# Data model — Expense domain

Status: `CLARIFIED LOGICAL MODEL — PHYSICAL PLAN REQUIRED`

## Tenant ownership

Each Expense belongs to one tenant. Expense rows inherit ownership from Expense. All referenced year, cost center, vendor, Plafond, project, contract, attachment, operational revision, and audit event must belong to the same tenant. Cross-tenant links fail before calculation or persistence.

## `expenses`

| Column | MySQL | Nullable/default | Constraint/index | Purpose |
|---|---|---|---|---|
| `id` | bigint unsigned | no | PK | stable logical identity |
| `tenant_id` | bigint unsigned | no | FK restrict/index | tenant ownership |
| `legacy_id` | varchar(140) | yes | unique with tenant/source | migration reconciliation |
| `kind` | varchar(16) | no | application enum/index | `Ordinary` or `Plafond` |
| `title` | varchar(255) | no | index | current title |
| `planning_year_id` | bigint unsigned | no | same-tenant FK/index | current year |
| `cost_center_id` | bigint unsigned | no | same-tenant FK/index | current cost center |
| `uses_plafond` | boolean | no/false | — | funding flag |
| `plafond_expense_id` | bigint unsigned | yes | same-tenant/year self FK | funding source |
| `is_extra` | boolean | no/false | — | extra flag |
| `project_id` | bigint unsigned | yes | same-tenant FK/index | optional context |
| `contract_id` | bigint unsigned | yes | same-tenant FK/index | optional context |
| `notes` | text | yes | — | current notes |
| `lock_version` | int unsigned | no/0 | — | optimistic concurrency |
| `created_at/updated_at` | timestamp | no | indexes as required | current metadata |
| `deleted_at` | timestamp | yes | index | persistence tombstone; excluded from active domain |

Project and contract are mutually exclusive when present. `deleted_at` is infrastructure, not an economic state; ordinary domain queries always exclude it.

## `expense_rows`

| Column | MySQL | Nullable/default | Constraint/index | Purpose |
|---|---|---|---|---|
| `id` | bigint unsigned | no | PK | stable logical row identity |
| `legacy_id` | varchar(140) | yes | unique with tenant/source through parent | migration reconciliation |
| `expense_id` | bigint unsigned | no | FK restrict/index | aggregate parent |
| `position` | int unsigned | no | unique current expense+position | current order |
| `phase` | varchar(16) | Plafond may null | index | Estimate/Quote/Actual |
| `vendor_id` | bigint unsigned | Ordinary required | same-tenant FK/index | vendor |
| `description` | varchar(255) | no | — | current description |
| `quantity` | decimal(19,6) | no/1 | — | input |
| `unit_price` | decimal(19,6) | yes | — | input |
| `entered_amount` | decimal(19,6) | no | — | input/derived intermediate |
| `amount_includes_vat` | boolean | no/false | — | input mode |
| `vat_rate` | decimal(7,4) | yes | — | row/default VAT |
| `amount_net` | decimal(19,2) | no/0 | index only if proven | business result |
| `amount_vat` | decimal(19,2) | no/0 | — | business result |
| `amount_gross` | decimal(19,2) | no/0 | — | business result |
| `spend_date` | date | yes | index | point date mode |
| `start_date/end_date` | date | yes | indexes | period mode |
| `distribution` | varchar(8) | yes | — | all/start/end |
| `external_reference` | varchar(255) | yes | tenant-scoped unique when non-null | stable external identity |
| `generation_source_key` | varchar(255) | yes | tenant-scoped unique when non-null | generated occurrence identity |
| `notes` | text | yes | — | current notes |
| `lock_version` | int unsigned | no/0 | — | optimistic concurrency |
| `created_at/updated_at` | timestamp | no | — | current metadata |
| `deleted_at` | timestamp | yes | index | persistence tombstone; excluded from active domain |

The target model has no current `state`, `replaces_row_id`, or replacement graph. Legacy state/replacement values are migration inputs and may inform revision evidence, but are not target current-row lifecycle fields.

## Attachments

Attachments use a dedicated tenant-owned relation rather than one path column on `expense_rows`. Logical fields include parent type/ID, tenant, storage disk/path, original name, MIME, size, checksum, actor, timestamps, and optional deletion timestamp. File payload deletion follows the parent/action contract; revision/audit stores metadata only.

## Operational revisions

The selected versioning package stores per-model versions. Application-owned metadata adds, where the package does not already provide it:

- `tenant_id`;
- `revision_batch_uuid`;
- aggregate root type/ID;
- operation;
- actor;
- restored-from version reference;
- deletion reason/minimized tombstone metadata.

One Expense form transaction assigns the same revision batch to the Expense and all changed rows/attachments. Revision payloads never join current totals.

## Audit

Audit is separate from operational revision storage. It records security/business events with minimized old/new properties and 24-month retention. Expected validation failures are not error events. Passwords, secrets, sessions, and full attachment contents are excluded.

## Constraints implemented by Actions

- current Expense must retain at least one current non-deleted row;
- Ordinary and Plafond field rules;
- Extra/Plafond funding exclusivity;
- same-tenant/year Plafond reference;
- optional project XOR contract context;
- exact Money/VAT/date/distribution rules;
- generated source key and external reference uniqueness;
- current master-data selection rules;
- permission plus tenant checks;
- restore revalidates every current constraint;
- delete/restore aggregate transaction includes related files and revisions;
- no current query includes `deleted_at` records or revision/audit storage.
