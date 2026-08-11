# Tasks — Feature 017 Budget annuale, approvazioni e ciclo Spese

## Phase 1 — Setup and readiness

- [x] T001 Validate `specs/017-budget-annual-approval-and-expense-lifecycle/spec.md`, checklist and constitution decisions
- [x] T002 [P] Complete design artifacts in `specs/017-budget-annual-approval-and-expense-lifecycle/plan.md`, `research.md`, `data-model.md`, `contracts/api-v1.md`, and `quickstart.md`
- [x] T003 Run read-only cross-artifact analysis across `spec.md`, `plan.md`, and `tasks.md` and resolve CRITICAL/HIGH findings at source

## Phase 2 — Foundational schema and shared domain

- [x] T004 [P] Add schema/backfill tests for Budget, lifecycle, approvals, Contract Project, planning selection and revision tombstones in `tests/Feature/Budget/AnnualBudgetSchemaTest.php`
- [x] T005 Add forward migration and safe activation baseline in `database/migrations/2026_08_09_100001_add_annual_budget_lifecycle.php`
- [x] T006 [P] Add Budget/Expense/approval enums in `app/Domain/Budget/Enums/` and `app/Domain/Expenses/Enums/`
- [x] T007 Add model fields, casts, relations and version allowlists in `app/Models/PlanningYear.php`, `Expense.php`, `ExpenseRow.php`, `Contract.php`, `ApprovalOperation.php`, `ApprovalItem.php`, and `RevisionBatchItem.php`
- [x] T008 Add focused factories for approvals and lifecycle states in `database/factories/`
- [x] T009 Verify/extend ignore configuration in `.gitignore`, `.dockerignore`, and `frontend/eslint.config.js`

## Phase 3 — User Story 1: detailed proposed Budget (P1)

**Goal**: exactly one selected Estimate/Quote per Expense feeds proposed totals, with visible alternatives and exact Plafond reconciliation.

**Independent test**: create multiple planning rows and Plafond consumers, select planning, and verify one exact proposed contribution plus explicit overrun.

- [x] T010 [P] [US1] Rewrite planning/date/Plafond tests first in `tests/Feature/Expenses/ExpensePlanningLifecycleTest.php` and `tests/Accounting/Unit/EconomicEngineTest.php`
- [x] T011 [US1] Extend Expense DTOs and validation for optional planning dates, immediate same-year Actual and selected planning in `app/Domain/Expenses/Data/SaveExpenseData.php`, `SaveExpenseRowData.php`, and `Services/ExpenseAggregateValidator.php`
- [x] T012 [US1] Refactor Expense aggregate persistence to remove confirmation semantics and maintain current planning in `app/Domain/Expenses/Actions/Concerns/ManagesExpenseAggregate.php`
- [x] T013 [US1] Refactor shared dataset/kernel for selected planning, Actual and Plafond in `app/Domain/Economics/Data/`, `app/Domain/Economics/Services/EconomicEngine.php`, and `app/Domain/Reporting/Queries/EconomicDatasetQuery.php`
- [x] T014 [US1] Expose lifecycle/planned/actual values in Expense queries/resources/controller in `app/Domain/Expenses/Queries/`, `app/Http/Resources/Api/V1/Expense*Resource.php`, and `app/Http/Controllers/Api/V1/ExpenseController.php`
- [x] T015 [US1] Update typed Expense client/editor/detail/register for selected planning and no confirmation/distribution in `frontend/src/api/expenses.ts`, `frontend/src/components/expenses/`, and `frontend/src/pages/Expenses/`
- [x] T016 [US1] Run focused Expense planning/accounting/API tests and frontend build for the completed slice

## Phase 4 — User Story 2: approvals and variations (P1)

**Goal**: first approval and later multi-Expense variations are exact, immutable, atomic and historically traceable.

**Independent test**: approve null/zero/positive values, perform zero-delta reallocation, and inject stale/audit failure to prove full rollback.

- [x] T017 [P] [US2] Add approval domain/HTTP/authorization/rollback tests first in `tests/Feature/Budget/BudgetApprovalTest.php` and `tests/Feature/Api/Budget/BudgetApprovalApiTest.php`
- [x] T018 [US2] Implement approval DTO and transactional Action in `app/Domain/Budget/Data/ApplyApprovalData.php` and `app/Domain/Budget/Actions/ApplyBudgetApproval.php`
- [x] T019 [US2] Add annual Budget Policy/query/resources/controller/routes in `app/Policies/PlanningYearPolicy.php`, `app/Domain/Budget/Queries/AnnualBudgetQuery.php`, `app/Http/Resources/Api/V1/AnnualBudgetResource.php`, `app/Http/Controllers/Api/V1/BudgetLifecycleController.php`, and `routes/api/v1/reporting.php`
- [x] T020 [US2] Prevent generic approved/dimension mutation and Tenant basis changes after approval in `app/Domain/Expenses/Actions/UpdateExpense.php`, `app/Domain/Expenses/Services/ExpenseAggregateValidator.php`, and `app/Domain/Tenancy/Actions/UpdateTenant.php`
- [x] T021 [US2] Add Budget approval client/modal/summary UI in `frontend/src/api/budget.ts`, `frontend/src/components/budget/BudgetApprovalModal.tsx`, and `frontend/src/components/budget/BudgetView.tsx`
- [x] T022 [US2] Run focused approval/action/API/frontend verification for the completed slice

