# Research — Budget Proposto e Approvazione

**Feature**: `025-budget-proposal-approval`
**Date**: 2026-08-13
**Status**: all Phase 0 technical decisions resolved

## Baseline examined

- The current bridge (`ApplyBudgetApproval`, `approval_operations`, `approval_items`, and
  `expenses.approved_amount`) accepts a caller-selected subset and mutable approved amounts. It
  therefore cannot represent the complete immutable decision required by this Slice.
- `EconomicDatasetQuery` and `EconomicEngine` already provide the authoritative, exact-decimal
  Tenant/Year projection. They exclude soft-deleted expenses and rows from the *current*
  projection and already mark the one-time annual contributors through
  `ProjectedEconomicLine::contributesToCurrentPlanning`.
- `AnnualEconomicMutationGuard` always locks the Tenant first (shared for ordinary writers,
  exclusive for Approval/Base mutation) and then Tenant-scoped `PlanningYear` rows in sorted ID
  order. Expense, Project and Contract mutations already acquire this guard; copied-dimension
  writers join the same Tenant-first order before locking their roots. It is the shared
  linearization point; a second projection implementation/source or a separate approval lock is
  not permitted. Approval may invoke the same projection twice for identity discovery and the
  final authoritative rebuild after its contributing locks.
- The neutral `TenantMutationLock` owns that first-row primitive. Tenant-scoped identity mutations
  also acquire its shared lock before User/Role roots, preventing their Tenant-FK Audit writes from
  cycling with Approval's exclusive Tenant lock and actor FKs.
- The existing tenancy middleware, `TenantOwnedRecordQuery`, policies, API error envelope,
  optimistic `lock_version`, revision batches, audit recorder, Tenant timezone, and exact decimal
  conventions remain the current contract. Slice 022 records the target annual lifecycle and
  explicitly identifies this legacy approval bridge as superseded.

## Decisions

### 1. Contributor-only immutable approved snapshot

**Decision**: Replace the bridge's caller-supplied `ApprovalItem` decisions and
`expenses.approved_amount` as the authoritative approved value with one immutable approval header
and immutable snapshot-item rows. A snapshot item is created for each canonical contributor derived
from the authoritative `AnnualEconomicProjection`: one selected ordinary planning row, or one
aggregate Plafond allocation per Plafond Expense irrespective of how many signed allocation rows
compose it. It copies the source Expense and nullable ExpenseRow identity, kind/type,
net/VAT/gross/official amounts, basis, dimensions and labels
needed for historical display, plus the full snapshot totals on the header. It has no write or
restore action. Preview exclusions are explanatory transient data, never snapshot monetary items.

**Rationale**: This freezes exactly the complete proposal the user reviewed, including zero-value
contributors, while remaining independent of changed/deleted source records and technical
Revision chains. It reuses the one engine decision that already includes a selected ordinary plan
once and a Plafond allocation once while leaving covered planning informational. The absence of
contributors, not a `0.00` total, rejects approval.

**Alternatives considered**:

- Retain `approved_amount` on `expenses` and per-expense `ApprovalItem` deltas — rejected because
  it permits partial decisions and later writes to the approved meaning.
- Persist all lines or all preview exclusions in the snapshot — rejected because noncontributors
  are not approved monetary content and would create a second source of economic truth.
- Reconstruct approved values from live rows or Version history — rejected because live fields and
  retained Versions are mutable/operational and cannot guarantee historical reconstruction.

### 2. Deterministic composition fingerprint

**Decision**: Build a versioned canonical payload from the full, unfiltered proposal. Preview
computes it from one consistent read without acquiring a write lock or reservation; confirmation
rebuilds it only after the annual guard is acquired. Sort contributors by stable
`source_identity` (`expense-row:{row_id}` or `plafond-allocation:{expense_id}`) and serialize fixed
scale decimal strings, basis, source identities, economic classification, all three monetary
components and copied dimensions/labels. Header totals and contributor count are derived and
reconciled from that array but are not duplicated as digest input. Explanatory exclusions remain in
the reviewed impact view but are not approved monetary content and therefore are not fingerprint
input. Hash the canonical contributor bytes with SHA-256 and expose the resulting
`composition_fingerprint` in preview; the
approve command returns that exact opaque value together with `lock_version`. At confirmation the
server locks, rebuilds the same canonical payload, rejects a mismatch before snapshot persistence,
and derives the snapshot exclusively from the rebuilt payload.

