# Research: Plafond Singolo e Copertura Integrale

**Date**: 2026-08-12
**Status**: Phase 0 complete; no unresolved clarification
**Baseline**: Slice 023 integrated at `0d6c347289d886359923e582d6805f0accf489b8`; planning baseline `3fd7aa8a0db65430ec6d09453375e2807ba2749f`

## Method and Authority

This research resolves implementation choices only. Product semantics come from
`specs/BUDGET-DOMAIN-REFINEMENT.md`, the accepted decisions in `spec.md`, and the program artifacts
under `specs/022-application-workspace-ux/`. Current code and the Slice 023 contract establish the
implemented baseline. Architect/explorer findings were checked against the current migrations,
`AnnualEconomicMutationGuard`, `EconomicDatasetQuery`, `EconomicEngine`, Expense validators,
Tenant settings action, routes and frontend consumers.

## R01 — Canonical Creation Surface

**Decision**: Create a Plafond only through `POST /api/v1/plafonds`. The request creates one
`kind=plafond` Expense and its required initial `allocation_adjustment` ExpenseRow atomically.
Generic `POST /api/v1/expenses`, `PUT /api/v1/expenses/{expense}` and their preview accept only
Ordinary Expenses and Ordinary row types; they never create or reshape a Plafond.

**Rationale**: The current generic Expense API explicitly accepts only `kind=ordinary`. A dedicated
route gives Plafond creation exactly one public contract and avoids conditional aggregate shapes in
the normal editor while preserving Expense/ExpenseRow as monetary authority.

**Alternatives considered**: Allow `kind=plafond` in generic Expense CRUD (ambiguous row matrix and
two creation paths); separate empty Plafond creation followed by an adjustment (permits a transient
invalid aggregate); alias both routes (violates the single-route requirement).

## R02 — Database-Enforced Live Uniqueness

**Decision**: Add a nullable stored generated discriminator on `expenses`, equal to
`cost_center_id` only when `kind=plafond AND deleted_at IS NULL`, otherwise `NULL`, and a unique key
over `tenant_id, planning_year_id, <active-slot>`. Application validation provides the field-safe
error; the database key is the final concurrency barrier. Because the Product Owner confirmed a
Greenfield product with no data to preserve, consolidate this into the base Expense migration and
prove it from an empty protected test database; do not add a compatibility migration or backfill.

**Rationale**: MySQL unique indexes allow multiple `NULL` values, so Ordinary and soft-deleted
Expenses do not occupy a slot while exactly one live Plafond can occupy each Tenant/Year/Plafond-CC
combination. The current table has only non-unique indexes and cannot prevent concurrent duplicates.

**Alternatives considered**: Application check only (write skew); unique key including `deleted_at`
(multiple `NULL` live rows are allowed by MySQL); a separate allocation table (parallel monetary
source); hard delete to free the slot (conflicts with current lifecycle).

## R03 — AllocationAdjustment Row Shape

**Decision**: Extend the existing Expense row discriminator with `allocation_adjustment`. It is
allowed only inside a Plafond Expense, requires a non-zero signed monetary amount, the existing
`spend_date`, and a single server-owned `created_by_user_id`; the actor is derived server-side and
immutable. It cannot be current planning, Actual,
Extra Budget, covered, vendor/contract/project generated, or mixed with Ordinary row semantics.

**Rationale**: This keeps every allocation delta in the authoritative Expense aggregate and makes
the approved date/author requirements durable. A positive value increases Allocation; a negative
value decreases it. Zero carries no business event and is rejected.

**Alternatives considered**: Reuse Estimate/Quote/Actual (ambiguous economics); add a parallel
allocation date (duplicate temporal ownership); store author only in Audit (row requirement would
not be durable); a dedicated allocations table (second monetary source); client-supplied actor
(spoofable).

## R04 — Coverage on the Ordinary Aggregate

