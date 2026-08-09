# Implementation plan — Feature 017 Budget annuale, approvazioni e ciclo Spese

**Branch**: `laravel-replatform`  
**Spec**: `specs/017-budget-annual-approval-and-expense-lifecycle/spec.md`  
**Baseline**: `laravel-replatform@8f0f5660b409b562d354589d9e00012f31df8ef2`

## Technical context

- Backend: PHP 8.3.32, Laravel 13.22, Sanctum SPA, Spatie Permission 8.3.
- Persistence: MySQL 8.4, InnoDB, strict mode, forward-only migrations.
- Money: decimal strings and BCMath; Tenant `budget_basis` is frozen once approvals exist.
- Revisions: existing `overtrue/laravel-versionable` 6.0.0 snapshots plus application
  `RevisionBatch`/`RevisionBatchItem`; no second history package.
- Frontend: React 19, TypeScript, React Router and TailAdmin React Free 2.3.0.
- Tests: Pest/PHPUnit Architecture, Accounting and Application suites; persistent guarded MySQL test DB.
- New dependencies: none.
- API contract: feature-local contract only; the repository has no complete global OpenAPI document.

## Constitution Check

| Principle | Pre-design | Design evidence |
|---|---|---|
| Vertical, temporary Spec Kit | PASS | Every story crosses persistence, domain, HTTP, React and tests where applicable |
| Current code is authority for implemented behavior | PASS | Baseline code, migrations, routes, UI and 206 focused tests were inspected |
| Product decisions are not invented | PASS | Five high-impact gaps were clarified with the Product Owner and recorded in spec.md |
| Laravel owns business logic | PASS | Selection, approvals, lifecycle, exact formulas and history remain server-side |
| Tenant isolation fail-closed | PASS | Composite references, scoped resolution and foreign-ID 404 behavior are mandatory |
| Exact money, one economic kernel | PASS | Existing BCMath path is refactored in place; React performs no authoritative calculation |
| Explicit Actions | PASS | Approval, close, move and contract generation use focused transactional Actions |
| No silent fallback | PASS | Pre-activation historical cutoff and incompatible migration rows fail visibly |
| TailAdmin reuse | PASS | UI composes existing primitives and application components |
| Tests match real suite | PASS | TDD tasks and Compose verification commands are explicit |

No constitution exception is required.

## Architecture

```text
PlanningYear (annual Budget state)
  ├── Expense (selected plan, approved, lifecycle, links)
  │     └── ExpenseRow (Estimate / Quote / immediately-effective Actual)
  ├── ApprovalOperation ── ApprovalItem[]
  └── RevisionBatch ── RevisionBatchItem[] (upsert/delete, annual scope)
          ↓
AnnualBudgetDatasetQuery + EconomicEngine
          ↓
/api/v1/budget, /expenses, /reports, /contracts
          ↓
React/TailAdmin current and read-only historical surfaces
```

## Persistence and migration

- Extend `planning_years`, which already uniquely identifies Tenant/year, with `budget_state` and
  `history_activated_at`; keep `active` separate from Budget lifecycle.
- Add Contract `project_id` and remove the Expense Project/Contract XOR database constraint.
- Extend Expense with `approved_amount`, `approved_basis`, `state`, nullable `closure_outcome`,
  `current_planning_row_id`, and self-links for movement/credit lineage.
- Retain legacy confirmation/period columns for forward data compatibility but remove them from all new
  request/response/domain semantics. New Actual rows are immediately authoritative and require a same-year
  `spend_date`; new planning rows may omit a date. Legacy unchanged period rows remain readable but are not
  redistributed by the new monthly view.
- Convert only contract-generated, system-managed, non-overridden `Actual ToConfirm` rows to Quote planning;
  preserve user-authoritative/confirmed Actual rows as actual economic events.
- When exactly one planning candidate exists, backfill it as current. Multiple candidates remain unselected
  and require an explicit VCO choice.
- Create immutable `approval_operations` and `approval_items` with tenant/year scope and exact deltas.
- Add `mutation` and annual scope metadata to revision batch items. Create one activation baseline batch per
  Tenant/year; no cutoff earlier than `history_activated_at` is accepted.

## Budget and Expense lifecycle

- `PlanningYear` becomes Versionable. `budget_state` values are `preparation`, `approved`, `closed`.
- Expense state values are `open`, `closed`; closure outcome is nullable or one of `not_incurred`,
  `cancelled`, `moved`.
- Closing is explicit. Economic changes enumerated in FR-006 reopen a closed Expense; descriptive changes do
  not. Any mutation under a closed Budget succeeds, keeps it closed and returns `BUDGET_CLOSED` warning.
- `current_planning_row_id` selects at most one current non-deleted Estimate/Quote belonging to the Expense.
  Generic Expense writes cannot change `approved_amount`.
