# Quickstart: Validate Budget Proposal and Approval End to End

**Purpose**: executable acceptance guide for Slice 025. It validates the contracts in
[`data-model.md`](data-model.md) and [`contracts/`](contracts/), not implementation details. This
document specifies work to execute after implementation; it does not assert that any command or
scenario has been run.

## Prerequisites and Safe Start

- Docker and Docker Compose are available, and this checkout contains the Slice 025 implementation.
- The isolated Compose services `mysql`, `laravel.test`, and `frontend` from `compose.yaml` are
  running. MySQL must be the strict MySQL 8.4 service on host `mysql`.
- `.env.testing` resolves to the protected `master_plan_it_test` target. Do not point these steps at
  any shared or production data source.
- Use actors with the inherited Budget view/decision abilities and a selected active Tenant context;
  retain a user that lacks each ability and a foreign Tenant B fixture for negative checks.
- The canonical fixture contains a Net Tenant A / PlanningYear 2026 with an Ordinary Expense,
  alternative Estimate and selected Quote, a Plafond allocation and a covered planning row. Keep a
  Gross Tenant B fixture with the same monetary components for basis reconciliation.

From the repository root, start the isolated stack and use the only permitted Greenfield rebuild:

```bash
docker compose up -d mysql laravel.test frontend
docker compose ps
docker compose exec -T -u sail laravel.test php artisan app:test-reset-greenfield --seed
```

The reset must succeed only for a protected `local|testing`, strict-MySQL target at host `mysql` and
the exact allowlisted test database. It is not an application data-purge path. Never substitute
`migrate:fresh`, `db:wipe`, direct table/schema deletion, or Docker volume deletion.

Prove the guard itself before exercising the feature:

```bash
docker compose exec -T -u sail laravel.test php artisan test \
  tests/Architecture/DevelopmentEnvironmentTest.php \
  tests/Feature/Console/TestResetGreenfieldCommandTest.php
```

## Focused Automated Verification

Implement and run the following focused tests before the full gates. These paths are the required
Slice 025 test inventory; a rename is acceptable only when `tasks.md` records a one-to-one
replacement preserving every scenario class below.

```bash
docker compose exec -T -u sail laravel.test php artisan test \
  tests/Feature/Budget/BudgetProposalApprovalTest.php \
  tests/Feature/Budget/BudgetApprovalSnapshotTest.php \
  tests/Feature/Budget/BudgetApprovalAnnulmentTest.php \
  tests/Feature/Api/Budget/BudgetProposalApprovalApiTest.php \
  tests/Feature/Authorization/BudgetApprovalAuthorizationTest.php

docker compose exec -T -u sail laravel.test php artisan test \
  tests/Accounting/Integration/BudgetProposalApprovalDatasetTest.php \
  tests/Accounting/Integration/BudgetProposalApprovalConcurrencyTest.php \
  tests/Accounting/Integration/BudgetApprovalSurfaceReconciliationTest.php

docker compose exec -T frontend npm run test:unit -- \
  src/api/budget-proposal-approval.test.ts \
  src/components/budget/BudgetProposalImpact.test.tsx \
  src/components/budget/BudgetApprovalModal.test.tsx \
  src/components/budget/BudgetApprovalHistory.test.tsx \
  src/components/budget/BudgetAnnulmentModal.test.tsx \
  src/components/budget/BudgetView.test.tsx
```

The MySQL concurrency test uses two independent database connections/transactions against the
Compose MySQL service, not mocks, SQLite, or a sequential simulation. It must retain the controlled
collision count and outcomes as test evidence.

## Scenario 1 — Canonical Mixed Proposal and Impact View

Prepare Tenant A / 2026 in `preparation` with the following current economic dataset in Net Base:

