---

description: "Dependency-ordered implementation tasks for Slice 025 Budget Proposto e Approvazione"
---

# Tasks: Budget Proposto e Approvazione

**Input**: Design documents from `specs/025-budget-proposal-approval/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [API contract](contracts/api-contract.md), [quickstart.md](quickstart.md)

**Tests**: Required. The specification explicitly requires independently verifiable acceptance,
real-MySQL concurrency, rollback, Tenant isolation, exact decimal reconciliation and React
interaction coverage. Within each story, write the listed tests first and prove the intended target
assertions are red before implementing that story.

**Organization**: Tasks are grouped by user story. Shared schema, immutability, authorization and
error foundations are completed once; each story then has an independent fixture and verification
checkpoint.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Safe to execute in parallel because the task owns different files and has no dependency
  on another incomplete task in the same phase.
- **[Story]**: Maps directly to the seven stories in `spec.md`.
- Every task names its concrete repository path(s).

## Phase 1: Setup — Target Test Scaffolding

**Purpose**: Establish reusable deterministic fixtures and a red cutover contract without changing
production behavior.

- [X] T001 Create the canonical Net/Gross, empty, all-zero, offsetting, Plafond and Tenant-timezone fixture builder in `tests/Support/BudgetApprovalFixture.php`
- [X] T002 [P] Create typed React proposal/approval/history/blocker fixtures with raw and redacted overlap cases in `frontend/src/components/budget/__fixtures__/budgetApproval.ts`
- [X] T003 [P] Add a red architecture cutover contract forbidding the legacy partial-approval route/types/columns and requiring the target aggregate/actions in `tests/Architecture/BudgetApprovalTargetContractTest.php`

**Checkpoint**: Shared fixtures compile independently; the target architecture contract is red only
for artifacts deliberately not implemented yet.

---

## Phase 2: Foundational — Greenfield Lifecycle and Shared Contracts

**Purpose**: Replace the legacy persistence/transport foundation and define invariants that block
all user-story work.

**⚠️ CRITICAL**: No user story implementation begins until this phase is complete.

### Foundational tests

- [X] T004 [P] Add real-MySQL schema tests for immutable approval tables, active-year uniqueness, terminal status matrix, exact measures, source-independent snapshot IDs, nonunique correlations and two lifecycle seams in `tests/Feature/Budget/AnnualBudgetSchemaTest.php`
- [X] T005 [P] Add model immutability tests rejecting generic update/delete/restore/reactivation of approval items and protected header fields in `tests/Feature/Budget/BudgetApprovalModelImmutabilityTest.php`
- [X] T006 [P] Add stable error-envelope tests for `BUDGET_PROPOSAL_EMPTY`, `BUDGET_COMPOSITION_STALE` and `BUDGET_APPROVAL_ANNULMENT_BLOCKED` in `tests/Feature/Api/Budget/BudgetApprovalErrorContractTest.php`

### Foundational implementation

- [X] T007 Replace the partial bridge schema with `budget_approvals`, `budget_approval_items`, `budget_rectifications` and `budget_closures`, remove mutable Expense approval columns, add blocker indexes and drop unique correlation in `database/migrations/2026_08_09_100001_add_annual_budget_lifecycle.php`
- [X] T008 [P] Add `BudgetApprovalStatus` and contributor-kind enums and remove `ApprovalKind`/partial-change DTOs in `app/Domain/Budget/Enums/BudgetApprovalStatus.php`, `app/Domain/Budget/Enums/ApprovalContributorKind.php`, `app/Domain/Budget/Enums/ApprovalKind.php`, `app/Domain/Budget/Data/ApplyApprovalData.php` and `app/Domain/Budget/Data/ApprovalChangeData.php`
- [X] T009 Implement immutable casts, relationships and guarded lifecycle methods in `app/Models/BudgetApproval.php`, `app/Models/BudgetApprovalItem.php`, `app/Models/BudgetRectification.php`, `app/Models/BudgetClosure.php` and `app/Models/PlanningYear.php`, then remove `app/Models/ApprovalOperation.php` and `app/Models/ApprovalItem.php`
- [X] T010 [P] Implement valid Greenfield factories for active/annulled Approval headers, snapshot contributors, Rectification identities and Closure identities in `database/factories/BudgetApprovalFactory.php`, `database/factories/BudgetApprovalItemFactory.php`, `database/factories/BudgetRectificationFactory.php` and `database/factories/BudgetClosureFactory.php`
- [X] T011 Remove `approved_amount`/`approved_basis` fillable fields, casts, fixtures and any mutable approval relation from `app/Models/Expense.php`, `database/factories/ExpenseFactory.php` and `database/seeders/DemoDataSeeder.php`
- [X] T012 Add stable domain-code mappings, HTTP status, safe details and Italian messages to `app/Support/Api/ApiErrorResponse.php` without changing the inherited envelope or correlation behavior
- [X] T013 Enforce the existing `budget.view` read and combined `budget.view` + `expense.update` mutation checks without adding abilities in `app/Policies/ExpensePolicy.php` and `app/Http/Middleware/AuthorizeApplicationAbility.php`
- [X] T014 Remove the invalid legacy Close route/controller exposure and server Action until Slice 026 in `routes/api/v1/reporting.php`, `app/Http/Controllers/Api/V1/BudgetLifecycleController.php` and `app/Domain/Budget/Actions/CloseAnnualBudget.php`
- [X] T015 Update baseline lifecycle/schema tests to reject Preparation→Closed and the legacy close/approval bridge in `tests/Feature/Api/Budget/BudgetLifecycleApiTest.php`, `tests/Feature/Api/Budget/BudgetApprovalApiTest.php` and `tests/Feature/Budget/AnnualBudgetLifecycleTest.php`

**Checkpoint**: Protected Greenfield reset builds only the target schema; legacy persistence and
Closure entry points are absent; schema/error/immutability tests pass.

---

## Phase 3: User Story 1 — Comprendere il Budget Proposto (Priority: P1) 🎯 MVP

**Goal**: Show the complete server-derived proposal, exact totals, contributors, explanatory
exclusions and authorized source links without manual membership decisions.

**Independent Test**: Build Estimate `100`, current Quote `120`, Plafond Allocation `3500`, covered
planning `4200` and deleted input; preview and overview include Quote plus Allocation once, explain
the rest, ignore presentation filters and reconcile Net/Gross to the cent.

### Tests for User Story 1

- [ ] T016 [P] [US1] Add table-driven contributor/exclusion/empty/zero/Plafond composer tests in `tests/Accounting/Unit/BudgetProposalComposerTest.php`
- [ ] T017 [P] [US1] Add deterministic canonical JSON/fingerprint tests for ordering, NFC strings, nulls, fixed decimals, source versions and same-total dimension changes in `tests/Accounting/Unit/BudgetProposalFingerprintTest.php`
- [ ] T018 [P] [US1] Add Net/Gross full-dataset and presentation-filter reconciliation across composer, Preparation overview and approval preview, with an explicit numeric fixed-query budget that remains constant as contributors/exclusions/dimensions grow, in `tests/Accounting/Integration/BudgetProposalApprovalDatasetTest.php`, `tests/Accounting/Integration/BudgetProposalSurfaceQueryCountTest.php` and `tests/Accounting/Integration/BudgetApprovalSurfaceReconciliationTest.php`
- [ ] T019 [P] [US1] Add overview/approval-preview HTTP contract tests for complete DTOs, empty composition, unknown fields, authorized links, legacy field absence and zero Approval/Revision/Audit/state/version/Base effects in `tests/Feature/Api/Budget/BudgetProposalApprovalApiTest.php`
- [ ] T020 [P] [US1] Add React impact/overview tests for totals, inclusion/exclusion reasons, Plafond counted once, context-preserving links and no selection controls in `frontend/src/components/budget/BudgetProposalImpact.test.tsx` and `frontend/src/components/budget/BudgetView.test.tsx`

### Implementation for User Story 1

- [ ] T021 [P] [US1] Add immutable contributor, exclusion, composition-evidence and proposal DTOs in `app/Domain/Budget/Data/ApprovalContributor.php`, `app/Domain/Budget/Data/ApprovalExclusion.php`, `app/Domain/Budget/Data/BudgetCompositionEvidence.php` and `app/Domain/Budget/Data/BudgetProposal.php`
- [ ] T022 [US1] Implement projection-owned contributor mapping, aggregate Plafond allocation, explanatory exclusions and exact reconciliation in `app/Domain/Budget/Services/BudgetProposalComposer.php`
- [ ] T023 [US1] Implement versioned canonical serialization and `sha256:<hex>` evidence in `app/Domain/Budget/Services/BudgetProposalFingerprint.php`
- [ ] T024 [US1] Refactor Preparation overview to expose proposal/evaluations/Actuals without legacy approval fields or filtered recomputation in `app/Domain/Budget/Queries/AnnualBudgetQuery.php`
- [ ] T025 [US1] Add read-only approval impact query that obtains one internally consistent snapshot without locks or reservation, with bounded dimension/link authorization loading, in `app/Domain/Budget/Queries/BudgetApprovalPreviewQuery.php`
- [ ] T026 [P] [US1] Add proposal, contributor, exclusion and composition resources in `app/Http/Resources/Api/V1/BudgetProposalResource.php`, `app/Http/Resources/Api/V1/ApprovalContributorResource.php` and `app/Http/Resources/Api/V1/ApprovalExclusionResource.php`
- [ ] T027 [US1] Add `GET /budget/{planningYear}/approval-preview`, preserve `GET /budget?planning_year_id=&as_of=`, and reject unsupported preview query fields in `app/Http/Controllers/Api/V1/BudgetApprovalController.php` and `routes/api/v1/reporting.php`
- [ ] T028 [P] [US1] Replace legacy frontend Budget DTOs with strict proposal/composition/contributor/exclusion types and adapters in `frontend/src/api/budget.ts` and `frontend/src/api/budget.test.ts`
- [ ] T029 [US1] Implement complete Impact presentation and update the Preparation overview with existing TailAdmin primitives in `frontend/src/components/budget/BudgetProposalImpact.tsx` and `frontend/src/components/budget/BudgetView.tsx`
- [ ] T030 [US1] Make the prewritten T018 composer/Preparation-overview/approval-preview fixed-query-count and reconciliation gates green by batching proposal/dimension loading and removing filtered recomputation in `app/Domain/Budget/Queries/BudgetApprovalPreviewQuery.php`, `app/Domain/Budget/Queries/AnnualBudgetQuery.php` and their Resources; snapshot/history/drill-down remain the explicit T044/T054 gate after US3 creates them

**Checkpoint**: User Story 1 is independently usable and testable; no Approval write is required to
understand the proposal.

---

## Phase 4: User Story 2 — Approvare una Fotografia Completa (Priority: P1)

**Goal**: Confirm the reviewed automatic composition as one atomic immutable active Approval,
including effective date, Base lock, Revision and Audit.

**Independent Test**: Approve a multi-contributor preview without item/amount input; verify one
active header and exact items, reject empty/future/stale/double approval, accept nonempty total zero,
and roll back every side effect at each injected failure boundary.

### Tests for User Story 2

- [ ] T031 [P] [US2] Add Action tests for complete contributor snapshot, empty/all-zero/offsetting proposal, Tenant-local date boundaries and immutable copied actor/dimensions in `tests/Feature/Budget/BudgetProposalApprovalTest.php`
- [ ] T032 [P] [US2] Extend approve HTTP tests for exact request allowlist, no `items`/amounts, `201` DTO, state errors, reused correlation and Tenant-local future rejection in `tests/Feature/Api/Budget/BudgetProposalApprovalApiTest.php`
- [ ] T033 [P] [US2] Add failure-injection tests at header/item, state, first Base lock, Revision link and business Audit boundaries in `tests/Feature/Budget/BudgetProposalApprovalRollbackTest.php`
- [ ] T034 [P] [US2] Add two-connection real-MySQL tests with 20 controlled preview-to-confirm contributor collisions (including same-total change), double confirmation, first approvals across two Tenant Years, and a concurrent annual writer proving preview neither locks nor reserves in `tests/Accounting/Integration/BudgetProposalApprovalConcurrencyTest.php`
- [ ] T035 [P] [US2] Replace modal tests with full-impact confirmation, Tenant-timezone max date, optional note and retained input on future/stale error in `frontend/src/components/budget/BudgetApprovalModal.test.tsx`

### Implementation for User Story 2

- [ ] T036 [P] [US2] Add strict approve command DTO and Tenant-local date validator in `app/Domain/Budget/Data/ApproveBudgetProposalData.php` and `app/Domain/Budget/Services/ApprovalEffectiveDateValidator.php`
- [ ] T037 [US2] Implement Tenant→PlanningYear guarded rebuild, version/fingerprint checks, empty detection, immutable header/items, first Base lock and atomic evidence in `app/Domain/Budget/Actions/ApproveBudgetProposal.php`
- [ ] T038 [US2] Implement exact approve validation/response and `POST /budget/{planningYear}/approve` in `app/Http/Controllers/Api/V1/BudgetApprovalController.php`, `app/Http/Resources/Api/V1/BudgetApprovalSummaryResource.php` and `routes/api/v1/reporting.php`
- [ ] T039 [US2] Update Approved overview to source planned totals solely from the active immutable snapshot while preserving current evaluations/Actuals in `app/Domain/Budget/Queries/AnnualBudgetQuery.php` and `app/Http/Resources/Api/V1/AnnualBudgetResource.php`
- [ ] T040 [P] [US2] Implement strict approve API call and error preservation without legacy payload fallbacks in `frontend/src/api/budget.ts` and `frontend/src/api/budget.test.ts`
- [ ] T041 [US2] Replace the selected-item editor with effective-date/note/composition confirmation and accessible stale re-review behavior in `frontend/src/components/budget/BudgetApprovalModal.tsx`
- [ ] T042 [US2] Remove `ApplyBudgetApproval`, partial DTOs, variation enum, legacy route method and downstream mutable field use from `app/Domain/Budget/Actions/ApplyBudgetApproval.php`, `app/Http/Controllers/Api/V1/BudgetLifecycleController.php`, `app/Domain/Budget/Queries/AnnualBudgetQuery.php` and `routes/api/v1/reporting.php`
- [ ] T043 [US2] Register `ApproveBudgetProposal` and its failure checkpoints in `tests/Architecture/Fixtures/domain-write-rollback-map.php` and make `tests/Architecture/WriteRollbackCoverageTest.php` green

**Checkpoint**: User Stories 1 and 2 work end to end; exactly one complete decision can become
active and every failure is side-effect free.

---

## Phase 5: User Story 3 — Conservare il Previsto Immutabile (Priority: P1)

**Goal**: Read the active or historical approved plan from copied snapshot content after current
source values, labels or deletion change.

**Independent Test**: Approve `120`, change current planning to `135` and rename dimensions; current
overview keeps approved `120`, history/detail preserves original content and filtered sums reconcile
in the recorded Base.

### Tests for User Story 3

- [ ] T044 [P] [US3] Add immutable snapshot/history tests for source edits, dimension renames, soft deletion, source-independent copied actor labels, Net/Gross surface reconciliation and bounded query counts in `tests/Feature/Budget/BudgetApprovalSnapshotTest.php`, `tests/Accounting/Integration/BudgetApprovalSurfaceReconciliationTest.php` and `tests/Accounting/Integration/BudgetProposalSurfaceQueryCountTest.php`
- [ ] T045 [P] [US3] Add paginated list/detail, foreign/missing and immutable DTO HTTP tests in `tests/Feature/Api/Budget/BudgetApprovalHistoryApiTest.php`
- [ ] T046 [P] [US3] Extend historical `as_of` tests so target snapshots—not legacy operations or mutable Expenses—supply approved planned values in `tests/Feature/Budget/HistoricalBudgetQueryTest.php` and `tests/Feature/Api/Budget/HistoricalBudgetApiTest.php`
- [ ] T047 [P] [US3] Add React history/detail/filter/drill-down tests for active and annulled decisions in `frontend/src/components/budget/BudgetApprovalHistory.test.tsx`

### Implementation for User Story 3

- [ ] T048 [P] [US3] Implement paginated scoped history and immutable detail queries with bounded eager loading in `app/Domain/Budget/Queries/ApprovalHistoryQuery.php` and `app/Domain/Budget/Queries/ApprovalSnapshotQuery.php`
- [ ] T049 [P] [US3] Add history page, detail and immutable contributor resources in `app/Http/Resources/Api/V1/BudgetApprovalCollection.php`, `app/Http/Resources/Api/V1/BudgetApprovalDetailResource.php` and `app/Http/Resources/Api/V1/BudgetApprovalItemResource.php`
- [ ] T050 [US3] Add `GET /approvals` and `GET /approvals/{approval}` with pagination allowlists and Tenant-scoped nested lookup in `app/Http/Controllers/Api/V1/BudgetApprovalController.php` and `routes/api/v1/reporting.php`
- [ ] T051 [US3] Refactor historical reconstruction to the target Approval aggregate and remove `approval_operations` assumptions in `app/Domain/Budget/Queries/HistoricalAnnualBudgetQuery.php`
- [ ] T052 [P] [US3] Add strict history/detail frontend adapters and pagination types in `frontend/src/api/budget.ts` and `frontend/src/api/budget.test.ts`
- [ ] T053 [US3] Implement immutable Approval history, detail and stored-item filtering in `frontend/src/components/budget/BudgetApprovalHistory.tsx`, `frontend/src/components/budget/BudgetApprovalDetail.tsx` and `frontend/src/components/budget/BudgetView.tsx`
- [ ] T054 [US3] Run and make green the prewritten T018/T044 Net/Gross overview/snapshot/history/drill-down reconciliation and bounded-query gates by correcting only their owning query/resource implementations

**Checkpoint**: Approved planned values remain reconstructible without live fields or a Revision
chain; history is paginated and independently testable.

---

## Phase 6: User Story 4 — Annullare un'Approvazione Non Utilizzata (Priority: P1)

**Goal**: Annul only the active Approval of an Approved Budget when all four blocker groups are
empty, preserving history and the irreversible Base lock.

**Independent Test**: Preview four empty arrays, annul with normalized nonblank note and current
Budget version, verify Preparation plus terminal history/Revision/Audit/Base, then reapprove into a
new distinct snapshot.

### Tests for User Story 4

- [ ] T055 [P] [US4] Add allowed-annul Action tests for note normalization, active-only state, Budget version, immutable original fields, Base preservation and reapproval in `tests/Feature/Budget/BudgetApprovalAnnulmentTest.php`
- [ ] T056 [P] [US4] Add annulment preview/final HTTP tests for exact four empty arrays, required note, current Budget version, historical target rejection and response DTOs in `tests/Feature/Api/Budget/BudgetApprovalAnnulmentApiTest.php`
- [ ] T057 [US4] Add failure-injection tests for terminal header write, Budget state, Revision and business Audit rollback in `tests/Feature/Budget/BudgetProposalApprovalRollbackTest.php`
- [ ] T058 [P] [US4] Add React allowed-annul tests for required note, preserved Unicode/internal spaces, successful state/history refresh and historical action absence in `frontend/src/components/budget/BudgetAnnulmentModal.test.tsx`

### Implementation for User Story 4

- [ ] T059 [P] [US4] Add exact four-group result/item DTOs and source identity value object in `app/Domain/Budget/Data/AnnulmentBlockers.php`, `app/Domain/Budget/Data/AnnulmentBlocker.php` and `app/Domain/Budget/Data/BlockerSourceIdentity.php`
- [ ] T060 [US4] Implement Tenant/Year blocker reads over Actual, Extra, Rectification and Closure sources with intra-group deduplication in `app/Domain/Budget/Queries/AnnulmentBlockerQuery.php`
- [ ] T061 [P] [US4] Add strict annul command DTO and note normalization in `app/Domain/Budget/Data/AnnulBudgetApprovalData.php`
- [ ] T062 [US4] Implement PlanningYear→active Approval locking, Budget version/state checks, final blocker rebuild, terminal metadata, Preparation transition, Base preservation and atomic evidence in `app/Domain/Budget/Actions/AnnulBudgetApproval.php`
- [ ] T063 [P] [US4] Add annulment preview/success resources with exactly four keys in `app/Http/Resources/Api/V1/BudgetApprovalAnnulmentResource.php` and `app/Http/Resources/Api/V1/AnnulmentBlockerResource.php`
- [ ] T064 [US4] Add annulment preview/final controller methods and canonical plural routes in `app/Http/Controllers/Api/V1/BudgetApprovalController.php` and `routes/api/v1/reporting.php`
- [ ] T065 [US4] Add strict annulment frontend DTOs/API calls in `frontend/src/api/budget.ts` and `frontend/src/api/budget.test.ts`
- [ ] T066 [US4] Implement allowed-annul confirmation/history refresh and action gating in `frontend/src/components/budget/BudgetAnnulmentModal.tsx` and `frontend/src/components/budget/BudgetView.tsx`
- [ ] T067 [US4] Register `AnnulBudgetApproval` and evidence failure checkpoints in `tests/Architecture/Fixtures/domain-write-rollback-map.php` and make rollback inventory green

**Checkpoint**: A never-used active Approval can be annulled and reapproved without rewriting its
snapshot or unlocking the Tenant Base.

---

## Phase 7: User Story 5 — Comprendere Perché l'Annullamento è Bloccato (Priority: P1)

**Goal**: Expose every canonical operational dependency in exactly the applicable group(s), with
safe links/redaction, and block final annulment after transactional revalidation.

**Independent Test**: Exercise positive/negative/zero/manual/contract/deleted Actual, deleted Extra,
Rectification, Closure-after-reopen and Actual+Extra; verify same source once per applicable group,
authorized links or opaque redaction, disabled UI and stable blocked error.

### Tests for User Story 5

- [ ] T068 [P] [US5] Add table-driven blocker source tests for signs, zero, contract origin, deleted root/row, two rows on one Expense, Rectification and historical Closure, plus paired fixtures for move/reproposal, Project Continuation, Plafond increase/decrease, annual Contract/Project exclusion, contract cessation, cancellation and restore proving each blocks only when it emits one of the four canonical facts, in `tests/Feature/Budget/BudgetApprovalBlockerContractTest.php`
- [ ] T069 [US5] Add Actual+Extra overlap tests requiring identical identity once in each group and no within-group duplicate in `tests/Feature/Budget/BudgetApprovalBlockerContractTest.php`
- [ ] T070 [P] [US5] Add HTTP tests for raw authorized items, Approval-scoped opaque aliases, tombstone drill-down, redacted detail, arbitrary alias 404 and blocked error details in `tests/Feature/Api/Budget/BudgetApprovalAnnulmentApiTest.php`
- [ ] T071 [P] [US5] Add 20 controlled two-connection annul-vs-writer collisions for each of Actual, Extra, Rectification and Closure, including favorable-preview races, asserting linearizable outcomes and never Preparation when a blocker committed first, in `tests/Accounting/Integration/BudgetApprovalAnnulmentConcurrencyTest.php`
- [ ] T072 [P] [US5] Add React tests for four labelled groups, cross-group shared alias, tombstones/redaction, accessible links and absent final action while blocked in `frontend/src/components/budget/BudgetAnnulmentModal.test.tsx`

### Implementation for User Story 5

- [ ] T073 [US5] Complete trashed root/row existence queries, canonical source identity and per-group dedup without `EconomicDatasetQuery` in `app/Domain/Budget/Queries/AnnulmentBlockerQuery.php`
- [ ] T074 [US5] Implement batched source authorization, copied tombstone evidence and Approval-scoped HMAC aliasing in `app/Domain/Budget/Services/AnnulmentBlockerPresenter.php`
- [ ] T075 [US5] Add scoped authorized blocker drill-down query that resolves issued aliases without becoming a discovery oracle in `app/Domain/Budget/Queries/AnnulmentBlockerDetailQuery.php`
- [ ] T076 [US5] Add blocker drill-down controller/resource behavior and `GET /approvals/{approval}/blockers/{sourceIdentity}` in `app/Http/Controllers/Api/V1/BudgetApprovalController.php`, `app/Http/Resources/Api/V1/AnnulmentBlockerResource.php` and `routes/api/v1/reporting.php`
- [ ] T077 [US5] Return current authorized/redacted four-group details from `BUDGET_APPROVAL_ANNULMENT_BLOCKED` without partial writes in `app/Domain/Budget/Actions/AnnulBudgetApproval.php` and `app/Support/Api/ApiErrorResponse.php`
- [ ] T078 [US5] Render grouped items, overlap identity, tombstone links/redaction and nonexecutable blocked state in `frontend/src/components/budget/BudgetAnnulmentModal.tsx` and `frontend/src/components/budget/BudgetBlockerGroups.tsx`

**Checkpoint**: Every blocker is understandable to the degree authorized, never leaks foreign/raw
protected data, and always prevents an invalid Annullamento.

---

## Phase 8: User Story 6 — Non Bloccare per Eventi Informativi o Indipendenti (Priority: P2)

**Goal**: Keep annulment available when only the explicitly approved nonblocking activities exist.

**Independent Test**: Independently create informational evaluations, descriptive edits,
attachments, isolated Revision/Audit, report/export/scenario/read-only snapshot/preferences and
other-Year changes; preview remains exactly four empty arrays and valid annulment succeeds.

### Tests for User Story 6

- [ ] T079 [P] [US6] Add a nonblocker data provider spanning evaluations; amount/membership-neutral Cost Center, Vendor, description and note edits; attachments; isolated Revision/Audit; Report; Export; Scenario; BudgetVersion/read-only snapshot; preferences; different-Year changes; and every FR-026 operation when it emits no canonical blocker, asserting a successful annulment after each case, in `tests/Feature/Budget/BudgetApprovalNonblockerTest.php`
- [ ] T080 [US6] Add HTTP regression tests proving no fifth key/activity fallback and no unrelated-Year blocker in `tests/Feature/Api/Budget/BudgetApprovalAnnulmentApiTest.php`
- [ ] T081 [US6] Add React empty-group/allowed-action tests after each nonblocking activity in `frontend/src/components/budget/BudgetAnnulmentModal.test.tsx`

### Implementation for User Story 6

- [ ] T082 [US6] Harden the blocker result as an exhaustive four-key type that cannot accept Audit/Revision/attachment/activity fallbacks in `app/Domain/Budget/Data/AnnulmentBlockers.php` and `app/Domain/Budget/Queries/AnnulmentBlockerQuery.php`
- [ ] T083 [US6] Ensure blocker joins scope strictly to the routed Tenant/PlanningYear and never traverse unrelated annual relationships, and refine the existing Expense/Plafond lifecycle guard so amount- and membership-neutral descriptive updates remain allowed after Approval without enabling allocation, coverage or monetary mutations, in `app/Domain/Budget/Queries/AnnulmentBlockerQuery.php`, `app/Domain/Expenses/Actions/UpdateExpense.php` and `app/Domain/Expenses/Services/PlafondLifecycleGuard.php`
- [ ] T084 [US6] Present the explicit no-blocker explanation and enabled mandatory-note path without generic warnings in `frontend/src/components/budget/BudgetAnnulmentModal.tsx`

**Checkpoint**: Informational and independent activity never becomes an implicit fifth blocker.

---

## Phase 9: User Story 7 — Operare in Modo Isolato, Atomico e Tracciabile (Priority: P2)

**Goal**: Prove every read/write is Tenant-isolated, permission-correct, linearizable, atomic,
diagnosable and accessible across the completed feature.

**Independent Test**: Run the full route matrix with allowed/missing abilities, inactive actors and
Tenant, protected Platform Administrator, missing/foreign IDs and controlled collisions/failures;
verify equivalent non-disclosure and exact state/history/evidence cardinality.

### Tests for User Story 7

- [ ] T085 [P] [US7] Add all-route ability, active-user/Tenant and protected Platform Administrator matrix tests in `tests/Feature/Authorization/BudgetApprovalAuthorizationTest.php`
- [ ] T086 [P] [US7] Add missing-vs-foreign Year/Approval/blocker equivalence and zero-leak tests for state, counts, identities, actors and amounts in `tests/Feature/Api/Budget/BudgetApprovalTenancyTest.php`
- [ ] T087 [P] [US7] Add success/failure Revision, `revision.batch.begin`, business Audit and correlation cardinality/privacy tests in `tests/Feature/Budget/BudgetProposalApprovalAuditTest.php`
- [ ] T088 [P] [US7] Extend real-MySQL lock-order tests for Tenant→Years ascending→roots and unrelated scope progress in `tests/Accounting/Integration/AnnualEconomicMutationConcurrencyTest.php`
- [ ] T089 [P] [US7] Add fixed query-count gates for overview, impact, history/detail, annulment preview and drill-down with large fixtures in `tests/Accounting/Integration/BudgetProposalSurfaceQueryCountTest.php`
- [ ] T090 [US7] Add correlation reuse, error/log redaction and no replay tests in `tests/Feature/Diagnostics/CorrelationIdTest.php` and `tests/Feature/Budget/BudgetProposalApprovalAuditTest.php`
- [ ] T091 [P] [US7] Add frontend tests for preserved inputs, correlation reference, text-not-color errors, keyboard focus and inactive actions in `frontend/src/components/budget/BudgetApprovalModal.test.tsx`, `frontend/src/components/budget/BudgetAnnulmentModal.test.tsx` and `frontend/src/components/budget/BudgetView.test.tsx`

### Implementation for User Story 7

- [ ] T092 [US7] Enforce endpoint abilities before scoped lookup and nested Tenant/Year Approval resolution before protected reads in `routes/api/v1/reporting.php`, `app/Http/Controllers/Api/V1/BudgetApprovalController.php` and `app/Domain/Budget/Queries/ApprovalSnapshotQuery.php`
- [ ] T093 [US7] Enforce deterministic locked error precedence and re-read persisted actor/Tenant/Year/Approval in `app/Domain/Budget/Actions/ApproveBudgetProposal.php` and `app/Domain/Budget/Actions/AnnulBudgetApproval.php`
- [ ] T094 [US7] Redact business Audit/log properties to IDs, digest and safe cardinalities while preserving diagnostic correlation in `app/Domain/Budget/Actions/ApproveBudgetProposal.php`, `app/Domain/Budget/Actions/AnnulBudgetApproval.php` and `app/Domain/Audit/AuditRecorder.php`
- [ ] T095 [US7] Batch-load dimensions and source authorizations to satisfy fixed query-count gates in `app/Domain/Budget/Queries/BudgetApprovalPreviewQuery.php`, `app/Domain/Budget/Queries/ApprovalHistoryQuery.php` and `app/Domain/Budget/Services/AnnulmentBlockerPresenter.php`
- [ ] T096 [US7] Complete accessible focus/error/state behavior and responsive dark-mode rendering in `frontend/src/components/budget/BudgetProposalImpact.tsx`, `frontend/src/components/budget/BudgetApprovalModal.tsx`, `frontend/src/components/budget/BudgetApprovalHistory.tsx` and `frontend/src/components/budget/BudgetAnnulmentModal.tsx`

**Checkpoint**: All seven stories satisfy the inherited security, atomicity, concurrency,
diagnostic and accessibility contracts with executable evidence.

---

## Phase 10: Polish, Gates, Permanent Documentation and Local Integration

**Purpose**: Complete cross-cutting fixtures, full verification, permanent current-state handoff and
the explicitly requested local-only integration workflow.

- [ ] T097 [P] Seed canonical automatic proposal, active/annulled history and safe four-group demo evidence without invalid business writers in `database/seeders/DemoDataSeeder.php` and extend `tests/Feature/Expenses/AnnualExpenseSeederTest.php`
- [ ] T098 Update the versioned economic coverage manifest and architecture action/rollback inventories for all changed economic classes in `tests/Support/economic-coverage-classes.php`, `tests/Architecture/Fixtures/domain-write-rollback-map.php` and `tests/Architecture/BudgetApprovalTargetContractTest.php`
- [ ] T099 Remove every remaining production/frontend/test reference to `approval_operations`, `ApprovalKind`, `approved_amount`, `approved_basis`, `approval-decisions`, editable approval items and legacy Close in `app/`, `database/`, `routes/`, `frontend/src/` and `tests/`
- [ ] T100 Execute the protected reset and all focused backend/frontend scenarios from `specs/025-budget-proposal-approval/quickstart.md`, recording only actual command/result evidence in the local run state `.codex/run-state.local.md`
- [ ] T101 Run `composer test:static`, `composer test:prepare`, `composer test:accounting`, the Xdebug economic coverage gate and `composer test:application` in the Compose Laravel service, then fix only Slice 025 regressions in their owning files
- [ ] T102 Run `npm run verify` in the Compose frontend service and fix Slice 025 Vitest, dark-token, ESLint, TypeScript or Vite build regressions in `frontend/src/`
- [ ] T103 Perform the proportional manual browser verification for desktop, responsive, dark mode, proposal impact, approval, immutable history, allowed/blocked annulment and stale-input recovery from `specs/025-budget-proposal-approval/quickstart.md`
- [ ] T104 Update permanent verified-current domain/architecture/operations/status documentation only after gates pass in `docs/DOMAIN.md`, `docs/ARCHITECTURE.md`, `docs/OPERATIONS.md`, `docs/STATUS.md`, `README.md` and `specs/README.md`, and record that no canonical global OpenAPI file exists instead of inventing one
- [ ] T105 Run independent domain, security/Tenant, test-completeness and surface-parity reviews against `specs/025-budget-proposal-approval/spec.md`; resolve every CRITICAL/HIGH finding in its owning code/test/artifact path
- [ ] T106 Run `git diff --check`, inspect the exact Slice 025 diff and modes, commit the complete feature with an intentional message on `agent/025-budget-proposal-approval`, then locally integrate it into `agent/022-integration` without push, PR or remote merge

---

## Dependencies & Execution Order

### Phase dependencies

- **Setup (Phase 1)**: no dependency; T001–T003 can begin immediately.
- **Foundational (Phase 2)**: depends on Setup; blocks every story.
- **US1 (Phase 3)**: depends only on Foundation and is the preview/overview MVP.
- **US2 (Phase 4)**: depends on US1 composition/fingerprint.
- **US3 (Phase 5)**: depends on US2 persisted snapshots.
- **US4 (Phase 6)**: depends on US2 active Approval; it is independently testable with empty blocker fixtures. T065 additionally waits for T052 because both own the shared Budget frontend adapter.
- **US5 (Phase 7)**: depends on US4 blocker contract/action.
- **US6 (Phase 8)**: depends on US4 exact group contract; it may proceed in parallel with US5 after US4 except T080 waits for T070 and T081 waits for T072 because each pair owns the same test file.
- **US7 (Phase 9)**: depends on all desired functional stories and verifies their shared guarantees.
- **Polish (Phase 10)**: depends on US1–US7 completion.

### User-story graph

```text
Foundation
   |
  US1
   |
  US2 ─────> US3
   |
  US4 ─────> US5
   |          |
   └───────> US6
                 \