**Rationale**: A total or a planning-year version alone cannot detect a changed contributor whose
amount compensates to the same total. Canonical ordering and fixed decimal representation prevent
ordering, locale, or numeric-format noise from changing the evidence. Recalculation under the
shared guard prevents both mixed snapshots and a preview token from reserving data.

**Alternatives considered**:

- Compare only the total or individual Expense lock versions — rejected because they miss
  membership, row, classification, dimension, and compensating-amount changes.
- Persist a server-side preview reservation/token — rejected because previews are explicitly
  informative and must not reserve the economic dataset.
- Let the client calculate a hash — rejected because the server owns inclusion and must not trust
  presentation data as the decision source.

### 3. Greenfield replacement of the legacy bridge

**Decision**: Use the approved Greenfield scope to replace the legacy bridge schema as part of the
clean protected development/test schema: remove its mutable approval fields and bridge tables and
create the approval header/snapshot schema required by this Slice. The migration path is verified
through the existing guarded `php artisan app:test-reset-greenfield --seed` command; it contains no backfill, conversion
of partial decisions, or application-level purge. The feature's own new approvals, snapshots,
revisions, and audit history are thereafter never removed by this replacement.

**Rationale**: The Product Owner explicitly confirmed that no pre-feature data must be preserved.
Converting arbitrary partial `approved_amount` values would fabricate a supposedly complete
historical snapshot, which is less safe than rebuilding the protected schema. The choice remains
limited to the protected Greenfield environments and does not authorize deleting Expenses in the
trash or post-feature history.

**Alternatives considered**:

- Backfill old operation/item rows into snapshots — rejected because they contain partial,
  user-selected amounts rather than the complete authoritative composition.
- Keep both bridges indefinitely — rejected because two approved-value authorities would violate
  the one-Budget/one-projection invariant.
- Add a general production data purge — rejected because it exceeds the explicitly authorized
  Greenfield reconstruction and the Cestino rules.

### 4. One active approval per Tenant and PlanningYear

**Decision**: The approval header has explicit `active`/`annulled` status and a nullable stored
generated `active_planning_year_id`: it equals `planning_year_id` only while status is `active`,
otherwise `NULL`. A database unique key on `(tenant_id, active_planning_year_id)` is the final
invariant, while the annual
guard, Budget-state check, and optimistic `lock_version` provide the normal serialized path. The
approval header is never deleted or reactivated, and application code cannot make the discriminator
disagree with status.

**Rationale**: MySQL unique keys allow multiple `NULL`s, so the key permits any number of
historical annulled decisions and at most one active decision for a Tenant/Year. It also makes two
simultaneous confirmations safe even if an application-level check is accidentally bypassed.

**Alternatives considered**:

- Enforce uniqueness only in the Action — rejected because concurrent writers need a database
  backstop.
- Unique `(tenant_id, planning_year_id, status)` — rejected because it would incorrectly allow
  only one annulled history row.
- A partial unique index on `status = active` — rejected because the deployed MySQL strategy does
  not supply that portable partial-index primitive.

### 5. Effective date uses the Tenant-local calendar

**Decision**: Parse `effective_date` as a calendar `Y-m-d` value and accept it iff it is on or
before `CarbonImmutable::now($context->timezone)->toDateString()`. Do not compare it to the
PlanningYear label or impose an annual range. Store the accepted calendar date unchanged and set
`recorded_at` independently from the server UTC clock within the transaction.

**Rationale**: The Tenant already owns a validated timezone and other annual views use it. This
implements the approved rule at the local-day boundary, including dates outside the Budget year,
without conflating an effective business date with the technical recording instant.

