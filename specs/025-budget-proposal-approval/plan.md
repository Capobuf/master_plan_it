# Implementation Plan: Budget Proposto e Approvazione

**Branch**: `agent/025-budget-proposal-approval` | **Date**: 2026-08-13 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/025-budget-proposal-approval/spec.md`

**Planning Status**: Phase 0 and Phase 1 complete; zero unresolved product or technical decision.
No implementation gate is claimed as executed by this planning intervention.

## Summary

Deliver one vertical Slice that derives the complete Budget Proposto from the existing authoritative
annual projection, previews its exact composition, approves it as one immutable contributor
snapshot, exposes permanent Approval history, and exceptionally annuls only the active unused
Approval after revalidating exactly four blocker groups.

The implementation replaces the temporary partial-approval bridge rather than adapting it. It
removes caller-selected amounts, mutable `Expense.approved_amount`, `initial|variation` operations,
the unique diagnostic correlation constraint, and the invalid legacy Closure entry point. It adds
an immutable Approval header/item aggregate, a deterministic contributor fingerprint, database
uniqueness for one active Approval, two narrow lifecycle identity seams for the Slice 026
Rectification/Closure writers, a four-group blocker projection that intentionally sees deleted
Actual/Extra rows, guarded approve/annul Actions, typed `/api/v1` resources, and complete React
Budget impact/history/annulment surfaces.

`PlanningYear` remains the one annual Budget and serialization root. `EconomicDatasetQuery` plus
the single `EconomicEngine` remain the only monetary formula authority. Approval and annulment use
exact decimal measures, the shared Tenant/Year guard, optimistic Budget version checks, atomic
Revision/business Audit evidence, and the existing fail-closed Tenant authorization contract.

## Technical Context

**Language/Version**: PHP 8.3.32; TypeScript 5.7.3; SQL for MySQL 8.4.10

**Primary Dependencies**: Laravel 13.22, Sanctum 4.3, BCMath, Eloquent/MySQL transactions,
overtrue/laravel-versionable 6, React 19, React Router 7.1, Axios 1.19, Tailwind CSS 4

**Storage**: MySQL 8.4.10; consolidated Greenfield annual lifecycle migration; existing
RevisionBatch/RevisionBatchItem and Audit persistence

**Testing**: Pest 4 / PHPUnit 12, real-MySQL Accounting and Application suites, Xdebug economic
line/branch coverage gate, Architecture rollback inventory, Vitest 3 + Testing Library, ESLint,
TypeScript/Vite production build

**Target Platform**: API-only Laravel service and React browser application in the existing Docker
Compose Linux environment; desktop-first Workspace with responsive light/dark behavior

**Project Type**: Multi-Tenant web application with Laravel as sole business owner and React as
presentation client

**Performance Goals**: No invented latency target. Proposal/Approval reads use one bounded annual
projection per request; history is paginated; blocker preview uses four bounded category queries;
no contributor-, dimension-, authorization-, or blocker-level N+1 query

**Constraints**: exact decimal strings/no float authority; complete annual dataset before filters;
one current projection; immutable contributor snapshots; no empty Approval; nonempty total zero
allowed; Tenant-local nonfuture effective date; exactly four non-exclusive blocker groups; deleted
Actual/Extra facts retained for blocking; Tenant then PlanningYear lock order; no preview
reservation; no new permission vocabulary; no raw destructive reset; no fallback or dual-write

**Scale/Scope**: One vertical Slice across the existing Budget/economic core, Greenfield schema,
seven canonical API routes, current/historical Budget consumers, and React Budget Workspace. No
fixed contributor, history, blocker, or Expense count is assumed.

## Authority and Planning Inputs

- Product authority: `specs/BUDGET-DOMAIN-REFINEMENT.md` plus the accepted [specification](spec.md).
- Program authority: `specs/022-application-workspace-ux/` after the five recorded PO decisions.
- Verified implementation baseline: Slice 023–024 integrated at
  `3ac7183885308501a1048805fbe64bfc65900a00`.
- Current code evidence: legacy selected-item approval, mutable Expense approval columns,
  preparation/approved/closed PlanningYear state, `AnnualEconomicMutationGuard`, authoritative
  annual projection, soft-deleted Expense/ExpenseRow storage, Tenant timezone, current abilities,
  Revision/Audit helpers, historical `as_of` view and React modal.
- The parent `/root/masterplan/AGENTS.md` describes a retired Frappe/Python repository and conflicts
  with this Laravel/React checkout. Current manifests, Compose configuration, constitution, code and
  tests therefore control. Its compatible general advice—test changes and use intentional commit
  summaries—remains applicable.
- Product Owner confirmation: no pre-feature data must be preserved. Greenfield consolidation is
  authorized only through the protected reset; it is not an application purge capability.

## Constitution Check — Pre-Design Gate

| Principle | Result | Evidence / consequence |
|---|---|---|
| Minimal permanent documentation | PASS | Target behavior remains in the active Slice artifacts until verified implementation. |
| Vertical temporary Spec Kit | PASS | Schema, Laravel domain/API, React, security, persistence and tests deliver one end-to-end decision workflow. |
| Authority on current behavior | PASS | Code/migrations/tests are classified as the legacy bridge baseline; only the 025 delta is specified. |
| Product decisions belong to PO | PASS | Automatic membership, empty/zero, zero Actual, Tenant-local date and overlapping groups are all explicitly decided. |
| Laravel sole business owner | PASS | Composition, fingerprint, state, blocker membership and authorization are server-owned. |
| Exact money/current rows | PASS | The single projection supplies exact measures; only current nondeleted contributors feed the proposal. |
| Explicit complex mutations | PASS | Dedicated approve/annul Actions own every economic/history side effect. |
| Minimum architecture | PASS | No Budget duplicate, repository, CQRS, event bus, generic blocker ledger, service locator or second engine. |
| Tenant isolation/server authorization | PASS | Existing context, abilities, scoped lookup and redaction rules protect every surface. |
| Rollback and verification | PASS | Atomic evidence, failure injection, real-MySQL concurrency and full frontend gates are planned. |

No Constitution violation requires a complexity exception. Phase 0 was allowed to proceed.

## Project Structure

### Documentation (this feature)

```text
specs/025-budget-proposal-approval/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── api-contract.md
└── checklists/
    └── requirements.md