US1 + US2 + US3 + US4 + US5 + US6 ──> US7 ──> Polish/Integration
```

### Within each story

1. Create the story's tests and confirm the target assertions fail for the missing behavior.
2. Add data/value objects before services/actions.
3. Add services/actions before controller/resources/routes.
4. Add strict frontend adapters before components.
5. Run the independent story checkpoint before starting dependent stories.
6. Do not mark a task complete solely because an adjacent test passes; execute the task's named
   test/gate and inspect exact state/evidence when specified.

## Parallel Opportunities

- T002 and T003 can run alongside T001.
- T004–T006 are independent red contract suites; T008, T010 and T012 can proceed in parallel after
  T007's schema names are fixed.
- Within US1, T016–T020 can be authored in parallel; T021, T026 and T028 own separate layers.
- Within US2, T031–T035 are independent test surfaces; T036 and T040 own separate layers.
- US3 query/resource work T048–T049 and frontend adapter T052 can proceed in parallel.
- US4 tests T055, T056 and T058 and DTO work T059/T061/T063 can proceed in parallel; T057 follows the earlier rollback-file owner and T065 follows T052.
- US5 tests T068–T072 are independent; server redaction T074 and React rendering T078 use separate
  layers after the core blocker shape is stable.
- US5 and US6 may run in parallel only after US4; serialize T070→T080 and T072→T081, and coordinate
  shared ownership of `AnnulmentBlockerQuery.php` and `BudgetAnnulmentModal.tsx` to avoid conflicting edits.
- US7 test tasks T085–T091 can run in parallel; implementation tasks then close the observed gaps.
- T097 and T104 can be prepared independently but T104 may be finalized only after all gates pass.

## Parallel Examples

### User Story 1

```text
Task T016: composer unit contracts in tests/Accounting/Unit/BudgetProposalComposerTest.php
Task T019: API preview contracts in tests/Feature/Api/Budget/BudgetProposalApprovalApiTest.php
Task T020: React impact contracts in frontend/src/components/budget/BudgetProposalImpact.test.tsx
```

### User Story 2

```text
Task T031: approval/domain cases in tests/Feature/Budget/BudgetProposalApprovalTest.php
Task T034: real-MySQL races in tests/Accounting/Integration/BudgetProposalApprovalConcurrencyTest.php
Task T035: modal interactions in frontend/src/components/budget/BudgetApprovalModal.test.tsx
```

### User Stories 5 and 6 after US4

```text
Worker A: T068–T078, owning blocker facts, redaction, drill-down and blocked UI
Worker B: T079–T084, owning nonblocker matrices and exact empty-group behavior
Integration owner: serializes T070→T080, T072→T081 and shared edits to
AnnulmentBlockerQuery.php and BudgetAnnulmentModal.tsx
```

## Implementation Strategy

### MVP first

1. Complete Setup and Foundation.
2. Complete US1 only.
3. Validate the full automatic proposal/Impact story independently in Net and Gross Base.
4. Do not expose a write until US2 atomic snapshot tests are green.

### Incremental delivery

1. **US1**: transparent complete proposal.
2. **US2**: atomic complete Approval and Base freeze.
3. **US3**: permanent immutable history.
4. **US4**: safe allowed Annullamento and reapproval.
5. **US5**: exhaustive blocker explanation/redaction.
6. **US6**: proven nonblockers/no fifth category.
7. **US7**: full security, concurrency, rollback and accessibility hardening.
8. **Polish**: complete gates, browser verification, permanent documentation, review and local-only
   integration.

### Multi-agent ownership strategy

- Complete Setup/Foundation with one integration owner; schema and shared Budget query remain
  single-owner files.
- After Foundation, delegate independent test surfaces and backend/frontend layers, always assigning
  explicit file ownership and warning workers not to revert concurrent edits.
- Keep `AnnualBudgetQuery.php`, `BudgetApprovalController.php`, `reporting.php`,
  `AnnulmentBlockerQuery.php`, `frontend/src/api/budget.ts` and shared Budget components under one
  owner at a time.
- Merge only verified commits into the Slice branch; complete the independent reviews before the
  final Slice commit/local integration.

## Notes

- `[P]` means file ownership and prerequisites genuinely permit parallel work; it does not authorize
  two workers to edit the same shared file concurrently.
- Every Approval/Annullamento success has one operation-specific business Audit plus the inherited
  `revision.batch.begin` infrastructure Audit; tests must not miscount those as two business events.
- `PlanningYear.lock_version` is the sole optimistic version for both mutations.
- Redacted blockers expose only an Approval-scoped opaque alias and group membership/cardinality;
  they never expose raw source ID, label, amount, actor or link.
- The protected Greenfield reset is the only schema rebuild command. Never substitute raw destructive
  migration, database or Docker-volume deletion.
- Stop at any checkpoint if its independent test is not green; do not carry a known contract gap into
  a dependent story.
