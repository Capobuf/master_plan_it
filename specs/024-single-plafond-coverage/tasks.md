# Tasks: Plafond Singolo e Copertura Integrale

**Input**: design documents in `specs/024-single-plafond-coverage/`

**Prerequisites**: `spec.md`, `plan.md`, `research.md`, `data-model.md`,
`contracts/api-contract.md`, `quickstart.md`

**Tests**: mandatory and tests-first. A test task must demonstrate the missing target behavior before
the corresponding implementation task starts. Existing passing baseline assertions are not a red
test substitute.

**Shared-owner rule**: tasks marked **Primary integration owner** are serialized because they touch
the consolidated migrations, economic engine/projection, shared error envelope, route aggregation,
Budget/Report consumers, navigation or permanent documentation. Parallel tasks never write the same
file.

## Format: `[ID] [P?] [Story] Description`

- `[P]` means disjoint files and no dependency on another incomplete task in the same phase.
- `[US#]` maps directly to the independently testable story in `spec.md`.
- Every task names its exact paths, prerequisite, command and expected result.

## Phase 1: Failing Contracts and Shared Foundation

**Purpose**: freeze schema, economics, transport and absence contracts before implementation.

- [ ] T001 [P] [US1] Add MySQL schema tests in `tests/Feature/Expenses/PlafondSchemaTest.php` and update the closed enum assertion in `tests/Feature/Expenses/ExpenseSchemaTest.php`; prerequisite: final data model; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Expenses/PlafondSchemaTest.php tests/Feature/Expenses/ExpenseSchemaTest.php`; expect red until the nullable generated live slot, unique Tenant/Year/Plafond-CC key, `allocation_adjustment`, `created_by_user_id`, date/author checks and Tenant-safe relations exist, while Ordinary and soft-deleted roots do not occupy the slot. [FR-001–FR-010, SC-001]
- [ ] T002 [P] [US1, US5] Add the pure decision table in `tests/Accounting/Unit/PlafondEconomicProjectionTest.php` and extend `tests/Accounting/Unit/EconomicEngineTest.php`; prerequisite: final projection contract; run both files with `php artisan test`; expect red for `3000+1000-500`, planned `4200`, consumed `2500`, available `1000`, selection, signs, Net/Gross, no clamp, Allocation once, covered planning excluded from annual planning and Plafond never Actual. [FR-003, FR-008, FR-020–FR-030, SC-002, SC-005, SC-009]
- [ ] T003 [P] [US5] Add `tests/Architecture/PlafondContractAbsenceTest.php`; prerequisite: API contract; run that file; expect red while maintained Backend/Frontend still expose `coverage_allocations`, partial/complete-coverage actions, `plafond_overrun`, `global_plafond_overrun`, Plafond `residual` or visible `Sforamento`, while explicitly preserving the distinct annual Budget `residual`. [FR-010, FR-016, FR-030, FR-040–FR-046]
- [ ] T004 [P] [US6] Add exact error-envelope tests in `tests/Feature/Api/Contracts/PlafondErrorContractTest.php`; prerequisite: API contract; run that file; expect red until `PLAFOND_INSUFFICIENT` supports `fields`, `details`, currency, basis, allocated, available, required, shortage, impact and correlation without leaking full payloads. [FR-032, FR-039, FR-050–FR-051, FR-058, SC-010]
- [ ] T005 [P] [US1–US5] Add request/response adapter tests in `frontend/src/api/plafonds.test.ts`, and extend `frontend/src/api/{expenses,budget,reports}.test.ts`; prerequisite: API contract; run `docker compose exec -T frontend npm run test:unit -- plafonds expenses budget reports`; expect red until decimal strings, four measures, impact, funding reference, dedicated routes and removed overrun fields match the contract without coercion or fallback. [FR-004–FR-008, FR-031–FR-046]
- [ ] T006 [P] [US6] Add route/ability/non-leakage contract tests in `tests/Feature/Api/Expenses/PlafondApiHttpTest.php`; prerequisite: API contract; run that file; expect red for every dedicated endpoint, reused abilities, active-state branches, root 404, relationship 422, unknown fields, generic-route kind/type rejection and exact success/error envelopes. [FR-039, FR-047–FR-058, SC-010–SC-011]
- [ ] T007 [P] [US6] Update `tests/Architecture/Fixtures/domain-write-rollback-map.php` test expectations and add `tests/Feature/Expenses/PlafondActionRollbackTest.php`; prerequisite: planned Action inventory; run `tests/Architecture/WriteRollbackCoverageTest.php` plus the new file; expect red until every new write Action is inventoried and Audit/Revision failures roll back roots, rows, versions and success evidence. [FR-054–FR-058, SC-011]
- [ ] T008 [P] [US5] Add full-dataset and query-budget tests in `tests/Accounting/Integration/{PlafondEconomicDatasetTest,PlafondSurfaceQueryCountTest}.php` and extend `AnnualExpenseProjectionQueryCountTest.php`; prerequisite: projection contract; abort unless driver is MySQL; expect red until cross-CC contributors survive presentation filters and query counts remain constant at existing caps. [FR-012–FR-029, FR-040, FR-045, SC-004–SC-005]

### Shared foundation implementation

- [ ] T009 [US1] **Primary integration owner** consolidate `database/migrations/2026_08_03_020001_create_expenses_table.php` with the generated active-slot column/unique key and `database/migrations/2026_08_03_020002_create_expense_rows_table.php` with `allocation_adjustment`, server-owned `created_by_user_id`, updated date/confirmation/metadata checks and FK; prerequisite: red T001; run the protected reset and T001; expect an empty strict-MySQL schema with no compatibility migration/backfill and all DB invariants green. [FR-001–FR-010, SC-001]
- [ ] T010 [P] [US1] Update `app/Domain/Expenses/Enums/ExpenseType.php`, `app/Models/{Expense,ExpenseRow}.php` and `database/factories/{Expense,ExpenseRow}Factory.php`; prerequisite: T009 contract; run T001 and PHP lint on these files; expect typed allocation rows, author relation/version snapshot and only valid factory states. [FR-002–FR-008, FR-054]
- [ ] T011 [US1–US5] **Primary integration owner** add `app/Domain/Economics/Data/{PlafondEconomicProjection,PlafondImpact}.php` and extend `EconomicLine.php`, `ProjectedEconomicLine.php`, `ExpenseEconomicProjection.php`, `AnnualEconomicProjection.php` and `EconomicMeasure.php` only as needed by the frozen contract; prerequisite: red T002; run PHP lint and T002; expect one exact typed projection vocabulary, no persisted total and no second engine. [FR-010, FR-020–FR-030, FR-040–FR-046]
- [ ] T012 [US1–US5] **Primary integration owner** extend `app/Domain/Economics/Services/EconomicEngine.php` to classify Allocation, Coverage Planned, Consumed and Available and annual totals exactly once; prerequisite: T011 and red T002; run T002; expect every pure decision branch green in both bases. [FR-003, FR-008, FR-020–FR-030, SC-002, SC-005]
- [ ] T013 [US1–US5] **Primary integration owner** refactor `app/Domain/Reporting/Queries/EconomicDatasetQuery.php` so it always loads the complete current Tenant/Year dataset before filters and carries both Plafond and consumer CC metadata; prerequisite: T012 and red T008; run `PlafondEconomicDatasetTest.php`; expect deleted rows excluded, alternate planning excluded and cross-CC contributors retained. [FR-012–FR-029, FR-040, FR-044–FR-045]
- [ ] T014 [US3, US4] Add `app/Domain/Expenses/Services/{PlafondCapacityService,PlafondLifecycleGuard}.php` using the canonical projection and existing `AnnualEconomicMutationGuard`; prerequisite: T012–T013; run T002 plus focused service tests; expect pure final-state impacts, deterministic shortage/blocking rows and preparation-only decisions without persistence. [FR-009, FR-025–FR-037, FR-052–FR-053]
- [ ] T015 [US6] **Primary integration owner** extend `app/Support/Api/ApiErrorResponse.php` and add the narrow typed exception/data carrier needed for `PLAFOND_INSUFFICIENT`; prerequisite: red T004 and T014; run T004 plus `tests/Feature/Api/Contracts/AnnualExpenseErrorContractTest.php`; expect stable details while all inherited envelopes remain unchanged. [FR-032, FR-039, FR-050–FR-051, FR-058]
- [ ] T016 [US1–US6] **Primary integration owner** add the planned pure classes to `tests/Support/economic-coverage-classes.php`; prerequisite: T011–T015; run `docker compose exec -T -u sail -e XDEBUG_MODE=coverage laravel.test composer test:economic-coverage`; expect the gate to stay red until all new core branches have tests and to fail for any omitted class/metric. [FR-020–FR-030, FR-046]

**Checkpoint**: schema, projection, capacity vocabulary and error transport are frozen before story writers touch dependent API/UI files.

## Phase 2: User Story 1 — Allocazione Unica e Additiva (P1)

**Goal**: create the only live Plafond for a Tenant/Year/CC and append signed, dated and authored allocation rows.

**Independent Test**: create `3000`, append `+1000/-500`, reject zero/duplicate and read Allocation `3500` with three attributable rows.

### Tests first

- [ ] T017 [P] [US1] Add domain tests in `tests/Feature/Expenses/PlafondAggregateTest.php`; prerequisite: T009–T015; run that file before implementation; expect red for dedicated create, row matrix, actor/date, no-op zero, additive result, duplicate slot, state gating and referenced delete. [FR-001–FR-010, FR-035–FR-039, SC-001–SC-002]
- [ ] T018 [P] [US1] Add real-MySQL duplicate concurrency cases in `tests/Accounting/Integration/PlafondMutationConcurrencyTest.php` using isolated connections; prerequisite: T009; run that file; expect red until 20 contenders yield one complete winner and no losing root/row/revision/audit. [FR-001, FR-037, FR-052–FR-057, SC-001]
- [ ] T019 [P] [US1] Add frontend component/page tests in `frontend/src/components/plafonds/{PlafondEditor,PlafondMeasures,PlafondRegister}.test.tsx` and `frontend/src/pages/Plafonds/Home.test.tsx`; prerequisite: red T005 fixtures; run those files; expect red for create, adjustment, loading/empty/error/permission, exact four measures and no local formula. [FR-004–FR-008, FR-031–FR-038, SC-002]

### Implementation

- [ ] T020 [US1] Add `app/Domain/Plafonds/Data/{SavePlafondData,AllocationAdjustmentData}.php`, `Actions/{CreatePlafond,AddAllocationAdjustment}.php`, and `Queries/{PreviewAllocationAdjustment,PlafondQuery}.php`; prerequisite: red T017 and T014; run T017; expect annual-guarded creation/append, immutable server actor, preparation-only state, exact impacts and one Revision/Audit. [FR-001–FR-010, FR-031–FR-039, FR-052–FR-057]
- [ ] T021 [US1] Add `app/Http/Controllers/Api/V1/PlafondController.php` and `app/Http/Resources/Api/V1/{PlafondSummaryResource,PlafondDetailResource,PlafondImpactResource}.php`; prerequisite: T020 and red T006; run Plafond API tests; expect create/list/detail/preview/adjust responses to match the contract with strict unknown-field validation. [FR-001–FR-010, FR-031–FR-039, FR-047–FR-051]
- [ ] T022 [US1] **Primary integration owner** add `routes/api/v1/plafonds.php` with only the dedicated contract routes and reused `expense.*`/dimension abilities; prerequisite: T021; run `php artisan route:list --path=api/v1/plafonds` and T006; expect exactly one create surface and no new permission names. [FR-001, FR-047–FR-051]
- [ ] T023 [P] [US1] Add `frontend/src/api/plafonds.ts`, `frontend/src/components/plafonds/{PlafondEditor,PlafondMeasures,PlafondRegister}.tsx` and `frontend/src/pages/Plafonds/{Home,New,Detail}.tsx`; prerequisite: frozen T021 resource fixtures and red T019; run T005/T019; expect decimal strings rendered verbatim, inputs retained and no calculation/fallback. [FR-004–FR-008, FR-031–FR-038]
- [ ] T024 [US1] **Primary integration owner** add Italian Plafond routes/navigation in `frontend/src/{App.tsx,navigation/routes.ts,navigation/applicationNavigation.ts}` gated by existing `expense.view|create`; prerequisite: T023; run `frontend npm run test:unit -- App AppHeader Plafond` and build; expect reachable register/create/detail with no duplicate entry or permission leak. [FR-047–FR-048]
- [ ] T025 [US1] Extend `database/seeders/DemoDataSeeder.php` and `tests/Feature/Expenses/AnnualExpenseSeederTest.php` with the canonical valid Plafond dataset; prerequisite: T020–T024; run protected reset with seed and the seeder test; expect deterministic `3000+1000-500` rows with valid author/date and no invalid legacy state. [FR-004–FR-008, SC-002]

**Checkpoint**: US1 is independently usable through the dedicated Plafond workspace and API.

## Phase 3: User Story 2 — Copertura Integrale della Riga (P1)

**Goal**: let each Ordinary Estimate/Quote/Actual reference zero or one same-Tenant/year Plafond, including a different Cost Center, with Extra/Coverage XOR.

**Independent Test**: cover rows cross-CC, reject foreign/year/XOR/partial/multiple shapes and preserve both CC identities.

### Tests first

- [ ] T026 [P] [US2] Add `tests/Feature/Expenses/PlafondCoverageTest.php`; prerequisite: US1 foundation; run it before implementation; expect red for same Tenant/year, cross-CC allow, full single reference, both XOR directions, field-safe invalid relations and final-state move A→B. [FR-011–FR-019, FR-027, SC-003–SC-004]
- [ ] T027 [P] [US2] Extend `tests/Feature/Api/Expenses/AnnualExpenseApiTest.php`; prerequisite: red T006; run that file; expect red until Expense create/update/preview accepts only nullable `funded_plafond_expense_id`, rejects coverage share/list/percentage, preserves missing/foreign 422 equivalence and rejects `allocation_adjustment`/`kind=plafond`. [FR-011–FR-019, FR-047–FR-051]
- [ ] T028 [P] [US2] Extend `frontend/src/components/expenses/{ExpenseEditor,ExpenseEditorRows}.test.tsx` and `frontend/src/pages/Expenses/ExpenseDetail.test.tsx`; prerequisite: red T005 adapter; run those tests; expect red for eligible Plafond lookup, cross-CC labels, full-coverage copy, no Extra/allocation type controls and preserved form values. [FR-011–FR-019, FR-038, FR-044]

### Implementation

- [ ] T029 [US2] Extend `app/Domain/Expenses/Data/SaveExpenseRowData.php` and `app/Domain/Expenses/Services/ExpenseAggregateValidator.php` with the Ordinary/Plafond kind matrix, live same-Tenant/year funding lookup, cross-CC allowance and XOR; prerequisite: red T026; run T001/T026; expect all final row shapes deterministic and field-indexed. [FR-011–FR-019]
- [ ] T030 [US2] Extend `app/Http/Controllers/Api/V1/ExpenseController.php` request parsing so generic writes remain Ordinary, accept nullable funding only on Ordinary types and reject all unowned fields; prerequisite: T029 and red T027; run T006/T027; expect exact public input boundary and no Extra Budget write surface. [FR-011–FR-019, FR-047–FR-051]
- [ ] T031 [US2] Extend `app/Domain/Expenses/Queries/{PreviewExpenseQuery,ExpenseDetailQuery}.php`, `app/Domain/Expenses/Data/ExpenseDetail.php` and `app/Http/Resources/Api/V1/{ExpenseDetailResource,ExpenseRowResource}.php` with Plafond impacts/identity and both Cost Centers; prerequisite: T029–T030; run T026/T027; expect canonical server measures and no per-resource formula. [FR-014–FR-019, FR-031, FR-040, FR-044–FR-046]
- [ ] T032 [US2] Extend `frontend/src/api/expenses.ts`, `frontend/src/components/expenses/{ExpenseEditor,ExpenseEditorRows,ExpenseRowsTable}.tsx` and `frontend/src/pages/Expenses/ExpenseDetail.tsx`; prerequisite: T031 and red T028; run T005/T028; expect one full-coverage selector, both CC labels and input preservation. [FR-011–FR-019, FR-031, FR-038, FR-044]
- [ ] T033 [US2] Remove the misleading generic Plafond kind option from `frontend/src/components/expenses/ExpenseEditor.tsx` and use the dedicated routes from US1; prerequisite: T032; run ExpenseEditor and navigation tests; expect no frontend-without-backend create path and no `allocation_adjustment` option in Ordinary rows. [FR-002, FR-004, FR-011]

**Checkpoint**: US2 works independently with a fixed valid Plafond fixture and does not depend on Extra Budget UI.

## Phase 4: User Story 3 — Bloccare Effettivi Senza Capienza (P1)

**Goal**: reject covered Actual create/update/revision-restore that would make Consumed exceed Allocation, while planning remains informational.

**Independent Test**: from `3000/500/2500`, reject Actual `2700` with shortage `200`, preserve inputs and succeed after a valid correction.

### Tests first

- [ ] T034 [P] [US3] Extend `tests/Feature/Expenses/PlafondCoverageTest.php` with covered Actual create/update, old-contribution replacement, planning above capacity, negative Actual and consumer delete; prerequisite: US2; run it before implementation; expect red until only Actual consumes and all final states are atomic. [FR-020–FR-030, FR-037, SC-005–SC-006]
- [ ] T035 [P] [US3] Extend `tests/Accounting/Integration/PlafondMutationConcurrencyTest.php` with 20 synchronized capacity collisions, preview-then-race and stable A→B lock order; prerequisite: US2; run on MySQL; expect red until no commit has Consumed greater than Allocation and losers return updated impact. [FR-025–FR-027, FR-035–FR-037, FR-052–FR-053, SC-008]
- [ ] T036 [P] [US3] Add revision cases in `tests/Feature/Revisions/PlafondRevisionTest.php`; prerequisite: US2; run it; expect red until live-root restore revalidates existence/year/XOR/final Actual capacity and rolls back invalid snapshots without success evidence. [FR-025–FR-030, FR-054–FR-057]
- [ ] T037 [P] [US3] Add frontend shortage/recovery tests in `frontend/src/components/plafonds/PlafondImpactPanel.test.tsx` and extend Expense editor tests; prerequisite: T031 fixtures; run those files; expect red for four exact amounts, highlighted Plafond section, input retention and all four recovery choices. [FR-031–FR-039, SC-006, SC-012]

### Implementation

- [ ] T038 [US3] Integrate `PlafondCapacityService` into `app/Domain/Expenses/Actions/{CreateExpense,UpdateExpense,DeleteExpense,RestoreExpenseRevision}.php` and shared aggregate concern only where required; prerequisite: red T034–T036; run those three suites; expect annual guard before final dataset, stable root/row order, planning allowed and insufficient Actual/revision restore rolled back. [FR-020–FR-030, FR-035–FR-037, FR-052–FR-057]
- [ ] T039 [US3] Extend `app/Domain/Expenses/Services/PrepareExpenseAggregate.php` and `PreviewExpenseQuery.php` to emit affected Plafond impacts without reservation; prerequisite: T038; run API preview and race tests; expect stale checks, zero write/evidence and confirmation revalidation. [FR-025–FR-039]
- [ ] T040 [US3] Extend `frontend/src/api/client.ts` only as needed to retain structured error details and implement `frontend/src/components/plafonds/PlafondImpactPanel.tsx` plus Expense editor error integration; prerequisite: T015/T039 and red T037; run T005/T037; expect exact values, no numeric reconstruction and retained inputs after 409/422. [FR-031–FR-039, SC-006, SC-012]
- [ ] T041 [US3] Extend `tests/Architecture/Fixtures/domain-write-rollback-map.php` and rollback tests for every Action actually introduced/modified; prerequisite: T038–T040; run T007 command; expect discovered write count and rollback contract exact, with no blanket exclusions. [FR-054–FR-057, SC-011]

**Checkpoint**: US3 independently proves planning risk is visible but only covered Actual consumes/block capacity.

## Phase 5: User Story 4 — Vista di Impatto per Riduzioni (P1)

**Goal**: preview and block allocation reductions below current Consumed without unlinking coverage.

**Independent Test**: allow `3500→3000` with Consumed `2500`; reject `3500→2300` with shortage `200` and deterministic blocking rows.

### Tests first

- [ ] T042 [P] [US4] Extend `tests/Feature/Expenses/PlafondAggregateTest.php` with valid/invalid reductions, exact equality, stale preview and covered-row drill-down; prerequisite: US3; run it; expect red until confirmation revalidates and an invalid reduction appends no row. [FR-009, FR-033–FR-037, SC-007]
- [ ] T043 [P] [US4] Extend `frontend/src/components/plafonds/{PlafondEditor,PlafondImpactPanel}.test.tsx`; prerequisite: T037; run those tests; expect red for proposed measures, shortage, blocking links, stale/error and no implicit unlink. [FR-033–FR-038, SC-007]

### Implementation

- [ ] T044 [US4] Complete `app/Domain/Plafonds/Queries/PreviewAllocationAdjustment.php` and `Actions/AddAllocationAdjustment.php` with final-state reduction capacity and deterministic blocking rows; prerequisite: red T042; run T017/T042; expect exact equality allowed, insufficient reduction rejected and no partial row/revision/audit. [FR-005–FR-009, FR-033–FR-037, FR-052–FR-057]
- [ ] T045 [US4] Complete `PlafondController` preview/confirm validation and `PlafondImpactResource`; prerequisite: T044; run T004/T006; expect optional preview, mandatory root lock version, current revalidation and exact `PLAFOND_INSUFFICIENT` details. [FR-033–FR-039, FR-047–FR-051]
- [ ] T046 [US4] Complete `frontend/src/components/plafonds/{PlafondEditor,PlafondImpactPanel}.tsx` adjustment flow; prerequisite: T045 and red T043; run T019/T043; expect valid confirm, invalid retained input, blocking links gated by ability and no local capacity formula. [FR-033–FR-038, SC-007]

**Checkpoint**: US4 is independently usable on an existing Plafond and never weakens current coverage.

## Phase 6: User Story 5 — Quattro Misure su Quattro Superfici (P2)

**Goal**: reconcile Document, Register, annual Budget and Plafond Report from the same annual projection without double counting.

**Independent Test**: the canonical Net/Gross dataset yields identical four measures and cross-CC drill-down on all surfaces.

### Tests first

- [ ] T047 [P] [US5] Add `tests/Accounting/Integration/PlafondSurfaceReconciliationTest.php`; prerequisite: US1–US4; run it; expect red until Document/Register/Budget/Report share identical measures/lines in Net and Gross and covered planning is not double-counted. [FR-040–FR-046, SC-002, SC-009]
- [ ] T048 [P] [US5] Extend `tests/Accounting/Integration/AnnualBudgetDatasetTest.php`, `tests/Feature/Budget/{AnnualBudgetLifecycleTest,HistoricalBudgetQueryTest}.php` and `tests/Feature/Api/Reporting/ReportingApiHttpTest.php`; prerequisite: T012–T013; run those files; expect red until maintained current payloads remove overrun keys, add Plafond measures and preserve distinct Budget residual/history semantics. [FR-003, FR-040–FR-046]
- [ ] T049 [P] [US5] Extend `frontend/src/components/{budget/BudgetView,reports/ReportsView,expenses/ExpenseRegisterTable}.test.tsx` and add `frontend/src/components/plafonds/PlafondDetail.test.tsx`; prerequisite: frozen API fixtures; run those tests; expect red until four sentinel measures render verbatim, cross-CC rows remain and `Sforamento`/fallback is absent. [FR-040–FR-046, SC-009]

### Implementation

- [ ] T050 [US5] Add `app/Domain/Plafonds/Queries/{PlafondListQuery,PlafondDetailQuery,PlafondReportQuery}.php` using one full annual projection, then presentation filters; prerequisite: red T047 and T013; run T008/T047; expect stable ordering, complete contributing lines and constant query behavior. [FR-040, FR-044–FR-046]
- [ ] T051 [US5] **Primary integration owner** adapt `app/Domain/Budget/Queries/{AnnualBudgetQuery,HistoricalAnnualBudgetQuery}.php` and `app/Domain/Reporting/Queries/AnnualEconomicReportQuery.php`, removing maintained overrun fields and attaching canonical Plafond measures without changing annual Budget residual; prerequisite: T050 and red T048; run T047/T048; expect exact parity and historical compile safety. [FR-003, FR-040–FR-046]
- [ ] T052 [US5] Complete Plafond list/detail/report resources/controller responses from T050 and align `app/Http/Resources/Api/V1/{ExpenseDetailResource,ExpenseRegisterResource}.php`; prerequisite: T050–T051; run T006/T047/T048; expect Document/Register/Budget/Report output identical and no local summation. [FR-040–FR-046, FR-047–FR-051]
- [ ] T053 [US5] Update `frontend/src/api/{plafonds,budget,reports,projection}.ts` and remove `plafond_overrun|global_plafond_overrun` without aliasing general Budget residual; prerequisite: T051–T052 and red T005; run adapter tests and TypeScript; expect one shared exact Plafond measure type and no legacy fallback. [FR-040–FR-046]
- [ ] T054 [US5] Update `frontend/src/components/plafonds/{PlafondMeasures,PlafondRegister,PlafondDetail}.tsx`, `budget/BudgetView.tsx`, `reports/ReportsView.tsx` and `expenses/ExpenseRegisterTable.tsx`; prerequisite: T053 and red T049; run T019/T049; expect identical measures, both CCs, loading/empty/error states and no visible `Sforamento`. [FR-040–FR-046, SC-009]
- [ ] T055 [US5] Finish query budgets in `tests/Accounting/Integration/{PlafondSurfaceQueryCount,AnnualExpenseProjectionQueryCount,HistoricalBudgetQueryCount}Test.php`; prerequisite: T050–T054; run the three files; expect query counts constant between small/large datasets and existing numeric caps not raised without a measured finding. [FR-040, FR-045]

**Checkpoint**: US5 proves all four user surfaces reconcile to the cent from one Laravel projection.

## Phase 7: User Story 6 — Isolamento, Concorrenza e Tracciabilità (P2)

**Goal**: close every security, tenancy, atomicity and evidence branch for the completed feature.

**Independent Test**: same-Tenant allow succeeds; ability/inactive/foreign/stale/failure branches reveal nothing and leave no partial state; concurrent winners remain linearizable.

- [ ] T056 [P] [US6] Extend `tests/Feature/Expenses/ExpenseAuthorizationTest.php` and `tests/Feature/Api/Expenses/PlafondApiHttpTest.php` with the exhaustive endpoint/ability/active actor/inactive Tenant/protected-admin matrix; prerequisite: all API work; run both files; expect exact allow/deny behavior and no unauthorized economic reads. [FR-047–FR-051, SC-010]
- [ ] T057 [P] [US6] Complete missing/foreign equivalence and redaction assertions in `PlafondApiHttpTest.php`, `PlafondErrorContractTest.php` and `tests/Feature/Revisions/PlafondRevisionTest.php`; prerequisite: all domain/API work; run all three; expect equivalent public status/code/field keys, no foreign attributes and exact Revision/Audit cardinality. [FR-049–FR-058, SC-010–SC-011]
- [ ] T058 [US6] Extend `app/Domain/Tenancy/Actions/UpdateTenantSettings.php` to project every guarded year in the proposed Base before commit; prerequisite: T012–T015; add/extend `tests/Feature/Budget/TenantBudgetBasisFreezeTest.php` and run it; expect Net-valid/Gross-invalid rollback of basis, Tenant version and success Audit, then valid change without rewriting row components. [FR-028–FR-029, FR-052, FR-057]
- [ ] T059 [US6] Complete real-MySQL concurrency in `PlafondMutationConcurrencyTest.php`; prerequisite: T018/T035/T058; run serially on the dedicated test DB; expect all duplicate/capacity/preview/multi-root cases linearizable with no deadlock in supported order and no overcommit. [FR-052–FR-053, SC-001, SC-008]
- [ ] T060 [US6] Run `tests/Architecture/WriteRollbackCoverageTest.php`, Plafond rollback/revision tests and all API security tests after final Action inventory; prerequisite: T056–T059; expect zero unregistered writer, complete rollback and no success evidence for preview/no-op/failure. [FR-054–FR-058, SC-011]

**Checkpoint**: all six stories are complete and independently demonstrable.

## Phase 8: Full Gates, Acceptance, Reviews and Integration Handoff

- [ ] T061 **Primary integration owner** update all affected 023 regression fixtures/tests named in the implementation diff, including overrun removal, four-value enum and dedicated Plafond creation; prerequisite: US1–US6; run the touched test inventory; expect no test preserves deprecated application behavior or weakens unrelated 023 assertions. [FR-003–FR-004, FR-040–FR-046]
- [ ] T062 Run protected schema/seed verification: `docker compose exec -T -u sail laravel.test php artisan app:test-reset-greenfield --seed`; prerequisite: T061; expect exit 0 on strict MySQL with canonical seed and never run raw destructive commands. [FR-001–FR-010, SC-001–SC-002]
- [ ] T063 Run `composer test:economic-coverage`; prerequisite: T016 and all pure tests; expect every manifest class present with 100% line and applicable branch coverage, exit 0. [FR-020–FR-030, FR-046]
- [ ] T064 Run Backend gates exactly: `composer test:static`, `composer test:prepare`, `composer test:accounting`, `composer test:application`; prerequisite: T062–T063; expect every command exit 0 and record current counts, distinguishing any environment/pre-existing failure without suppression. [FR-001–FR-058, SC-001–SC-011]
- [ ] T065 Run Frontend gate `docker compose exec -T frontend npm run verify`; prerequisite: all frontend work; expect Vitest, dark-token check, ESLint, TypeScript and Vite production build exit 0 with current counts. [FR-031–FR-051, SC-009, SC-012]
- [ ] T066 Execute every scenario in `specs/024-single-plafond-coverage/quickstart.md` through the real browser/server, including desktop/responsive, light/dark, cross-CC, insufficient Actual/reduction, four recovery choices, permission denial and removed vocabulary; prerequisite: T064–T065; expect captured current evidence and no simulated result. [SC-001–SC-012]
- [ ] T067 Perform independent read-only reviews for domain, security/tenancy, test design and Backend/Frontend surface parity against the final diff; prerequisite: T063–T066; expect zero CRITICAL/HIGH and resolve or explicitly justify every MEDIUM without scope expansion. [FR-001–FR-058, SC-001–SC-012]
- [ ] T068 **Primary integration owner**, only after T067 green, align the feature-local contract with actual routes/resources and propagate durable implemented rules/status to `docs/{DOMAIN,ARCHITECTURE,STATUS}.md`, `specs/README.md`, `specs/BUDGET-DOMAIN-REFINEMENT.md` and the 022 DAG/index; prerequisite: verified implementation; run `git diff --check` and final read-only analyze; expect permanent docs contain only VERIFIED CURRENT behavior and Slice 025 becomes READY without a historical analyze report. [SC-009–SC-012]

## Dependencies and Execution Order

```text
T001–T008 red contracts
  └─> T009–T016 shared foundation
       ├─> US1 T017–T025
       │    └─> US2 T026–T033
       │         └─> US3 T034–T041
       │              └─> US4 T042–T046
       │                   └─> US5 T047–T055
       └────────────────────────> US6 T056–T060
                                      └─> T061–T068 gates/delivery