```

`tasks.md` is Phase 2 output and is not created by `speckit-plan`.

### Source Code (repository root)

```text
app/
├── Domain/
│   ├── Budget/
│   │   ├── Actions/{ApproveBudgetProposal,AnnulBudgetApproval}.php
│   │   ├── Data/
│   │   ├── Enums/
│   │   ├── Queries/{AnnualBudgetQuery,HistoricalAnnualBudgetQuery,
│   │   │   ApprovalHistoryQuery,ApprovalSnapshotQuery,AnnulmentBlockerQuery}.php
│   │   ├── Services/{AnnualEconomicMutationGuard,BudgetProposalComposer}.php
│   │   └── Support/
│   ├── Economics/
│   ├── Expenses/
│   ├── Reporting/
│   └── Revisions/
├── Http/
│   ├── Controllers/Api/V1/
│   └── Resources/Api/V1/
└── Models/{PlanningYear,BudgetApproval,BudgetApprovalItem,
    BudgetRectification,BudgetClosure}.php

database/
├── migrations/2026_08_09_100001_add_annual_budget_lifecycle.php
├── factories/
└── seeders/

routes/api/v1/reporting.php

frontend/src/
├── api/budget.ts
├── components/budget/
├── pages/Budget/
├── navigation/
└── presentation/