**Decision**: Preserve the single nullable `funded_plafond_expense_id` on each Ordinary
ExpenseRow. Existing Expense create/update/preview carries that field for `estimate|quote|actual`;
no coverage allocation table and no separate “complete coverage” action are introduced. The server
validates same Tenant and PlanningYear, permits different Cost Centers, and enforces the existing
Extra Budget/coverage XOR. Slice 024 does not expose a new `is_extra` UI or write field.

**Rationale**: The current schema already has the correct single reference and a database XOR check.
Extending the canonical aggregate preserves atomic row edits and full coverage while avoiding a
parallel mutation route.

**Alternatives considered**: Dedicated coverage mutation endpoint (duplicate aggregate write
semantics); same Cost Center requirement (contradicts accepted PO answer); multiple allocations or
partial amount (deprecated); expose Extra Budget now (belongs to Slice 025–026).

## R05 — One Economic Engine and Four Measures

**Decision**: Extend the single `EconomicEngine` and projection data structures to produce, per
Plafond, `allocation`, `coverage_planned`, `consumed`, and `available`, each as the existing exact
Net/VAT/Gross/official measure. Formulas are:

```text
allocation       = sum(current allocation_adjustment rows of the Plafond)
coverage_planned = sum(covered selected current estimate/quote rows)
consumed         = sum(covered current actual rows)
available        = allocation - consumed
```

The Plafond allocation contributes once to annual current planning. Covered planning does not add
again to annual planning. Covered Actual remains part of annual Actual and also contributes to the
Plafond's consumed measure; this is classification, not duplicate annual cost.

**Rationale**: The engine currently treats all selected planning and Actual rows uniformly and
ignores Expense kind/funding/Extra. One extension can classify every authoritative line once and
serve all consumers without formula drift.

**Alternatives considered**: Compute Plafond in each query/UI (multiple engines); subtract covered
Actual from annual Actual (incorrect); reserve availability with planning (contradicts PO decision);
retain overrun/residual alias (ambiguous and deprecated).

## R06 — Full Annual Dataset Before Filters

**Decision**: Build the complete Tenant/PlanningYear economic dataset and projection before applying
Cost Center or other report presentation filters. A Plafond Cost Center filter selects Plafond
summaries by the Plafond's Cost Center only after projection; drill-down still includes covered rows
from different Cost Centers and exposes both `plafond_cost_center` and `expense_cost_center`.

**Rationale**: `EconomicDatasetQuery` currently pushes the Cost Center filter into SQL before
projection. That would understate cross-Cost-Center coverage and could approve an invalid capacity
decision. Capacity is annual and Plafond-specific, not a filtered-page total.

**Alternatives considered**: Filter source rows first (incorrect cross-CC totals); prohibit cross-CC
coverage (contradicts accepted PO answer); load separate filtered and capacity datasets (two sources
of truth).

## R07 — Locking and Confirmation

**Decision**: `AnnualEconomicMutationGuard` remains the canonical strongest scope. A mutation starts
one transaction, acquires the Tenant-scoped PlanningYear guard(s) in ascending ID, then affected
Expense roots and ExpenseRows in ascending ID. It reloads the complete annual dataset and validates
uniqueness/capacity before persistence, Revision and Audit. Preview is read-only and reserves
nothing; confirmation repeats every check.

**Rationale**: Locking only the Plafond or ordinary Expense permits write skew between two different
covered Expenses. Slice 023 already established the annual guard and deterministic order required
by approval and later lifecycle operations.

**Alternatives considered**: Optimistic `lock_version` alone (different roots can overcommit);
Plafond row lock alone (creation has no root yet and cross-root writes remain unsafe); preview token
as reservation (stale and stateful); a new lock service (duplicate mechanism).

## R08 — Economic Basis Change

**Decision**: Keep the existing Tenant → PlanningYears ascending lock order in
`UpdateTenantSettings`. When Base changes before its permanent lock, project every annual Plafond in
the proposed Base from stored Net/VAT/Gross values and reject the entire settings change with
`PLAFOND_INSUFFICIENT` if any consumed amount would exceed allocation. Return the first deterministic
failing Plafond plus a complete count; no Tenant field or Audit is changed on failure.