| Contributor or information | Net | VAT | Gross | Expected proposal treatment |
|---|---:|---:|---:|---|
| Ordinary Expense, selected Quote | `120.00` | `26.40` | `146.40` | included once |
| Alternative Estimate on the same Expense | `100.00` | `22.00` | `122.00` | informative evaluation, excluded |
| Plafond allocation | `3500.00` | `770.00` | `4270.00` | included once |
| Planning covered by that Plafond | `4200.00` | `924.00` | `5124.00` | Coverage Planned only, excluded from monetary sum |
| Soft-deleted Expense or Row | any | any | any | excluded from current proposal |
| Actual, Revision, Audit, read-only snapshot, Export or Scenario | any | any | any | excluded unless another owner adds an explicit economic rule |

1. Open the Budget Overview and Approval Impact view for Tenant A / 2026.
2. Verify that the automatically reconstructed proposal is complete before presentation filters and
   has no client selection, partial-item approval input, or persisted per-item membership decision.
3. Verify Quote `120.00` and Plafond Allocation `3500.00` are contributors exactly once: expected
   Net proposal `3620.00`, VAT `796.40`, Gross `4416.40`, official total `3620.00` in Net Base.
4. Verify the alternative Estimate and covered `4200.00` planning are visibly explained as excluded
   information, with Coverage Planned shown separately and never added again to the proposal.
5. Verify the deleted source is absent from the current monetary composition and cannot be revived by
   Revision, Audit, or historical snapshot lookup.
6. Drill into an included Expense and Plafond. Where the actor has access, links retain Tenant and
   PlanningYear context; where access is absent, no label, amount, count, or foreign identity leaks.
7. Apply a presentation filter that hides a contributor. Confirm the Impact total and confirmation
   identity still derive from the complete annual composition, not from filtered rows.

Repeat with Tenant B in Gross Base. Stored Net/VAT/Gross components stay unchanged, while the
official proposal total is `4416.40`. In both Bases, sum shown contributors with exact decimal
arithmetic and reconcile Net, VAT, Gross and official total to the cent.

## Scenario 2 — Approval, Empty/Zero Composition and Tenant-Local Date

1. From Scenario 1, obtain the Impact preview and confirm it with its returned composition evidence,
   current Budget lock version, an optional approval note, and an effective date that is today or
   earlier in the Tenant's time zone. Verify the server records a distinct server-owned `recorded_at`.
2. Use an effective date outside the PlanningYear but not after the Tenant-local current date. Expect
   success without changing the selected PlanningYear. Attempt the next Tenant-local calendar date;
   expect rejection, retained correctable inputs, no state/freeze/snapshot/revision/audit effect.
3. Verify the Budget changes from Preparation to Approved, exactly one complete active Approval and
   snapshot are created, and all contributor identity, Net/VAT/Gross/official amounts, dimensions,
   Base, total, effective date, approver, `recorded_at`, and optional note are retained.
4. Create a separate Preparation Budget with no economic contributor. Confirm its Impact declares
   an empty composition, and approval is rejected with unchanged Budget, Base-lock timestamp,
   approvals, revisions and success audits.
5. Create another Preparation Budget with at least one contributor at `0.00`, then a variant with
   positive and negative contributors that exactly offset. Both are non-empty and must be approvable
   with a snapshot total of `0.00`; neither may be conflated with the empty case.
6. Attempt a second approval while the Budget is Approved, then while it is Closed. Both are rejected
   and create no alternate snapshot or partial result.
7. Verify the first successful approval for the Tenant freezes the economic Base in the same
   transaction. A failed first approval rolls that timestamp back; later approval or annulment cannot
   unfreeze or change it.

## Scenario 3 — Snapshot Immutability, History and Reapproval

1. After Scenario 2 approval, alter a selected planning evaluation from `120.00` to `135.00` where
   that mutation remains an allowed informational evaluation; also change Cost Center, Vendor,
   Description and Notes without an economic membership or amount effect.
2. Verify the approved Planned amount remains `120.00` Net and the snapshot retains the original
   labels/dimensions. Overview separately presents immutable approved Planned, current informative
   evaluations and current Actuals; it does not introduce Forecast or sum parallel sources.
3. Use history, read-only snapshot, filtering and drill-down. The displayed snapshot components must
   add to the approved total at the cent in its recorded Base without needing current values or a
   technical Revision chain.