**Alternatives considered**:

- Use the API server timezone or browser date — rejected because the decision is Tenant-local and
  clients are not authoritative.
- Require a date in the PlanningYear — rejected because the specification expressly permits an
  earlier or otherwise outside-year effective date.
- Store only `recorded_at` — rejected because it loses the separately approved effective date.

### 6. Canonical four-group annulment blocker read

**Decision**: Implement one dedicated Tenant/Year-scoped blocker query/service that returns exactly
`actuals`, `extra_budget`, `rectifications`, and `closures`, with each group deduplicated by its
stable source identity before presentation. `actuals` reads `expense_rows` joined to `expenses`
without either soft-delete filter and selects every `type = actual`, including `0.00`. `extra_budget`
uses the same unfiltered relation and selects every `is_extra = true`. Thus a soft-deleted Expense
or row remains visible to validation, and a single row satisfying both predicates occurs once in
each applicable group, never once overall. Rectification and closure groups read their respective
append-only lifecycle seam records for the same Tenant/Year. No amount threshold, current-projection
filter, cross-year relationship, or residual catch-all is applied.

**Rationale**: This distinguishes the current projection's deliberate exclusion of trashed rows
from the historical operational facts that block an annulment. Separate group membership preserves
the specified non-exclusive Actual+Extra behavior and gives one stable source for both preview and
the final under-lock revalidation.

**Alternatives considered**:

- Reuse `EconomicDatasetQuery` — rejected because it excludes trashed data and only models the
  current projection.
- Use one precedence-ordered category per row — rejected because a row may canonically be both
  Actual and Extra Budget.
- Infer blockers from audit/revision records or an "other activity" bucket — rejected because
  those are explicitly not blockers and would add a forbidden fifth category.

### 7. Minimal Rectification/Closure persistence seam; Slice 026 owns writers

**Decision**: Slice 025 establishes the two distinct append-only Tenant/PlanningYear persistence
seams needed to read blocker identity: canonical Rectification records and canonical Closure
operation records. They record their own source/operation identity and recording instant and support
Tenant-bound historical lookup; there is no generic lifecycle-event or blocker table. Slice 025
owns the schemas, models/factories, read contract, blocker query, and fixture seam. Slice 026 owns
their business creation actions, full monetary/closure snapshot payloads, UI, reopening behavior,
and every rule that produces them. The Slice 025 annulment Action never creates either record.

**Rationale**: Rectification and Closure are already required blocker categories, but their target
creation lifecycle belongs to Slice 026. An append-only, typed read seam makes the blocker contract
testable now and lets a later reopening leave the recorded closure fact available, without pulling
026 workflows into this Slice or inventing a generic blocker category.

**Alternatives considered**:

- Omit the two groups until Slice 026 — rejected because this Slice must return and revalidate all
  four canonical groups.
- Implement full Rectification/Closure/Riapertura actions now — rejected because ownership is
  explicitly Slice 026.
- Use PlanningYear's current state as the only closure evidence — rejected because a historical
  closure must still block after reopening and multiple lifecycle facts need durable identity.

### 8. Authorization and redaction retain inherited abilities

**Decision**: Reuse existing server-side abilities: `budget.view` protects overview, impact,
history, and read-only snapshot detail; approval and annulment require both `budget.view` and the
inherited manage-budget ability `expense.update`. No permission names or roles are added. Execute the established
active-user, Tenant-context, permission-team, and active-Tenant checks before any economic read.
For each blocker/snapshot drilldown, authorize the target source with its existing record policy;
when it is not viewable, return one non-actionable redacted marker per blocker with an
Approval-scoped opaque HMAC alias but no raw source ID, label, amount, actor or link. Array
cardinality and the same alias across applicable groups remain visible to `budget.view`; they are
required to represent every blocker and the approved overlap rule. It still makes `can_annul` false.

