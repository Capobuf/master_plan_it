# Data model — Feature 010 Projects

## Project

Tenant-owned, current domain aggregate. It has no monetary fields.

| Field | Type | Rules |
|---|---|---|
| `id` | unsigned bigint | Logical identity; terminal after soft delete |
| `tenant_id` | FK Tenant | Required; all relations use the same Tenant |
| `cost_center_id` | composite tenant FK | Required; current valid Cost Center on create/update/restore |
| `title` | varchar(255) | Trimmed, non-empty |
| `stage` | enum | `idea`, `proposed`, `approved`, `deferred`, `rejected` |
| `deferred_target_planning_year_id` | nullable composite tenant FK | Required only for Deferred; cleared for every other stage |
| `lock_version` | unsigned bigint | Starts at 1; incremented by update, restore, promotion and delete |
| `deleted_by_user_id` | nullable FK User | Terminal delete actor |
| `deleted_by_at` | nullable UTC timestamp | Terminal deletion provenance |
| `deletion_reason` | nullable text | Trimmed; maximum 500; conditional requirement from Tenant setting |
| timestamps / `deleted_at` | timestamps | `deleted_at` is a terminal tombstone, never a restorable state |

### Relationships

- belongs to Tenant;
- belongs to Cost Center using the same `tenant_id`;
- optionally belongs to a target PlanningYear using the same `tenant_id`;
- has many current/historical Expense records through `expenses.project_id`;
- has version snapshots linked to `RevisionBatch` through existing polymorphic primitives.

### State invariants

- Deferred requires a current valid target PlanningYear of the same Tenant.
- Non-Deferred stores a null target.
- Deferred becomes Proposed when Tenant-local current year reaches/exceeds the target year.
- Promotion is idempotent and clears the target.
- Delete requires zero current non-deleted linked Expense rows in the same transaction.
- A deleted Project is excluded by normal model/query/selector behavior and is not restored.

## Expense delta

The existing nullable `expenses.project_id` column is retained. The Project migration adds the missing
tenant-safe foreign key; no duplicate column is created.

Invariants:

- zero or one current Project per Expense;
- Project belongs to the Expense Tenant and is not deleted;
- `project_id` and `contract_id` cannot both be non-null (existing database check plus authoritative
  aggregate validation);
- Project and Expense Cost Centers are independent;
- Contract-generated, source-governed Expense rows cannot be converted to Project association.

## Economic line delta

`EconomicLine` gains nullable `projectId`, nullable technical `projectStage`, and an authoritative bucket
derived by `EconomicEngine`. Project is context only: no new monetary row is created.

Bucket mapping:

| Economic row | Project context | Bucket |
|---|---|---|
| Actual | any/none | Primary |
| Estimate/Quote | none or Approved | Primary |
| Estimate/Quote | Proposed | Proposed |
| Estimate/Quote | Idea | Idea |
| Estimate/Quote | Deferred or Rejected | Excluded |
| Plafond allocation / uncovered overrun | any/none | Primary |

`potential = primary + proposed + idea`. Covered Plafond consumption is subtracted once and Excluded is
never included in Potential.

## Revision/deletion invariants

- Create, update, promotion, restore and delete create new immutable package versions and logical revision
  batches with actor, timestamp, operation and correlation ID.
- Restore reads an existing Project version, revalidates all current references and writes a new current
  version; it never rewrites the source snapshot.
- Revision history and compare are available only while the current Project exists.
- Audit records are evidence, not business snapshots or economic sources.