4. With all four blocker groups empty, preview annulment and submit a nonblank normalized note plus
   current version. Verify the same Budget returns to Preparation, only the active Approval becomes
   Annulled, its whole snapshot/Data/Approver/Base/recorded time/approval note remain immutable in
   history, and a new Budget Revision plus attributed business Audit are added.
5. Confirm the Base-lock timestamp remains unchanged. Approve the rebuilt current proposal and
   verify a distinct new active snapshot appears while the Annulled decision is never overwritten,
   reactivated, or deleted.
6. Try annulment on an already Annulled/historical approval. It is rejected and does not affect the
   active decision. A blank or whitespace-only annulment note is likewise rejected with zero effects;
   a valid note with inner spaces and accents is retained exactly after outer-whitespace normalization.

## Scenario 4 — Four Canonical Annulment Blockers

Create four separate approved Budget fixtures for the same Tenant/Year boundary, plus the combined
case below. For every preview, assert that `blockers` has exactly `actuals`, `extra_budget`,
`rectifications`, and `closures` lists—never a generic fifth category.

1. Add a manual Actual of `0.00`, an Actual of either sign, and a Contract-generated Actual. Soft
   delete their Expense/Row as applicable. Each still makes `actuals` nonempty, each source appears
   at most once within that group, and `can_annul=false`.
2. Add an Extra Budget Expense/Row and soft delete it. It remains once in `extra_budget` and blocks
   annulment.
3. Mark one source row as both Actual and Extra Budget. It appears once in `actuals` and once in
   `extra_budget`, with the same source identity across groups; it is neither duplicated within a
   group nor reduced to one primary category.
4. Create a Rectification after approval or closure. It appears only in `rectifications` and blocks.
5. Execute a Closure, then Reopen the Budget. The historical Closure remains once in `closures` and
   blocks; reopening must not make annulment viable.
6. Where a blocker is authorized for display, verify its category and link point to the relevant
   Expense/operation. An inaccessible blocker still prevents annulment but its protected detail is
   redacted; lack of detail never changes `can_annul` to true.
7. Attempt the final annulment mutation for each blocked fixture, including stale favorable previews.
   Expect `BUDGET_APPROVAL_ANNULMENT_BLOCKED`, current permitted groups, unchanged Budget and
   Approval state, Base lock, snapshot, Revision and success Audit.

## Scenario 5 — Nonblockers and Scope Boundaries

Starting from an approved Budget with no canonical blocker, perform each action independently:

1. Add/change an informational Estimate or Quote, and make descriptive-only Cost Center, Vendor,
   Description or Note changes.
2. Add an Attachment, isolated Revision/Audit, report/export, Scenario, BudgetVersion/read-only
   snapshot, and a user preference.
3. Mutate a different PlanningYear in the same Tenant without deriving an Actual, Extra Budget,
   Rectification or Closure for the approved Budget year.
4. Exercise movement/reproposal, project continuation, Plafond allocation change, contract/project
   annual exclusion, contract cessation, deletion or restoration only when none produces a canonical
   blocker.

For every variant, preview shows the four empty lists and `can_annul=true`. A valid-note, current-
version annulment completes normally. Repeat each operation only when it does produce an Actual,
Extra Budget, Rectification, or Closure and confirm it then belongs solely to the applicable
canonical group(s), not to a newly invented category.

## Scenario 6 — Authorization, Tenant Isolation and Redaction

For Overview, Impact, snapshot/history, approval preview/confirm and annulment preview/confirm:

1. Verify same-Tenant allow with the inherited exact ability and active actor/context.
2. Remove required ability, deactivate actor, or use an unusable Tenant. The server denies before
   economically scoped reads/writes; retain the inherited protected Platform Administrator exception
   exactly, including explicit context and exact ability requirements.
3. Send a Tenant B PlanningYear or Approval identifier as Tenant A. Compare with a missing ID of the
   same request class: responses are equivalent non-disclosing failures and expose no state, total,
   actor, blocker count, label or existence clue.