**Rationale**: The approved operation must not become executable merely because a dependency is
confidential, while Tenant isolation forbids revealing protected details. This conforms to the
existing policy model and the explicit prohibition on invented permission names.

**Alternatives considered**:

- Hide inaccessible blockers and allow annulment — rejected because it defeats the operational
  safety rule.
- Return source IDs, titles, counts, or amounts with a disabled link — rejected because those are
  data disclosures.
- Add a dedicated approval permission — rejected because the specification requires the inherited
  authorization contract and forbids new names.

### 9. Tenant-to-PlanningYear guard and error precedence

**Decision**: For every endpoint, process authentication, active user, Tenant context, active
Tenant exception, and endpoint ability first. Then resolve the PlanningYear through
`TenantOwnedRecordQuery`; missing and foreign identifiers both become `RESOURCE_NOT_FOUND` with
the same envelope. For approval-specific paths, resolve the approval only through the already
Tenant-scoped Year and require it to be active; missing, foreign, annulled, or non-active targets
do not yield a favorable preview. Inside each mutation, acquire `AnnualEconomicMutationGuard` and
re-query the same Tenant/Year and target state. Approval checks the Tenant-local future-date bound
first, then state/active slot, Budget version, exact schema/projection versions, the authoritative
rebuilt fingerprint, and only then empty composition. Annulment retains its own
state/version/blocker precedence. No mutation creates evidence before all applicable checks
succeed.

**Rationale**: This makes authorization fail before protected reads, makes missing and foreign
resources observationally equivalent, and avoids accepting a target that changed between routing
and the transactional decision. The shared lock provides the required complete ordering with
economic writers.

**Alternatives considered**:

- Query a global approval by ID then compare Tenant — rejected because existence and state could
  leak.
- Trust the route-resolved model through the mutation — rejected because it can become stale or
  be supplied from another Tenant.
- Check blockers before the guard — rejected because a concurrent blocker could then create a
  mixed decision.

### 10. Correlation IDs are diagnostic and non-unique

**Decision**: Change the approval correlation column from a unique constraint to a normal index,
matching revision batches and audit events. Every approve or annul retry is an independent request
that repeats authentication, state, `lock_version`, fingerprint/date or blocker validation. Audit
properties keep only the operation kind, immutable IDs, and safe cardinalities; full money payloads
remain in the authorized snapshot, not logs or error details.

**Rationale**: The supplied Correlation ID follows a request for diagnosis across revisions and
audit; it is not an idempotency key. A uniqueness error would incorrectly turn diagnostic reuse
into replay semantics and could bypass required revalidation.

**Alternatives considered**:

- Preserve the existing unique constraint and replay the first response — rejected because the
  specification explicitly rejects approval deduplication.
- Ignore/re-generate caller correlation IDs — rejected because cross-layer diagnostics require a
  consistent request identifier.
- Put snapshot money in audit/error payloads — rejected because diagnostics must not expose full
  monetary or foreign-Tenant data.

### 11. Approval and annulment transaction boundary

**Decision**: Both Actions use one database transaction. Approval obtains the annual guard with an
exclusive Tenant lock, reloads/reauthorizes the actor and locked Tenant, checks the Tenant-local
date before state/version, discovers and locks all contributing roots and copied dimensions, then
rebuilds and checks the fingerprint before rejecting an empty composition. It begins exactly one
revision batch (and its `revision.batch.begin` infrastructure Audit), inserts the single active
header and all immutable items, transitions the Year, links its new Version, sets
`economic_basis_locked_at` only if null, and writes one operation-specific business Audit.
Annulment locks the same Year and active approval, validates trimmed non-empty note and the expected
PlanningYear/Budget version,
rebuilds the four groups under lock, then marks that header annulled, transitions to Preparation,
and writes exactly one revision batch and one operation-specific business Audit, plus the shared
revision infrastructure Audit. Any exception rolls back every listed
write, including a first attempted basis lock.

