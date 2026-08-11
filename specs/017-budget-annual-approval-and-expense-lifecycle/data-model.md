# Data model — Feature 017 Budget annuale, approvazioni e ciclo Spese

## PlanningYear / Annual Budget

| Field | Type | Rules |
|---|---|---|
| `tenant_id`, `year_label` | existing composite identity | Unique Tenant/year |
| `active` | existing boolean | Selector availability; independent from Budget state |
| `budget_state` | enum | `preparation`, `approved`, `closed`; default preparation |
| `history_activated_at` | UTC timestamp | Earliest supported historical cutoff |
| `lock_version` | integer | Optimistic concurrency; increments on state transitions |

PlanningYear becomes Versionable. It has many Expense and ApprovalOperation records.

## Expense

| Field | Type | Rules |
|---|---|---|
| existing ownership/dimensions | FKs | Tenant/year/Cost Center required; Project/Contract optional |
| `approved_amount` | nullable decimal(19,2) | Nonnegative; null is not approved, zero is approved at zero |
| `approved_basis` | nullable enum | `net` or `gross`; both null or both populated |
| `state` | enum | `open`, `closed` |
| `closure_outcome` | nullable enum | `not_incurred`, `cancelled`, `moved`; only valid when closed |
| `current_planning_row_id` | nullable FK | Same Tenant/Expense, current Estimate or Quote |
| `moved_from_expense_id` | nullable tenant FK | Destination links to origin; same Tenant, different year |
| `credit_for_expense_id` | nullable tenant FK | New-year negative credit links to origin |
| `lock_version` | integer | Increment on every logical mutation |

Project and Contract may coexist only when Contract.project_id equals Expense.project_id. Economic changes reopen
a closed Expense. Approved fields are writable only by Approval Action.

## ExpenseRow delta

- Types remain `estimate`, `quote`, `actual`.
- Actual requires `spend_date` in the Expense year and may be negative.
- Estimate/Quote are nonnegative and may omit planned date.
- `confirmation_state` and period/distribution columns are legacy storage only and are absent from the new domain
  contract. New writes keep confirmation null and do not write periods.
- Generated Quote uses `source_key=contract:{contract_id}:year:{year}` and current term provenance.
- `is_system_managed=false` or `manual_override_at!=null` protects a generated planning row from overwrite.

## Contract delta

| Field | Type | Rules |
|---|---|---|
| `project_id` | nullable composite tenant FK | Current same-Tenant Project |

One Contract/year has at most one generated Expense/current generated Quote. Project changes affect future
generation only.

## ApprovalOperation

| Field | Type | Rules |
|---|---|---|
| `tenant_id`, `planning_year_id` | composite scope | Required and immutable |
| `kind` | enum | `initial`, `variation` |
| `effective_date` | date | Product date supplied by VCO |
| `recorded_at` | UTC timestamp | Server timestamp |
| `actor_user_id` | FK User | Required authorship |
| `reason` | nullable text | Trimmed, max 500 |
| `budget_basis` | enum | Frozen official basis |
| `revision_batch_id` | FK | One atomic logical history batch |
| `correlation_id` | string | Diagnosability/common operation ID |

## ApprovalItem

| Field | Type | Rules |
|---|---|---|
| `approval_operation_id` | FK | Immutable parent |
| `tenant_id`, `planning_year_id`, `expense_id` | scoped FKs | Same annual Budget |
| `previous_amount`, `new_amount` | nullable/exact decimal | New amount nonnegative |
| `delta_amount` | decimal | Server-calculated, treating null as zero only for delta arithmetic |
| dimension snapshots | IDs/strings | Cost Center, Project, Contract, kind and basis at decision time |

Unique Expense per operation. Items never feed current totals directly.

## RevisionBatchItem delta

| Field | Type | Rules |
|---|---|---|
| `tenant_id` | FK | Denormalized indexed scope |
| `planning_year_id` | nullable composite FK | Annual scope where applicable |
| `mutation` | enum | `upsert`, `delete` |

Historical selection partitions by subject and orders by batch `occurred_at`, batch ID and sequence. Delete is an
explicit tombstone. Activation baseline uses upsert items for every current subject required by the annual view.

## Derived economic values

- `planned = selected Estimate/Quote in official basis`
- `actual = sum current Actual rows in official basis`
- `residual = approved - actual` when approved is non-null; otherwise null
- `variance = actual - approved` when approved is non-null; otherwise null
- `proposed = sum relevant planned`
- `initial_approved = values established by first ApprovalOperation`
- `approved_variations = sum deltas after initial operation`
- `approved_current = sum non-null Expense approved amounts`
- Plafond covered consumption is removed once; overrun remains in totals and is exposed separately.