**Rationale**: The current action already locks Tenant and all years but does not revalidate Plafond
capacity. Gross and Net can change relative capacity when rows have different VAT rates.

**Alternatives considered**: Leave existing coverage valid without checking (can create overrun);
rewrite stored amounts (violates Slice 023); clamp availability (invented semantics); silently remove
coverage (data loss).

## R09 — Preparation-Only Transitional Boundary

**Decision**: Until Slice 025–026 implement approval/closure Rectification semantics, Plafond create,
AllocationAdjustment create, adding/changing/removing coverage, Plafond delete, and revision restore
that changes Plafond economics require PlanningYear `preparation`. Other states return
`BUDGET_STATE_CONFLICT` without side effects. Reads remain available under their existing rules.

**Rationale**: Allocation rows after approval/closure can be Rectifications and require phase and
mandatory Note semantics not owned by this Slice. Implementing them now would invent or duplicate
later lifecycle behavior.

**Alternatives considered**: Allow writes in all states (missing Rectification contract); block all
Expense mutations (scope expansion); implement Slice 025–026 early (dependency violation).

## R10 — Delete and Revision Restore Boundary

**Decision**: Preserve existing Expense delete and revision-restore routes. Deleting a Plafond is
blocked while any current row references it; deleting an Ordinary covered consumer releases its
planning/consumed contribution under the annual guard. Revision restore applies only to a live root,
as in the verified current contract; if the restored prior aggregate changes kind, Cost Center,
allocation rows or coverage it reruns live uniqueness, row matrix, XOR, compatibility and capacity.
A soft-deleted root remains in Trash without a restore capability until Slice 030. No Trash list,
new restore endpoint, purge or attachment recovery is added.

**Rationale**: Existing surfaces can violate the new invariants unless they participate in the same
guard and validation. Their broader redesign belongs to Slice 030.

**Alternatives considered**: Cascade/unlink consumers on Plafond delete (silent economic change);
disable all restore (regression); redesign Trash in Slice 024 (scope expansion).

## R11 — Authorization, Errors and Audit

**Decision**: Reuse `expense.view|create|update|delete|view-revisions|restore-revision` according to
the equivalent Plafond operation; do not add permissions. Preserve Slice 023 authentication,
inactive-account/Tenant exception, 404 root versus 422 relationship non-leakage, exact decimal,
currency/basis and correlation contracts. Use stable `PLAFOND_INSUFFICIENT` with exact economic
measures; use `VALIDATION_FAILED`, `STALE_VERSION`, `BUDGET_STATE_CONFLICT` and
`RESOURCE_NOT_FOUND` where already defined. Successful writes produce one aggregate Revision and
business Audit; preview/no-op/failure produces no success evidence.

**Rationale**: Plafond is an Expense nature, so the current permission vocabulary is sufficient.
Stable errors and current redaction prevent UI guessing and cross-Tenant leakage.

**Alternatives considered**: New `plafond.*` permissions (new product/security taxonomy); expose DB
unique errors (unstable and leaky); log monetary payloads (unnecessary sensitive detail).

## R12 — UI Surface Parity and Legacy Removal

**Decision**: Add a Plafond Workspace for create/detail/adjustment and coverage controls to the
Ordinary Expense editor, then project identical Plafond summaries into Document, Register, Budget
and Report. Remove `plafond_overrun`, `global_plafond_overrun`, visible **Sforamento**, and any
Plafond-specific `residual` alias from maintained API/UI adapters. The general Budget `residual`
measure remains because it is a different budget concept. Client money remains server-provided
strings; no stale overrun fallback remains.

**Rationale**: Current Budget/Report still expose constant overrun fields and the UI displays
Sforamento. Keeping them would contradict blocking capacity and provide two meanings for the same
state.

**Alternatives considered**: Keep zero-valued compatibility fields (stale contract); rename overrun
to available without changing semantics (ambiguous); update only the Plafond page (consumer drift).

## Resolved Unknowns

There are no unresolved clarification markers. SQL identifier names, class boundaries and component
composition remain reversible implementation details constrained by the decisions above.