**Rationale**: This preserves both the immutable economic decision and its evidence as a single
linearizable operation. It also makes a concurrent Actual, Extra, Rectification, or Closure either
fully precede a successful annulment or cause the stable
`BUDGET_APPROVAL_ANNULMENT_BLOCKED` failure with no partial history.

**Alternatives considered**:

- Treat preview success as a reservation — rejected because previews are informational.
- Write revision/audit asynchronously — rejected because evidence is required atomically with the
  decision.
- Unlock the Tenant basis on annulment — rejected because the first historical approval permanently
  establishes it.

### 12. Remove the invalid legacy Closure bridge until Slice 026

**Decision**: Remove the current `POST .../close` route exposure, controller branch and React close
control in Slice 025. The current bridge permits `preparation -> closed` and creates no durable
Closure fact. Slice 025 supplies only the typed `budget_closures` persistence/read seam and fixtures;
Slice 026 owns the sole target Close/Reopen actions, full snapshot and UI, and requires an active
approved snapshot.

**Rationale**: Leaving the bridge callable would violate the target lifecycle and could not prove a
historical Closure after reopening. Adding an incomplete writer here would duplicate the next
Slice's vertical user outcome.

**Alternatives considered**:

- Keep the bridge temporarily — rejected because a delivered 025 installation could enter an
  invalid target state.
- Make the bridge silently insert a minimal Closure row — rejected because it would publish an
  underspecified second Closure workflow.
- Infer Closure from mutable `PlanningYear.budget_state` — rejected because reopening erases that
  evidence.

### 13. UI, history, and drilldown follow the same immutable source

**Decision**: Replace the current selectable per-expense approval modal with an impact view fed by
the server preview: full contributor list, totals in recorded basis, inclusion/exclusion reasons,
and authorized source links. The only confirmation fields are effective date, optional approval
note, `budget_lock_version`, and opaque composition fingerprint; no per-item selection or amount input is
sent. The Budget overview shows `proposed` only in Preparation and, when approved, separately shows
the immutable `approved_snapshot`/planned total, current informational evaluations, and current
Actuals. History lists every active or annulled approval and opens the immutable header/item detail.
Snapshot drilldown filters those stored items and must reconcile exactly to the stored header total;
links retain the selected Tenant/PlanningYear context and are omitted/redacted when not authorized
or no longer navigable. Client date controls may prevent future dates using the Tenant timezone but
the server remains authoritative; failed previews/confirms retain correctable form input and show
the API cause/correlation reference.

**Rationale**: A single presentation of the same server composition prevents the UI from reviving
the legacy partial-decision behavior and keeps overview, impact, history, and drilldown reconcilable
to the cent. Redaction does not erase the safety meaning of a blocker.

**Alternatives considered**:

- Keep checkboxes and editable approved amounts — rejected because they contradict automatic full
  composition and immutable snapshots.
- Compute history or drilldown from current Budget/Expense rows — rejected because later edits or
  deletion would alter the decision shown.
- Use a color-only blocked state or discard form input on error — rejected because the operation
  needs an explicit cause and correction path.

## Verification implications

- Cover exact decimal totals, zero/non-zero/compensating contributors, current selected planning,
  Plafond allocation once, and covered planning excluded from snapshot money.
- Cover fingerprint mismatch when a contributor changes without changing the total; concurrent
  confirmations; the database active-approval uniqueness backstop; and full rollback on snapshot,
  basis-lock, revision, and audit failures.
- Cover effective dates at the Tenant-local day boundary and outside the PlanningYear, future-date
  rejection, and distinct server `recorded_at`.
- Cover all four blocker groups, Actual `0.00`, soft-deleted Actual/Extra, Actual+Extra once per
  group, historical closure after reopening, redacted blockers, and final revalidation against a
  concurrent blocker.
- Cover same-Tenant allow, missing ability, inactive user/Tenant, missing/foreign Year and approval
equivalence, non-unique correlation retries, and zero success evidence for previews/failures.
- Cover overview, impact, approval detail/history, and filtered snapshot drilldown reconciliation
in both net and gross bases.