```

- Schema, projection, common errors, routes, Budget/Report consumers, navigation and permanent docs
  remain serialized under the Primary integration owner.
- Test files marked `[P]` may be authored in parallel, but MySQL concurrency/reset suites run
  serially unless isolated Compose projects, databases, ports, volumes, cache and storage are proven.
- Frontend work starts from frozen response fixtures after Backend resource contracts settle.
- Extra Budget creation/classification remains Slice 025; Trash root restore remains Slice 030.

## User Story Independence

- **US1** uses no Ordinary coverage and proves unique additive Allocation.
- **US2** uses a fixed Plafond fixture and proves integral relationship compatibility without Actual
  capacity behavior.
- **US3** uses fixed Allocation and proves Actual-only consumption/recovery.
- **US4** uses an existing Plafond/Actual dataset and proves reduction preview/blocking.
- **US5** uses direct fixtures and proves read parity independently from editors.
- **US6** wraps the completed capability but its security cases are independently executable per
  endpoint.

## Parallel Examples

```text
Foundation red tests: T001 || T002 || T003 || T004 || T005 || T006 || T007 || T008
US1 red tests:        T017 || T018 || T019
US2 red tests:        T026 || T027 || T028
US3 red tests:        T034 || T035 || T036 || T037
US5 red tests:        T047 || T048 || T049
Final read-only:      domain || security/tenancy || test || surface-parity reviews
```

No parallel example writes the same file. Shared migrations, engine, routes, API error mapping,
Budget/Report queries, navigation and program docs always have one writer.

## Requirement Coverage Map

| Requirement | Owning tasks |
|---|---|
| FR-001–FR-010 Plafond/Allocation | T001–T003, T009–T025, T042–T046, T062 |
| FR-011–FR-019 integral coverage/XOR/cross-CC | T005–T006, T013–T015, T026–T033, T056–T057 |
| FR-020–FR-030 measures/capacity/Base | T002, T008, T011–T016, T034–T041, T047–T055, T058–T059, T063 |
| FR-031–FR-039 impact/errors/input retention | T004–T007, T014–T015, T017–T023, T034–T046, T057 |
| FR-040–FR-046 four-surface projection/parity | T002–T005, T008, T011–T013, T047–T055, T061, T065–T068 |
| FR-047–FR-053 authorization/tenancy/concurrency | T006, T014–T015, T018, T021–T024, T027, T030–T031, T035, T045, T056–T060 |
| FR-054–FR-058 revision/audit/rollback/redaction | T004, T006–T007, T015, T017–T022, T036, T038, T041, T044–T045, T056–T060, T067 |
| SC-001 unique concurrency | T001, T009, T017–T018, T059, T062, T066 |
| SC-002 additive exact Allocation | T002, T017, T020–T025, T047, T066 |
| SC-003 integral single/XOR | T026–T033, T066 |
| SC-004 cross-CC | T008, T013, T026–T033, T047–T055, T066 |
| SC-005 formulas/planning above capacity | T002, T008, T012–T014, T034, T047, T066 |
| SC-006 insufficient Actual atomicity | T004, T034–T041, T057, T066 |
| SC-007 reduction impact | T042–T046, T066 |
| SC-008 capacity concurrency | T018, T035, T059, T066 |
| SC-009 four-surface parity | T002, T047–T055, T061, T065–T067 |
| SC-010 Tenant non-leakage | T004, T006, T027, T030–T031, T056–T057, T067 |
| SC-011 Revision/Audit cardinality | T007, T017–T022, T036, T038, T041, T044–T045, T057, T060, T067 |
| SC-012 operational recovery UX | T019, T028, T037, T040, T043, T046, T049, T054, T065–T067 |

## Done Criteria

- All 68 tasks are checked only after their stated command/result is evidenced.
- Protected Greenfield reset, static, Accounting, Application, economic coverage, Frontend verify
  and browser acceptance are current and green.
- Active uniqueness and capacity are proven on strict MySQL with real concurrency.
- Document, Register, Budget and Plafond Report reconcile in Net and Gross without double counting.
- Every user-facing Backend capability has a React surface, ability gate, loading/empty/error handling
  and test; internal infrastructure is explicitly classified rather than given fake UI.
- No CRITICAL/HIGH review finding, hidden second engine, partial coverage, overrun vocabulary, new
  permission, premature Extra workflow or Trash restore remains.
