# Data Model: Plafond Singolo e Copertura Integrale

**Date**: 2026-08-12
**Status**: `VERIFIED CURRENT`

## Scope

This model is the Slice 024 delta over the verified Slice 023 Expense aggregate. It does not replace
the existing Tenant, PlanningYear, Expense, ExpenseRow, RevisionBatch or Audit models. Monetary
authority remains current, non-deleted ExpenseRows and all authoritative values remain exact
Net/VAT/Gross decimals interpreted through the Tenant's official Base.

## Invariants

1. At most one live Plafond exists for `Tenant + PlanningYear + Plafond CostCenter`.
2. A Plafond is an Expense of kind `plafond`; only its `allocation_adjustment` rows define
   Allocation and it never contributes to annual Actual.
3. An Ordinary Expense contains only `estimate|quote|actual` rows. Its rows may each reference zero
   or one live Plafond of the same Tenant and PlanningYear.
4. Covered row and Plafond may use different Cost Centers. Both identities remain visible.
5. A row is wholly covered or uncovered. `is_extra=true` and a Plafond reference are mutually
   exclusive even though Slice 024 does not expose a new Extra Budget write surface.
6. Selected covered Estimate/Quote contributes to `coverage_planned` only. Covered Actual
   contributes both to annual Actual and to the Plafond's `consumed` classification.
7. `available = allocation - consumed`; planning never reserves capacity and no overrun or Plafond
   residual measure exists.
8. Every capacity decision uses the complete annual dataset before presentation filters.
9. Every economic mutation acquires the shared annual guard and locks affected roots/rows in stable
   ascending order; preview reserves nothing.
10. Until Slice 025–026, lifecycle-sensitive Plafond writes are valid only in `preparation`.

## Existing Entity Changes

### Expense

| Field / relation | Slice 024 rule |
|---|---|
| `kind` | Existing `ordinary|plafond`; public generic Expense writes remain `ordinary` only. |
| `tenant_id` | Required and immutable; part of all scoped relations. |
| `planning_year_id` | Required and immutable for the aggregate; coverage requires equality. |
| `cost_center_id` | For `plafond`, identifies the allocation's Cost Center and active uniqueness slot. |
| `current_planning_row_id` | Required by Slice 023 rules for Ordinary planning rows; always null for Plafond. |
| `project_id`, `contract_id` | Null for Plafond; retained unchanged for Ordinary Expense. |
| `lock_version` | Required for update/delete/restore paths and incremented once per successful mutation. |
| `deleted_at` | Existing soft-delete marker; releases the live uniqueness slot. |
| generated active slot | Stored nullable discriminator: Plafond CostCenter ID for live Plafond, null otherwise. |

**Database uniqueness**:

```text
active_plafond_cost_center_id =
  CASE WHEN kind = 'plafond' AND deleted_at IS NULL THEN cost_center_id ELSE NULL END

UNIQUE (tenant_id, planning_year_id, active_plafond_cost_center_id)
```

The exact generated-column and index identifiers are technical choices. Their semantics are not.
A future Trash restore may reclaim the slot only after locking and revalidation; Slice 024 does not
expose that operation. A live-root revision restore that changes the Cost Center revalidates the
target slot. An Ordinary Expense or soft-deleted Plafond has a null slot and does not collide.

**Kind matrix**:

| Rule | Ordinary | Plafond |
|---|---:|---:|
| Created by generic Expense route | yes | no |
| Created by dedicated Plafond route | no | yes |
| Estimate/Quote/Actual row | yes | no |
| AllocationAdjustment row | no | yes |
| Current planning pointer | Slice 023 rules | null |
| Vendor/Project/Contract origin | existing rules | absent |
| Can be referenced as funding Plafond | no | yes, same Tenant/Year |
| Annual current-planning contribution | selected planning row | Allocation once |
| Annual Actual contribution | all Actual rows | never |

### ExpenseRow