- Movement is one transaction: lock source and target year, close origin as moved, create linked destination,
  copy planning context but not Actual rows or approved amount, and revision-link both records.

## Approvals

- Reuse existing configurable `expense.update` ability for approval and Expense close, and `planning-year.update`
  for Budget close. Historical Budget/Report reads reuse `budget.view`/`report.view`. This avoids inventing a
  new role distinction while preserving server authorization.
- `ApplyBudgetApproval` locks PlanningYear then Expense IDs in ascending order, validates same Tenant/year,
  nonnegative exact values and lock versions, computes previous/new/delta server-side, and persists the entire
  operation plus one revision batch atomically.
- The first operation changes `preparation` to `approved`; later operations are variations. A closed Budget
  remains closed. `null` and `0.00` remain distinct.
- Approval items snapshot Cost Center, Project, Contract, Expense kind and approved basis. Tenant budget basis
  updates are rejected once any approval exists.
- Generic updates of approval-relevant dimensions on an approved Expense are rejected; a reallocation uses the
  approval endpoint with replacement dimensions in the same atomic operation.

## Contract and Project behavior

- Contract may reference one same-Tenant current Project. Future generated annual Expense copies that Project;
  existing Expense records are never reclassified automatically.
- One Contract/year owns one stable generated Expense. Its generated Quote is the exact sum of Contract term
  occurrences falling in that year; it is a planning amount, never an Actual.
- Source keys become Contract/year stable. `is_system_managed` and `manual_override_at` remain the authority
  guard. A selected or manually changed generated planning is not overwritten; API exposes expected/current
  difference for VCO choice.
- Expense may carry both Project and Contract only when Project equals `Contract.project_id`.

## Economic kernel and reporting

- Refactor the shared dataset to one semantic line per Expense: selected planned components, approved scalar in
  frozen official basis, sum of Actual components, lifecycle and dimensions.
- Proposed excludes only closed `not_incurred`, `cancelled`, `moved`. Project/Contract state never changes
  economic inclusion.
- Approved initial derives from the first approval operation; approved variations are subsequent deltas;
  approved current comes from Expense current fields.
- Plafond stays an identifiable Expense. Referencing Expense actuals consume its allocation; covered consumption
  is not counted twice and excess stays valid and is exposed as `plafond_overrun`.
- Budget and Report use the same kernel. Report groups by cost center, project, contract, vendor or expense and
  returns exact decimal strings plus open/closed/unapproved-actual counts.

## Historical projection

- Normalize cutoff from Tenant timezone to UTC; date-only uses local end-of-day.
- Reject cutoff earlier than annual `history_activated_at` with stable `HISTORY_BEFORE_ACTIVATION`.
- Resolve snapshots by `revision_batches.occurred_at`, batch ID and item sequence, never by per-model version
  timestamp and never through `versionAt()` loops.
- Query latest complete item per subject using MySQL window functions/derived tables, then batch-fetch labels and
  relationships at the same cutoff. `mutation=delete` acts as tombstone.
- Current queries contain no joins to version tables. Historical projection is read-only and does not expose
  restore operations.
- Deterministic benchmark gates constant query count/no N+1; runtime is recorded as evidence because hosting
  thresholds are not yet approved.

## API and React

- Contracts are defined in `contracts/api-v1.md`.
- Expense editor removes confirmation and period distribution, supports optional planning date, mandatory
  same-year Actual date, planning selection and coherent Project+Contract selection.
- Budget adds state, closed warning, annual summary and atomic approval form.
- Expense detail adds lifecycle actions, approved/planned/actual comparison and revision activity.
- Budget/Report add current/as-of control and a read-only historical banner. Report provides all five grouping
  dimensions. All currency math remains response data from Laravel.

## Test strategy

- Test-first phases cover schema/backfill, exact formulas, tenant/ability matrices, rollback, stale locks,
  Contract annual planning, lifecycle/move and historical projection.
- Existing tests that assert confirmation, XOR, period distribution and Project-stage buckets are rewritten to
  the approved behavior rather than preserved as compatibility contracts.
- Full verification uses Compose runtime and never resets the persistent database destructively.

## Documentation propagation

After successful implementation and verification, update only durable rules in `docs/DOMAIN.md`, architecture
constraints in `docs/ARCHITECTURE.md`, real commands in `docs/OPERATIONS.md`, and the concise implementation
matrix in `docs/STATUS.md`. Reconcile active Specs 009/011/012/013 by documenting dependencies or superseded
scope; do not silently delete their Product Owner decisions.

## Post-design Constitution Check

PASS with zero exceptions. The design is vertical, tenant-safe, exact-decimal, fail-visible, uses explicit
Actions and one economic kernel, and separates current domain, approval history, operational revisions and
read-only historical projection.