## Phase 5 — User Story 3: Actual and Expense close/reopen (P1)

**Goal**: Actual rows are immediately effective and Expense close/reopen follows the clarified economic-field boundary.

**Independent test**: save positive/negative Actual, reject wrong year, close with/without outcome, edit descriptive then economic fields and verify state/revisions.

- [x] T023 [P] [US3] Add close/reopen/date/negative/tenant/rollback tests first in `tests/Feature/Expenses/ExpenseCloseReopenTest.php` and `tests/Feature/Api/Expenses/ExpenseLifecycleApiTest.php`
- [x] T024 [US3] Implement close and economic-change detection in `app/Domain/Expenses/Actions/CloseExpense.php`, `UpdateExpense.php`, and `Concerns/ManagesExpenseAggregate.php`
- [x] T025 [US3] Add lifecycle routes/resources and remove confirm route/Action contract in `app/Http/Controllers/Api/V1/ExpenseController.php` and `routes/api/v1/expenses.php`
- [x] T026 [US3] Add Expense close modal, state/outcome badges and automatic-reopen feedback in `frontend/src/pages/Expenses/ExpenseDetail.tsx` and `frontend/src/components/expenses/`
- [x] T027 [US3] Rewrite incompatible confirmation/schema/API tests in `tests/Feature/Expenses/`, `tests/Feature/Api/Expenses/`, and `tests/Feature/Revisions/ExpenseVersioningIntegrationTest.php`
- [x] T028 [US3] Run focused Expense lifecycle tests and frontend verification

## Phase 6 — User Story 4: Contract and Project annual planning (P2)

**Goal**: Contract may belong to Project and generates one protected annual planning Expense, never an Actual.

**Independent test**: generate monthly/annual terms into one yearly Quote, override it, change Contract/Project and verify future-only propagation and visible difference.

- [x] T029 [P] [US4] Rewrite Contract generation/project consistency tests first in `tests/Feature/Contracts/ContractAnnualPlanningTest.php` and `tests/Feature/Api/Contracts/ContractApiHttpTest.php`
- [x] T030 [US4] Extend Contract DTO/model/validation/API with Project in `app/Domain/Contracts/Data/SaveContractData.php`, `Actions/Concerns/ManagesContracts.php`, `app/Models/Contract.php`, and `app/Http/Controllers/Api/V1/ContractController.php`
- [x] T031 [US4] Replace occurrence Actual generation/sync with annual Quote planning in `app/Domain/Contracts/Queries/ExpectedContractOccurrenceQuery.php`, `Actions/GenerateContractOccurrenceForYear.php`, and `Actions/SynchronizeContractOccurrences.php`
- [x] T032 [US4] Update Contract/Expense resources and React forms/detail for Project and planning difference in `app/Http/Resources/Api/V1/Contract*Resource.php`, `frontend/src/api/contracts.ts`, and `frontend/src/components/contracts/`
- [x] T033 [US4] Run focused Contract/Project/Expense API tests and frontend verification

## Phase 7 — User Story 5: closed Budget, movement and cross-year credit (P2)

**Goal**: closed Budgets remain editable with warning; movement and credit lineage are explicit and atomic.

**Independent test**: close Budget, edit without reopen, move a planned Expense and add next-year credit while original Actual remains unchanged.

- [x] T034 [P] [US5] Add Budget close/move/credit/rollback tests first in `tests/Feature/Budget/BudgetCloseAndExpenseMoveTest.php` and `tests/Feature/Api/Budget/BudgetLifecycleApiTest.php`
- [x] T035 [US5] Implement Budget close and Expense movement Actions in `app/Domain/Budget/Actions/CloseAnnualBudget.php` and `app/Domain/Expenses/Actions/MoveExpense.php`
- [x] T036 [US5] Add close/move HTTP contracts and closed warning propagation in `app/Http/Controllers/Api/V1/BudgetLifecycleController.php`, `ExpenseController.php`, and API Resources
- [x] T037 [US5] Add Budget close/warning and Expense move UI in `frontend/src/components/budget/`, `frontend/src/pages/Budget/Home.tsx`, and `frontend/src/pages/Expenses/ExpenseDetail.tsx`
- [x] T038 [US5] Run focused close/movement tests and frontend verification

## Phase 8 — User Story 6: annual summary and detail (P2)

**Goal**: Budget and Report share proposed/approval/Actual formulas and five grouping dimensions.

**Independent test**: the same deterministic data returns cent-identical totals in Budget, Report and every grouping, including unapproved Actual and Plafond overrun.