| Field / relation | Slice 024 rule |
|---|---|
| `type` | Extended to `estimate|quote|actual|allocation_adjustment`. |
| `expense_id`, `tenant_id` | Existing composite Tenant-scoped parent relation. |
| `position`, `description` | Required as in Slice 023. |
| direct/calculated amount fields | Existing exact decimal XOR; AllocationAdjustment uses the same normalization. |
| `net_amount`, `vat_amount`, `gross_amount` | Required exact reconciled values. |
| `spend_date` | Required for Actual and AllocationAdjustment; allocation date records when the delta applies. |
| `created_by_user_id` | Single server-owned immutable row author; required for AllocationAdjustment. No parallel allocation actor field is added. |
| `funded_plafond_expense_id` | Nullable on Ordinary Estimate/Quote/Actual; null on AllocationAdjustment. |
| `is_extra` | Existing boolean and DB XOR with funding; no new Slice 024 request field. |
| vendor / generation fields | Existing rules for Ordinary rows; all null/unset for AllocationAdjustment. |
| `lock_version`, `deleted_at` | Existing optimistic concurrency and current-dataset rules. |

**AllocationAdjustment validation**:

- parent Expense is live `kind=plafond` in the same Tenant;
- normalized official amount is non-zero; positive increases and negative decreases Allocation;
- existing `spend_date` and server-derived `created_by_user_id` are present;
- it is never the current planning row;
- `is_extra=false`, no funding reference, vendor, Contract term, source key, confirmation state,
  period distribution or system-management meaning;
- a negative row is rejected if the final projected Allocation is below Consumed.

Creation always includes a first non-zero AllocationAdjustment. Allocation can later equal `0.00`
only because current signed adjustments compensate each other; a live Plafond without allocation
rows is never a valid aggregate.

**Ordinary coverage validation**:

- parent Expense is `kind=ordinary`;
- row type is Estimate, Quote or Actual, never AllocationAdjustment;
- referenced root is a live Plafond of the same Tenant and PlanningYear;
- Cost Center equality is not required;
- reference covers the whole authoritative amount;
- `is_extra` and reference cannot coexist;
- Actual capacity is checked against the final annual dataset; Estimate/Quote capacity is not
  blocked.

### Tenant

No new persistent field is required. `UpdateTenantSettings` retains the Slice 023 state and lock
contract. Before committing an allowed Base change, every PlanningYear is projected in ascending ID
under its annual guard using the proposed Base. If any Plafond becomes insufficient, the Tenant
update, lock version increment and success Audit all roll back.

### PlanningYear

No new persistent field or state is introduced. Its existing row is the serialization guard. Slice
024 Plafond writes require `state=preparation`; the states themselves and future Rectification
transitions remain owned by Slice 025–026.

## Derived Projection Types

### PlafondEconomicProjection

This is a read model, not a persistent monetary entity.

| Field | Meaning |
|---|---|
| `plafond_expense_id` | Identity of the Plafond Expense. |
| `planning_year_id` | Annual context. |
| `plafond_cost_center` | ID and label of the allocation Cost Center. |
| `currency`, `basis` | Context for all contained measures. |
| `allocation` | Sum of current AllocationAdjustment rows. |
| `coverage_planned` | Sum of covered selected current Estimate/Quote rows. |
| `consumed` | Sum of covered current Actual rows. |
| `available` | Allocation minus Consumed. |
| `allocation_lines` | Traceable contributing adjustments in stable row order. |
| `covered_lines` | Traceable Ordinary contributors with their own Cost Center. |

Every economic value uses the Slice 023 `EconomicMeasure` shape:

```json
{ "net": "3500.00", "vat": "770.00", "gross": "4270.00", "official": "3500.00" }
```

There is no `overrun`, `plafond_overrun`, `global_plafond_overrun` or Plafond `residual` field.
Negative Actuals may make Consumed negative and Available greater than Allocation; values are not
clamped.

### PlafondImpact

Ephemeral result used by Expense preview and AllocationAdjustment preview.