tests/
├── Accounting/Integration/
├── Architecture/
├── Feature/Api/Budget/
├── Feature/Budget/
└── Support/
```

**Structure Decision**: Retain the existing Laravel/React application and Budget bounded context.
Add the smallest explicit proposal/approval services, immutable models, controller/resource types and
React components required. Do not create a separate Budget table, projection engine, generic event
store, client state engine or parallel historical subsystem.

## Phase 0 Output — Research

[research.md](research.md) resolves thirteen implementation decisions:

1. contributor-only immutable snapshot, with one ordinary planning row or one aggregate Plafond
   allocation per canonical contributor;
2. versioned canonical contributor fingerprint, derived totals/count and no preview reservation;
3. protected Greenfield replacement of the legacy bridge with no dual-write/backfill;
4. generated active-year slot plus database uniqueness for one active Approval;
5. Tenant-local calendar validation separate from server UTC `recorded_at`;
6. four dedicated blocker reads including Trash and cross-group overlap;
7. two narrow append-only Rectification/Closure identity seams for Slice 026;
8. existing ability reuse, authorized detail and blocking redaction;
9. scoped lookup, shared guard and deterministic error precedence;
10. diagnostic, indexed, nonunique Correlation IDs without replay;
11. atomic Approval/Annullamento boundaries and evidence cardinality;
12. removal of the invalid legacy Closure bridge until Slice 026;
13. immutable overview/history/drill-down and React cutover.

There are no unresolved clarification or research markers.

## Phase 1 Outputs — Design and Contracts

- [data-model.md](data-model.md) defines the Greenfield tables, composite Tenant/Year keys,
  generated active discriminator, copied snapshot fields, exact amount checks, two lifecycle seams,
  blocker identities, transitions, lock order, immutability and Revision/Audit boundary.
- [contracts/api-contract.md](contracts/api-contract.md) defines the canonical routes, request and
  response DTOs, exact error precedence, pagination, abilities, redaction, non-disclosure,
  transaction/query-count expectations and test matrix.
- [quickstart.md](quickstart.md) defines the protected reset, canonical Net/Gross data, empty/zero,
  time-zone, snapshot/history, all blocker/nonblocker, concurrency, rollback, UI and full repository
  verification scenarios. It does not claim any command has run.

## Canonical Domain Design

### Proposal and composition evidence

`BudgetProposalComposer` consumes the complete `AnnualEconomicProjection`, never a filtered page.
It maps projection contribution decisions into exactly two contributor kinds:

- `expense-row:{row_id}` for the selected, uncovered current Estimate/Quote of an Ordinary Expense;
- `plafond-allocation:{plafond_expense_id}` for the aggregate signed Allocation of one Plafond.

Covered planning, alternatives, Actuals, deleted sources and technical history remain explanatory
exclusions. An empty contributor list—not a zero total—is the rejection criterion. Totals and count
are derived and reconciled from the contributor array.

Canonical UTF-8 JSON contains the composition schema version, Tenant/Year, currency/basis and the
bytewise source-identity-sorted frozen contributor fields. The opaque projection version is echoed
and validated separately, but is not duplicated in the digest. Keys are lexical, strings NFC,
decimals fixed at two places and null explicit. The stored/exposed value is
`sha256:<64 lowercase hex>`.
Exclusions, display filters and permission-dependent links do not enter the digest. Confirmation
rebuilds under lock and never trusts an earlier preview as authority.

### Approval aggregate

`BudgetApproval` is an immutable header and `BudgetApprovalItem` collection. The header freezes
effective date, UTC record time, actor identity/display name and note, Base/currency, exact total measures, contributor count,
versions and fingerprint. Each item freezes source identity/version, kind, copied source/dimension
IDs and labels, and exact measures. Historical IDs are intentionally not FK-bound to mutable source
records, so permanent source deletion cannot invalidate the snapshot. The only allowed header
change is terminal `active -> annulled` metadata; no generic CRUD, delete, restore or reactivation
exists.

A generated `active_planning_year_id` equals the Year only while active. Unique
`(tenant_id, active_planning_year_id)` permits unlimited annulled history but at most one active
decision. Actions additionally enforce: Preparation has none; Approved/Closed has exactly one.
Both approve and annul use `PlanningYear.lock_version`; no redundant Approval version exists.

### Blocker read model

`AnnulmentBlockerQuery` computes exactly four arrays after Tenant/Year scoping:

- `actuals`: every Actual row by existence, including zero, either sign, generated/manual and
  soft-deleted row or parent;
- `extra_budget`: every `is_extra` row, including soft-deleted row or parent;
- `rectifications`: every canonical append-only `budget_rectifications` row for the Year;
- `closures`: every canonical append-only `budget_closures` row, even after future reopening.

Deduplication key is `(group, canonical source identity)`. An Actual+Extra row appears once in each
of the first two groups with the same canonical identity. There is no global deduplication,
precedence or fifth category. Authorized items carry protected links; inaccessible same-Tenant
items use an Approval-scoped opaque HMAC alias, reveal no raw ID/label/amount/actor/link, and still
make `can_annul=false`.

The two lifecycle tables are narrow canonical seams, not a generic blocker ledger. Slice 025 owns
their schema/models/factories and read contract. Slice 026 extends them with monetary/closure
snapshot fields and owns every business writer and operation UI.

## Transaction and Lock Protocol

### Approval

1. Apply session, active user, Tenant context, active-Tenant exception, `budget.view` and
   `expense.update` before economic reads; resolve Year inside the selected Tenant.
2. Start one database transaction and acquire Tenant `FOR UPDATE`, then the selected PlanningYear
   through `AnnualEconomicMutationGuard`.
3. Reload/reauthorize the persisted actor and locked Tenant/Year; validate
   `effective_date <= today` in the locked Tenant timezone, then require `preparation`, zero active
   Approval and the expected Budget version; `recorded_at` is server UTC.
4. Compare exact schema/projection versions, discover the contributing source/dimension identities,
   and lock every referenced Expense, Row, Cost Center, Vendor, Project and Contract in deterministic
   class then PK order.
5. Rebuild the full projection/composition from the locked rows, reconcile measures, compare the
   fingerprint and only then reject count zero; do not accept items or amounts from the client.
6. Begin one Revision batch plus its inherited `revision.batch.begin` infrastructure Audit, then
   insert one active header referencing that batch and all immutable items.
7. Transition Year to `approved`, increment its version once, link that new Version to the batch,
   set `economic_basis_locked_at` only when null, write one operation-specific `budget.approved`
   business Audit and commit once.

Any error rolls back header/items, Year, first Base lock, Revision links and both evidence events.
Two approvals are ordered by the Year guard and backed by active-slot uniqueness.

### Annullamento

1. Apply the same protection and scoped Year/Approval lookup, validate declared request shape and
   normalized nonblank note.
2. Start one transaction, lock PlanningYear, then the active Approval. Tenant lock is unnecessary
   because annulment neither reads nor changes Base/timezone.
3. Require Budget `approved`, the routed Approval to be the active one and the expected Budget
   version to match.
4. Recompute all four blocker groups inside the same transaction, including deleted sources.
5. If any item exists, throw `BUDGET_APPROVAL_ANNULMENT_BLOCKED` with current authorized/redacted
   groups and zero writes.
6. Otherwise terminally mark the Approval, transition Year to `preparation`, increment its version
   once, preserve the Base lock, create one Revision/infrastructure Audit and one
   `budget.approval-annulled` business Audit, then commit once.

Every current/future writer that can create or remove Actual, Extra, Rectification or Closure
existence must acquire the same Year guard before its write. Writers needing Tenant state always
use Tenant → PlanningYear; multi-Year operations sort Years by ID. Preview and fingerprint never
reserve the dataset.

## Implementation Strategy

### Stage 1 — Consolidate the Greenfield lifecycle schema

1. Replace the legacy lifecycle portion of the consolidated migration: remove
   `expenses.approved_amount`, `approved_basis`, `approval_operations`, delta items and
   `initial|variation` semantics.
2. Create `budget_approvals` and `budget_approval_items` exactly as the data model defines, including
   exact amount/status checks, generated active discriminator, composite parent keys, nonunique
   correlation indexes and source-independent historical fields.
3. Add query-support indexes for Actual/Extra existence including soft-delete columns.
4. Add narrow `budget_rectifications` and `budget_closures` tables with Tenant/Year/Approval,
   actor, revision, correlation and stable operation identity. Do not add writers or monetary
   formulas owned by 026.
5. Update models, enums, casts, factories and demo fixtures. Make approval/item lifecycle methods
   reject generic update/delete/restore and preserve immutable copied labels.
6. Prove constraints and duplicate correlation behavior on real MySQL after the protected reset.

### Stage 2 — Compose the complete proposal once

1. Add immutable contributor/exclusion/composition DTOs and `BudgetProposalComposer` around the
   existing projection; do not copy calculation formulas.
2. Aggregate every Plafond's signed Allocation into one contributor; never snapshot each adjustment
   or covered planning as another approved amount.
3. Implement canonical serializer/fingerprint with fixed versions and deterministic tests for key,
   array, Unicode, decimal and null normalization.
4. Reconcile contributor sums to projection/header totals before preview and persistence; fail with
   `ECONOMIC_RECONCILIATION_FAILED`, never fallback.
5. Refactor current/historical `AnnualBudgetQuery` to read approved planned values solely from the
   active/stored snapshot while keeping current evaluations and Actuals separate.

### Stage 3 — Implement Approval reads and mutation

1. Replace `ApplyBudgetApproval`/legacy DTOs with preview query and `ApproveBudgetProposal` Action
   following the transaction protocol. Remove selected Expense locks, caller amounts, mutable
   Expense writes and variation classification.
2. Add paginated history and immutable snapshot detail queries/resources with bounded eager loads
   and source-independent drill-down labels.
3. Add canonical controller/routes and permanently remove
   `/budget/{planningYear}/approval-decisions`.
4. Preserve `GET /budget` current and inherited read-only `as_of` behavior; historical views never
   expose mutation actions.
5. Add stable `BUDGET_PROPOSAL_EMPTY`, `BUDGET_COMPOSITION_STALE`, `BUDGET_STATE_CONFLICT`,
   `STALE_VERSION` and reconciliation mappings in the existing common envelope.

### Stage 4 — Implement blocker projection and Annullamento

1. Add four bounded Tenant/Year queries with explicit `withTrashed`/scope bypass only for
   Actual/Extra blocker facts. Never reuse the current projection for Trash evidence.
2. Generate canonical unredacted identities and Approval-scoped HMAC aliases for redacted items;
   authorize source detail in batches, not per row.
3. Implement active-Approval annulment preview, authorized tombstone/operation blocker drill-down,
   and `AnnulBudgetApproval` with final under-lock revalidation.
4. Preserve error precedence: authentication/context → endpoint abilities → Tenant-scoped root
   lookup → request validation → locked state → `STALE_VERSION` → composition or blocker domain result.
   Simultaneous stale/new blocker returns stale first; refreshed submission exposes the blocker.
5. Register Actions in rollback/architecture inventories and prove exact Revision/business Audit
   cardinality plus inherited revision evidence.

### Stage 5 — Cut over the React Budget Workspace

1. Replace partial-selection API types/adapters with overview, impact, approve, history/detail,
   annulment and blocker types. Reject any retained `items`/`approved_amount` compatibility fallback.
2. Replace checkboxes/editable approved amounts with a complete Impact view showing Base,
   Net/IVA/Gross/official total, contributors, exclusions/reasons and authorized links.
3. Use Tenant timezone to mirror the server's nonfuture DatePicker bound; server remains authority.
   Preserve date and optional note across validation/stale failures.
4. Show immutable planned snapshot, current informative evaluations and Actual separately in the
   overview; history/detail always read stored items.
5. Add Annullamento preview/modal with exactly four labelled groups, shared identity indication for
   overlap, tombstone/redaction presentation, mandatory note and no executable confirmation when
   blocked.
6. Remove the legacy Close button/API/action route. Slice 026 will introduce the only target
   Close/Reopen workflow backed by the canonical Closure table; do not permit Preparation→Closed.
7. Preserve dirty/context guards, keyboard focus, text-not-color state, responsive layout and dark
   tokens using existing TailAdmin primitives.

### Stage 6 — Verify and document the delivered vertical story

1. Add pure composer/fingerprint tests and include any modified economic branch in the versioned
   economic coverage manifest.
2. Add real-MySQL schema, aggregate, rollback and two-connection concurrency tests for double
   approval, approval vs same-total change, annulment vs each blocker, active uniqueness and Base
   rollback.
3. Add HTTP tests for every route/DTO/error, empty/nonempty-zero, date boundaries, pagination,
   unknown fields, legacy route absence, abilities, inactive actors/Tenants, foreign/missing
   equivalence, redaction and reused correlation IDs.
4. Add consumer reconciliation/query-count tests for current overview, impact, snapshot history,
   drill-down and inherited historical view in Net/Gross Bases.
5. Replace legacy frontend tests and add interaction tests for retained inputs, overlap groups,
   blocked action absence, history immutability, accessibility and context preservation.
6. Execute [quickstart.md](quickstart.md), full backend/frontend gates and proportional manual
   browser checks. Record actual evidence only after execution.
7. After implementation is verified, update only permanent current-state/OpenAPI/operations/status
   documentation, commit the Slice deliberately, integrate locally, and leave push/PR/remote merge
   untouched.

## API and Error Precedence

Canonical routes are exactly those in [contracts/api-contract.md](contracts/api-contract.md):

```text
GET  /api/v1/budget?planning_year_id=&as_of=
GET  /api/v1/budget/{planningYear}/approval-preview
POST /api/v1/budget/{planningYear}/approve
GET  /api/v1/budget/{planningYear}/approvals
GET  /api/v1/budget/{planningYear}/approvals/{approval}
GET  /api/v1/budget/{planningYear}/approvals/{approval}/annulment-preview
POST /api/v1/budget/{planningYear}/approvals/{approval}/annul
GET  /api/v1/budget/{planningYear}/approvals/{approval}/blockers/{sourceIdentity}
```

The first path is an existing current/historical read surface; the remaining seven are the 025
delta. Reads require `budget.view`; approve/annul require both `budget.view` and `expense.update`.
No permission names are added.

Missing and foreign IDs of the same class return the same `404 RESOURCE_NOT_FOUND` envelope. The
server never leaks foreign state, totals, actors, counts, source identities or blocker existence.
For matching scoped mutations, validation precedes locked business decisions; locked state and
Budget version precede composition/blocker errors. Diagnostic correlation reuse is legal and never
replays a result.

## Test Strategy and Required Evidence

### Pure and schema contracts

- canonical contributor selection, aggregate Plafond, exclusions and fingerprint normalization;
- empty vs all-zero/offsetting composition;
- exact Net/VAT/Gross/official sum and mismatch failure;
- generated active uniqueness, terminal matrix, composite Tenant/Year keys, no mutable source FK,
  diagnostic correlation nonuniqueness and immutable models.

### Application and HTTP contracts

- full automatic Approval, nonfuture Tenant-local effective date, first Base lock and rollback;
- immutable history after current source edits/deletion, annul/reapprove, pagination and `as_of`;
- four exact blocker arrays, zero/manual/contract/deleted Actual, deleted Extra, Rectification,
  Closure-after-reopen fixture, Actual+Extra once in both groups and nonblockers;
- same-Tenant allow, both mutation abilities, missing ability, inactive account/Tenant, protected
  Platform Administrator path, foreign/missing equivalence and redaction;
- success business Audit/Revision cardinality, shared revision Audit, zero evidence on preview/error.

### Real-MySQL concurrency and rollback

- two simultaneous Approvals produce at most one active decision;
- Approval vs contributor change observes a complete before/after composition, including same-total
  changes;
- first Approvals in different Tenant Years serialize through the Tenant Base lock;
- favorable annul preview vs Actual, Extra, Rectification and Closure writer is linearizable;
- failures at snapshot item, state, Base, Revision and Audit boundaries restore every affected row.

### React and consumer parity

- no selection/editable amount bridge remains;
- overview/impact/snapshot/history/drill-down reconcile in Net and Gross;
- date and notes survive recoverable errors; stale data requests re-review;
- exactly four blocker groups, overlap identity, redaction/tombstone and disabled/absent action;
- responsive/light/dark/keyboard/text-labelled behavior and no console/runtime error;
- frontend adapters reject legacy shapes at compile/test time.

## Dependencies and Ownership Handoff

- Slice 025 depends only on integrated Slice 023–024 contracts and is independently deliverable.
- Slice 026 receives immutable Approval/history, active snapshot queries, the shared Year guard, and
  the narrow `budget_rectifications`/`budget_closures` schemas. It owns all Extra/Rectification
  writers, monetary deltas, Close/Reopen snapshots/actions/UI and final Budget. It must acquire the
  same guard and may extend, not replace, the seams.
- Slice 029 may later alter the current proposal through its persistent annual composition rules,
  before a future preview. It cannot reintroduce client-selected Approval items or rewrite a stored
  snapshot.
- Slice 030 owns Trash/restore/permanent-delete UX and must preserve snapshot independence plus the
  durable blocker semantics of deleted Actual/Extra facts.
- Contract/Project writers in later Slices must use the same annual serialization guard whenever
  they can create a blocker.

## Constitution Check — Post-Design Gate

| Principle | Result | Design evidence |
|---|---|---|
| One vertical outcome | PASS | Proposal, decision, immutable history and guarded annulment span every required layer. |
| One business owner/engine | PASS | Laravel composer consumes `AnnualEconomicProjection`; React and snapshots do not calculate authority. |
| Exact money | PASS | Four DECIMAL/string measures are copied/reconciled without float or fallback. |
| Tenant isolation | PASS | Composite scope, pre-read abilities, foreign/missing equivalence and redaction are explicit. |
| Explicit Actions/atomicity | PASS | Two Actions and one shared guard own complete transactional effects. |
| Minimum architecture | PASS | No Budget duplicate, generic event/blocker table, repository, event bus or second engine. |
| Current-row invariant | PASS | Only current nondeleted contributors feed proposal; Trash bypass is isolated to blocker evidence. |
| Testability | PASS | Every acceptance risk has a concrete schema, application, HTTP, concurrency or React gate. |
| No product invention | PASS | Every visible behavior traces to the five final PO decisions or inherited verified contract. |

No post-design Constitution exception or unresolved clarification remains.

## Complexity Tracking

No Constitution violation requires justification. The two narrow lifecycle identity seams are not
an architectural exception: they are the minimum concrete typed sources necessary for the four
already approved blocker groups and are extended by their owning Slice 026.