4. Test foreign/missing body relations and blocker links according to the feature API contract; they
   are field-safe and reveal neither foreign monetary data nor protected source details.
5. Inspect failure envelopes, audit payloads and logs. Correlation IDs remain diagnostic and indexed
   but not unique/replay keys; no complete monetary payload, secret, or foreign data is emitted.
6. Confirm every denied or failed request leaves no snapshot, Base change, Revision, or success Audit.

## Scenario 7 — Staleness, Composition Drift, Atomicity and Real-MySQL Linearizability

1. Open an approval Impact preview. Before confirmation, change one contributor's amount, selection,
   dimension, membership or deletion state—even when its total remains equal. Confirmation must
   reject stale composition evidence, preserve inputs for review, and commit no effect.
2. Submit a stale Budget lock version and verify the standard stale result with no state, snapshot,
   Base, Revision or Audit change.
3. Run two independent MySQL clients that confirm the same Preparation Budget at the decision
   boundary. Exactly one complete approval can commit; the loser observes a serially valid result.
   Across 20 controlled collisions, never observe two active approvals, a mixed snapshot, or a
   partial Base/revision/audit write.
4. Race confirmation against a same Tenant/Year economic mutation. The winning observation is a
   complete before or after dataset, never a mixed composition. Re-run against distinct
   Tenant/PlanningYear scopes and prove unrelated scopes can progress without violating the lock
   contract.
5. Obtain an annulment preview with empty groups, then concurrently create each type of blocker in
   separate controlled runs. Annulment either fully precedes that blocker or returns
   `BUDGET_APPROVAL_ANNULMENT_BLOCKED`; it never leaves an Annulled approval with a concurrent
   blocker ordered before it.
6. Inject failures after snapshot construction, Budget transition, first Base freeze, Revision and
   Audit for approval, and after state/annulment marking, Revision and Audit for annulment. Every
   injected failure restores the exact prior state; every success has exactly one required snapshot
   or annulment decision, one attributable Revision and one business Audit, while previews/no-ops/
   failures have no success evidence.

## Scenario 8 — Frontend Interaction and Accessibility

1. From Budget Overview, open the Impact view. Verify visible state, Base, exact totals, inclusions,
   exclusions, evaluation information and permitted actions use text and controls in addition to
   color. Links preserve context and inaccessible source details stay redacted.
2. In the approval modal, show effective-date, optional note and server-provided composition
   evidence; do not show editable per-contributor approval amounts or manual selection controls.
   A future Tenant-local date shows an actionable error and retains the other input values.
3. Make preview evidence stale, submit, and verify a clear re-review message, preserved date/note,
   refreshed safe data and no optimistic success state.
4. Open annulment. With no blockers, require a visibly labelled nonblank note and current version.
   With blockers, show exactly the four groups and accessible links, do not enable/offer the final
   action, and keep any protected detail concealed.
5. After success, refresh Overview and History to show Approved/Preparation state correctly,
   immutable historical snapshot and the newly active snapshot after reapproval. Test browser
   Back/forward, refresh, narrow/mobile layout, keyboard focus, and light/dark themes.

## Full Required Gates

After all focused scenarios pass, run the repository gates exactly as follows:

```bash
docker compose exec -T -u sail laravel.test composer test:static
docker compose exec -T -u sail laravel.test composer test:prepare
docker compose exec -T -u sail laravel.test composer test:accounting
docker compose exec -T -u sail -e XDEBUG_MODE=coverage laravel.test composer test:economic-coverage
docker compose exec -T -u sail laravel.test composer test:application
docker compose exec -T frontend npm run verify
git diff --check
```

Record each actual command, exit status, relevant test/assertion or coverage evidence, and any
manual browser result at execution time. Do not copy historical Slice 023/024 counts and do not
report any gate as passing before it has run. Completion requires the focused real-MySQL
linearizability/rollback evidence, cent-level Net and Gross reconciliation, all frontend interaction
checks, and no open critical or high security, tenancy, domain, or surface-parity finding.