| Field | Rule |
|---|---|
| current four measures | Computed from the complete committed annual dataset. |
| `requested` | Final official contribution introduced/replaced by the proposal. |
| proposed four measures | Computed by replacing the affected final aggregate, never by adding old and new versions. |
| `shortage` | `max(proposed_consumed - proposed_allocation, 0)` for presentation only. |
| `blocking_rows` | Current covered Actual contributors when a reduction is insufficient; scoped links only. |
| `can_confirm` | Informational result, not an authorization or reservation. |

The mutation always reconstructs this result after acquiring locks.

## Relationships

```text
Tenant 1 ── * PlanningYear
Tenant 1 ── * CostCenter
PlanningYear 1 ── * Expense
CostCenter 1 ── * Expense

Expense(kind=plafond) 1 ── * ExpenseRow(type=allocation_adjustment)
Expense(kind=ordinary) 1 ── * ExpenseRow(type=estimate|quote|actual)
ExpenseRow ordinary * ── 0..1 Expense(kind=plafond)
ExpenseRow allocation_adjustment * ── 1 User (author)
```

The funding relationship is same Tenant and PlanningYear but deliberately not same Cost Center.

## State and Mutation Transitions

### Plafond Lifecycle in Slice 024

```text
absent --POST /plafonds + initial adjustment--> live Plafond
live --positive/negative adjustment--> live with new Allocation
live --revision restore of a prior aggregate--> live (all invariants revalidated)
live --existing delete, no live references--> soft-deleted (slot released; no Slice 024 restore)
```

- Duplicate live creation or a revision restore that changes the Plafond Cost Center is rejected
  atomically.
- Delete with current referencing rows is rejected; no implicit unlink occurs.
- A soft-deleted root remains in Trash; its restoration belongs to Slice 030.
- Every transition above is preparation-only until lifecycle owners extend it.

### Ordinary Coverage Transition

```text
uncovered <---- remove/change funding ----> covered by exactly one Plafond
```

The transition occurs only inside the full-final Ordinary Expense create/update/restore aggregate.
Moving between Plafond A and B validates both projections in one locked transaction. Extra Budget is
not a third Slice 024 UI state; the existing XOR rejects any invalid final row.

## Lock and Validation Order

1. Authenticate and authorize the persisted active actor in the selected Tenant.
2. Resolve root-style IDs with non-leaking 404 and body relationships with field-safe 422.
3. Start one transaction.
4. When Tenant state changes, lock Tenant first.
5. Acquire all affected PlanningYear rows in ascending ID through
   `AnnualEconomicMutationGuard`.
6. Lock affected Expense roots in ascending ID, then current ExpenseRows in ascending ID.
7. Reload relationships and compare all submitted lock versions.
8. Build the complete annual current dataset in the effective Base.
9. Replace the proposed aggregate(s), then validate kind matrix, XOR, live uniqueness and capacity.
10. Persist root/rows, create exactly one aggregate Revision and record the business Audit.
11. Commit; any exception rolls back all steps.

Preview performs steps 1–2 and the same normalization/projection rules without persistent locks,
Revision, Audit, mutation or reservation.

## Delete and Restore Effects

| Operation | Required outcome |
|---|---|
| Delete covered Ordinary Expense/row | Remove its planning/consumed contribution after the guarded commit. |
| Delete Plafond with current references | Reject without unlinking or deleting anything. |
| Delete unreferenced Plafond | Existing soft delete; release live uniqueness slot and remove it from current projection. |
| Restore covered Ordinary revision | Revalidate Plafond existence, same Tenant/Year, XOR and Actual capacity. |
| Restore prior revision of live Plafond | Revalidate kind, Cost Center/live uniqueness, row matrix and Allocation ≥ Consumed. |
| Restore soft-deleted root | Not exposed by Slice 024; remains Slice 030 work. |

No new Trash list, purge, retention or attachment restoration behavior is implied.