- [x] T039 [P] [US6] Add reporting parity/grouping tests first in `tests/Accounting/Integration/AnnualBudgetDatasetTest.php` and `tests/Feature/Api/Reporting/AnnualReportApiTest.php`
- [x] T040 [US6] Complete annual summary/detail DTOs and kernel/query in `app/Domain/Budget/Data/`, `app/Domain/Reporting/Data/`, `app/Domain/Reporting/Queries/`, and `app/Domain/Economics/Services/EconomicEngine.php`
- [x] T041 [US6] Replace Budget/Report resources and filter contract in `app/Http/Resources/Api/V1/AnnualBudgetResource.php`, `AnnualReportResource.php`, and `app/Http/Controllers/Api/V1/EconomicReportController.php`
- [x] T042 [US6] Replace legacy bucket UI with annual metrics/groupings in `frontend/src/api/budget.ts`, `frontend/src/api/reports.ts`, `frontend/src/components/budget/BudgetView.tsx`, and `frontend/src/components/reports/ReportsView.tsx`
- [x] T043 [US6] Run accounting parity/API/frontend verification

## Phase 9 — User Story 7: historical annual projection (P3)

**Goal**: authorized users view a complete annual domain at/after activation cutoff with atomic batches and no N+1.

**Independent test**: cutoffs before/inside/after multi-record mutations return explicit unavailable, complete-before or complete-after states with historical labels and deletes.

- [x] T044 [P] [US7] Add cutoff/timezone/tombstone/tenant/read-only tests first in `tests/Feature/Budget/HistoricalBudgetQueryTest.php` and `tests/Feature/Api/Budget/HistoricalBudgetApiTest.php`
- [x] T045 [US7] Extend revision writing with annual scope and explicit mutation in `app/Domain/Revisions/Actions/LinkVersionToRevisionBatch.php` and all Expense/Budget mutation concerns
- [x] T046 [US7] Implement activation baseline and batch-cutoff projection in `app/Domain/Revisions/Actions/ActivateAnnualHistory.php` and `app/Domain/Budget/Queries/HistoricalAnnualBudgetQuery.php`
- [x] T047 [US7] Expose as-of Budget/Report responses and stable cutoff errors in `app/Http/Controllers/Api/V1/CurrentBudgetController.php`, `EconomicReportController.php`, and `app/Support/Api/ApiErrorResponse.php`
- [x] T048 [US7] Add current/as-of selector and read-only banner in `frontend/src/pages/Budget/Home.tsx`, `frontend/src/components/budget/BudgetView.tsx`, and `frontend/src/components/reports/ReportsView.tsx`
- [x] T049 [US7] Add deterministic no-N+1 benchmark in `tests/Performance/HistoricalBudgetBenchmarkTest.php`
- [x] T050 [US7] Run historical feature/API/benchmark/frontend verification

## Phase 10 — Polish, permanent documentation and full verification

- [x] T051 [P] Update feature-local contract and durable implemented rules in `specs/017-budget-annual-approval-and-expense-lifecycle/contracts/api-v1.md`, `docs/DOMAIN.md`, `docs/ARCHITECTURE.md`, `docs/STATUS.md`, and `specs/README.md`
- [x] T052 [P] Reconcile status/dependencies for `specs/009-operational-revisions`, `specs/011-expense-attachments`, `specs/012-budget-versions`, and `specs/013-scenarios` without deleting unimplemented scope
- [x] T053 Run backend static, migration, Accounting, Application and full `composer verify` commands in Compose
- [x] T054 Run route discovery for Budget, Expenses, Contracts and Reports and validate `contracts/api-v1.md`
- [x] T055 Run frontend lint/build and browser verification of all current/historical lifecycle flows
- [x] T056 Run Spec Kit convergence, append any remaining work, implement it, and repeat until clean

## Dependencies and delivery strategy

- Phases 1–2 gate every story.
- US1 establishes semantic planning/economics and gates US2, US4 and US6.
- US2 and US3 complete the P1 MVP and gate movement/report history.
- US4 and US5 may proceed after P1 on disjoint primary files, then converge in US6.
- US7 depends on every write path producing complete scoped revision items.
- Documentation and full verification follow all stories.

Parallel markers apply only to test-first or different-file work. MVP is US1–US3: detailed proposal,
approvals and immediate Actual/Expense lifecycle.

## Phase 11: Convergence

- [x] T057 Preserve selected Contract planning during synchronization and test unmanaged versus selected behavior per FR-027 (contradicts)
- [x] T058 Reconstruct and expose linked Contract terms and annual reference context in historical Budget responses per FR-035 (partial)
- [x] T059 Render annual utilization percentage in the Budget summary per FR-030 (partial)
- [x] T060 Render Expense closure outcome in the annual Budget detail per FR-031 (partial)
- [x] T061 Expose and render recent Expense revision activity without adding restore semantics per plan: API and React (partial)
