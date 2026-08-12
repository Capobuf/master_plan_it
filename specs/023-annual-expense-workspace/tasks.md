# Tasks: Workspace Annuale e Spesa Autorevole

**Input**: design documents from `specs/023-annual-expense-workspace/`

**Prerequisites**: `spec.md`, `plan.md`, `research.md`, `data-model.md`, `contracts/api-contract.md`, `quickstart.md`

**Tests**: mandatory and tests-first. Every test task must fail for the missing target behavior before its implementation task begins.

**Shared-owner rule**: tasks explicitly marked “Primary integration owner” may be implemented only by that owner. A Slice writer provides tests/contract and requests integration; it must not create a parallel migration, economic core, error catalogue, route, permission or permanent-doc implementation.

## Format: `[ID] [P?] [Story] Description`

- `[P]`: different files and no dependency on another incomplete task in the same phase.
- `[US#]`: maps to the independently testable User Story in `spec.md`.
- Every task declares exact path, prerequisite, verification command and expected result.

## Phase 1: Setup and Coverage Tooling

**Purpose**: make the required economic coverage Gate executable before business implementation.

- [ ] T001 Install and enable Xdebug as a test-only PHP extension in `docker/8.3/Dockerfile`; prerequisite: none; run `docker compose build laravel.test && docker compose run --rm -e XDEBUG_MODE=coverage laravel.test php -m`; expect exit 0 and one `xdebug` module without changing production dependencies. [FR-043]
- [ ] T002 [P] Add the focused source/test configuration in `phpunit.economic-coverage.xml` and the strict Cobertura assertion in `tests/Support/assert-economic-coverage.php`; prerequisite: none; run `php -l tests/Support/assert-economic-coverage.php`; expect valid syntax and a fixture-level failure whenever line or branch metrics are absent/below 100. [FR-043]
- [ ] T003 Add the `test:economic-coverage` orchestration command in `composer.json`; prerequisite: T001–T002; run `docker compose exec -T -u sail -e XDEBUG_MODE=coverage laravel.test composer test:economic-coverage`; expect the command to invoke Xdebug/PHPUnit plus the assertion and to fail until the economic tests/core are complete. [FR-043]

---

## Phase 2: Foundational Contracts and Shared Integration

**Purpose**: establish final Greenfield schema, exact projection types and common failure behavior before any User Story implementation.

**CRITICAL**: complete this phase before all User Story phases.

### Failing tests first

- [ ] T004 [P] Write Greenfield schema and environment-guard tests in `tests/Feature/Expenses/AnnualExpenseSchemaTest.php` and extend `tests/Architecture/DevelopmentEnvironmentTest.php`; prerequisite: T001; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Expenses/AnnualExpenseSchemaTest.php tests/Architecture/DevelopmentEnvironmentTest.php`; expect failure until economic basis fields exist, lifecycle columns are absent, decimals/FKs are exact and fresh reset is environment-guarded. [FR-001, FR-023, FR-035, FR-040, FR-041, SC-008, SC-011]
- [ ] T005 [P] Write the pure canonical decision table in `tests/Accounting/Unit/AnnualEconomicProjectionTest.php`; prerequisite: T002; run `docker compose exec -T -u sail laravel.test php artisan test tests/Accounting/Unit/AnnualEconomicProjectionTest.php`; expect failure until selection, actual-only, positive/zero/negative Actual, Net/Gross and deleted/non-current branches are implemented. [FR-015–FR-017, FR-021, FR-027–FR-030, SC-004, SC-005, SC-007]
- [ ] T006 [P] Write MySQL projection persistence/reconciliation tests in `tests/Accounting/Integration/AnnualExpenseProjectionTest.php`; prerequisite: T004; run `docker compose exec -T -u sail laravel.test php artisan test tests/Accounting/Integration/AnnualExpenseProjectionTest.php`; expect failure until current rows load once, Date outside year is retained and per-Expense/annual totals reconcile. [FR-011–FR-014, FR-027–FR-030, SC-006, SC-007]
- [ ] T007 [P] Write common error envelope regressions for Slice codes and no-leakage in `tests/Feature/Api/Contracts/AnnualExpenseErrorContractTest.php`; prerequisite: none; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Api/Contracts/AnnualExpenseErrorContractTest.php`; expect failure until stable 403/404/409/422/500 mappings and correlation IDs exist. [FR-033–FR-035, FR-038, FR-039, SC-003]

### Shared-owner implementation

- [ ] T008 Primary integration owner: consolidate Tenant/Expense/ExpenseRow target schema in `database/migrations/2026_08_03_000007_create_tenants_table.php`, `database/migrations/2026_08_03_020001_create_expenses_table.php`, `database/migrations/2026_08_03_020002_create_expense_rows_table.php` and `database/migrations/2026_08_09_100001_add_annual_budget_lifecycle.php` (including `economic_basis`, `economic_basis_locked_at`, no Expense lifecycle columns and exact composite FKs); prerequisite: red T004; run `docker compose exec -T -u sail laravel.test php artisan migrate:fresh --seed --env=testing --force && docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Expenses/AnnualExpenseSchemaTest.php`; expect a clean MySQL schema satisfying T004 with no legacy mapping. [FR-001, FR-003, FR-023, FR-035, FR-040, FR-041]
- [ ] T009 [P] Align economic-basis names and schema-safe casts/relations in `app/Domain/Tenancy/Enums/EconomicBasis.php`, `app/Models/Tenant.php`, `app/Models/Expense.php`, `app/Models/ExpenseRow.php` and remove superseded `app/Domain/Tenancy/Enums/BudgetBasis.php`; defer Expense lifecycle model metadata and enum removal to US3; prerequisite: T008; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Expenses/AnnualExpenseSchemaTest.php`; expect economic-basis metadata matches the target schema while lifecycle cleanup remains explicitly ordered under US3. [FR-001, FR-003, FR-023, FR-024]
- [ ] T010 Primary integration owner: implement immutable projection DTOs in `app/Domain/Economics/Data/EconomicMeasure.php`, `app/Domain/Economics/Data/ProjectedEconomicLine.php`, `app/Domain/Economics/Data/ExpenseEconomicProjection.php`, `app/Domain/Economics/Data/AnnualEconomicProjection.php` and make `app/Domain/Economics/Services/EconomicEngine.php` the sole calculator; prerequisite: red T005 and schema T008–T009; run `docker compose exec -T -u sail laravel.test php artisan test tests/Accounting/Unit/AnnualEconomicProjectionTest.php`; expect T005 green with exact BCMath and no formula outside the core. [FR-020, FR-021, FR-027–FR-029, FR-039]
- [ ] T011 Primary integration owner: adapt the single dataset loader in `app/Domain/Reporting/Queries/EconomicDatasetQuery.php` and inputs in `app/Domain/Economics/Data/EconomicDataset.php`, `app/Domain/Economics/Data/EconomicLine.php` and `app/Domain/Economics/Data/EconomicScope.php` to build the final projection without N+1 queries; prerequisite: T010 and red T006; run `docker compose exec -T -u sail laravel.test php artisan test tests/Accounting/Integration/AnnualExpenseProjectionTest.php`; expect T006 green and only current same-Tenant rows included. [FR-011, FR-027–FR-030]
- [ ] T012 Primary integration owner: map the stable error catalogue in `app/Support/Api/ApiErrorResponse.php` and exception rendering in `bootstrap/app.php`; prerequisite: red T007; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Api/Contracts/AnnualExpenseErrorContractTest.php`; expect exact codes/status/envelope/correlation and foreign-ID non-disclosure. [FR-033–FR-035, FR-038, FR-039]
- [ ] T013 [P] Update test/demo states in `database/factories/TenantFactory.php`, `database/factories/ExpenseFactory.php`, `database/factories/ExpenseRowFactory.php`, `database/seeders/DemoDataSeeder.php` and wire opt-in demo invocation in `database/seeders/DatabaseSeeder.php`; prerequisite: T008–T010; run `docker compose exec -T -u sail laravel.test php artisan migrate:fresh --seed --env=testing --force`; expect distinct Net/Gross Tenants, valid selected planning, signed Actuals and an out-of-year Date. [FR-042, SC-011]

**Checkpoint**: Greenfield schema, projection contract, error envelope and coverage tooling are stable; no User Story implementation has to redefine them.

---

## Phase 3: User Story 1 — Impostare il Contesto Economico (Priority: P1) 🎯 MVP

**Goal**: configure official Tenant basis and maintain fail-closed Tenant/Year context in a minimal top shell.

**Independent Test**: switch basis before/after lock, then traverse Expense/Budget/Report across two Tenants/years with dirty and late-response guards.

### Tests first

- [ ] T014 [P] [US1] Write settings lock/allow/deny/inactive/stale/audit tests in `tests/Feature/PlatformOperations/TenantEconomicBasisTest.php`; prerequisite: Phase 2; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/PlatformOperations/TenantEconomicBasisTest.php`; expect failure until persisted lock timestamp and target field names work atomically. [FR-001–FR-004, FR-034, FR-036, FR-038, SC-001]
- [ ] T015 [P] [US1] Write API settings contract tests in `tests/Feature/Api/Tenancy/TenantEconomicBasisApiTest.php`; prerequisite: Phase 2; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Api/Tenancy/TenantEconomicBasisApiTest.php`; expect failure until Resource/request use `economic_basis`/`economic_basis_locked_at` and exact errors. [FR-002, FR-003, FR-033–FR-035]
- [ ] T016 [P] [US1] Write top-shell/context tests in `frontend/src/layout/AppLayout.test.tsx`, `frontend/src/context/PlanningYearContext.test.tsx` and `frontend/src/components/header/WorkspaceContextBar.test.tsx`; prerequisite: Phase 2; run `docker compose exec -T frontend npm run test:unit -- AppLayout PlanningYearContext WorkspaceContextBar`; expect failure until no sidebar, Tenant/year reset, dirty guard, loading/empty/error and late-response protection exist. [FR-005–FR-010, SC-002]
- [ ] T017 [P] [US1] Write settings adapter/form tests in `frontend/src/api/tenantSettings.test.ts` and `frontend/src/components/settings/TenantGeneralSettingsForm.test.tsx`; prerequisite: Phase 2; run `docker compose exec -T frontend npm run test:unit -- tenantSettings TenantGeneralSettingsForm`; expect failure until target names, decimal strings, lock state, 409 and input preservation are handled. [FR-001–FR-004, FR-031, FR-033]

### Implementation

- [ ] T018 [US1] Update settings mutation/DTO/resource/controller in `app/Domain/Tenancy/Actions/UpdateTenantSettings.php`, `app/Domain/Tenancy/Data/TenantContext.php`, `app/Http/Resources/Api/V1/TenantSettingsResource.php` and `app/Http/Controllers/Api/V1/TenantSettingsController.php`; prerequisite: red T014–T015 and T008–T012; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/PlatformOperations/TenantEconomicBasisTest.php tests/Feature/Api/Tenancy/TenantEconomicBasisApiTest.php`; expect atomic basis update, permanent lock enforcement, version increment and minimal audit. [FR-001–FR-004, FR-034, FR-036, FR-038]
- [ ] T019 [P] [US1] Update settings client/form in `frontend/src/api/tenantSettings.ts` and `frontend/src/components/settings/TenantGeneralSettingsForm.tsx`; prerequisite: red T017 and T018 contract; run `docker compose exec -T frontend npm run test:unit -- tenantSettings TenantGeneralSettingsForm`; expect exact target fields, disabled locked control and preserved input/errors. [FR-001–FR-004, FR-031, FR-033]
- [ ] T020 [US1] Implement the context transition/dirty guard in `frontend/src/context/PlanningYearContext.tsx`, `frontend/src/context/ApplicationContext.tsx` and `frontend/src/hooks/useWorkspaceContextGuard.ts`; prerequisite: red T016; run `docker compose exec -T frontend npm run test:unit -- PlanningYearContext WorkspaceContextBar`; expect Tenant switch clears annual state, stale responses are ignored and unsaved work is confirmed. [FR-006–FR-010]
- [ ] T021 [US1] Replace permanent sidebar layout with the minimal TailAdmin top shell in `frontend/src/layout/AppLayout.tsx`, `frontend/src/layout/AppHeader.tsx` and new `frontend/src/components/header/WorkspaceContextBar.tsx`; prerequisite: T020 and red T016; run `docker compose exec -T frontend npm run test:unit -- AppLayout WorkspaceContextBar`; expect brand/core navigation/Tenant/year/user, responsive keyboard support and no sidebar width reservation. [FR-005–FR-010, SC-002]
- [ ] T022 [US1] Primary integration owner: verify existing abilities and target middleware bindings in `database/seeders/PermissionCatalogueSeeder.php`, `tests/Feature/Authorization/PermissionCatalogueTest.php` and `routes/api/v1/tenant-settings.php`; prerequisite: T014–T021; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Authorization/PermissionCatalogueTest.php tests/Feature/Api/Tenancy/TenantEconomicBasisApiTest.php`; expect no new role semantics and server-side `view|update` enforcement. [FR-002, FR-034]

**Checkpoint**: US1 works independently with existing/seeded Expense data.

---

## Phase 4: User Story 2 — Registrare la Spesa Autorevole (Priority: P1)

**Goal**: create/update one authoritative Expense aggregate with exact selection, signed Actuals and real dates independent of economic year.

**Independent Test**: canonical 2025 Expense with planning plus `40 + 65 - 5`, one 2026 date, optimistic collision, revision/audit and foreign-Tenant attempts.

### Tests first

- [ ] T023 [P] [US2] Write aggregate validation/decimal/date tests in `tests/Feature/Expenses/AuthoritativeExpenseTest.php`; prerequisite: Phase 2; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Expenses/AuthoritativeExpenseTest.php`; expect failure for missing/double selection and negative planning, success for signed Actual/actual-only/out-of-year Date. [FR-012–FR-017, FR-020, FR-021, SC-004–SC-006]
- [ ] T024 [P] [US2] Extend atomic rollback/stale/revision tests in `tests/Feature/Expenses/ExpenseActionRollbackTest.php` and add `tests/Feature/Revisions/AnnualExpenseRevisionTest.php`; prerequisite: Phase 2; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Expenses/ExpenseActionRollbackTest.php tests/Feature/Revisions/AnnualExpenseRevisionTest.php`; expect failure until root+row collision and revision/audit failures roll back completely with one full snapshot on success. [FR-018, FR-019, FR-036–FR-038, SC-009]
- [ ] T025 [P] [US2] Write preview/create/read/update/foreign/inactive HTTP tests in `tests/Feature/Api/Expenses/AnnualExpenseApiTest.php`; prerequisite: Phase 2; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Api/Expenses/AnnualExpenseApiTest.php`; expect failure until target payload, preview non-persistence and safe errors are implemented. [FR-014–FR-022, FR-032–FR-035]
- [ ] T026 [P] [US2] Write editor/api adapter tests in `frontend/src/api/expenses.test.ts`, `frontend/src/components/expenses/ExpenseEditor.test.tsx` and `frontend/src/components/expenses/ExpenseEditorRows.test.tsx`; prerequisite: Phase 2; run `docker compose exec -T frontend npm run test:unit -- expenses ExpenseEditor ExpenseEditorRows`; expect failure until exact strings, one selection, signed Actual, outside-year Date, stale/422 preservation and no local totals work. [FR-014–FR-022, FR-031–FR-033]

### Implementation

- [ ] T027 [US2] Align write DTO and full-aggregate validator in `app/Domain/Expenses/Data/SaveExpenseData.php`, `app/Domain/Expenses/Data/SaveExpenseRowData.php` and `app/Domain/Expenses/Services/ExpenseAggregateValidator.php`; prerequisite: red T023; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Expenses/AuthoritativeExpenseTest.php`; expect deterministic selection, date/sign/decimal/relationship validation without civil-year coupling. [FR-012–FR-017, FR-020, FR-021]
- [ ] T028 [US2] Implement shared normalization and preview in new `app/Domain/Expenses/Services/PrepareExpenseAggregate.php` and `app/Domain/Expenses/Actions/PreviewExpense.php`; prerequisite: T027 and T010; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Expenses/AuthoritativeExpenseTest.php tests/Feature/Api/Expenses/AnnualExpenseApiTest.php`; expect preview/save share calculation while preview persists no row/audit/revision. [FR-018, FR-020–FR-022, FR-027–FR-029]
- [ ] T029 [US2] Update `app/Domain/Expenses/Actions/CreateExpense.php`, `app/Domain/Expenses/Actions/UpdateExpense.php` and `app/Domain/Expenses/Actions/Concerns/ManagesExpenseAggregate.php`; prerequisite: T027–T028 and red T024; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Expenses/AuthoritativeExpenseTest.php tests/Feature/Expenses/ExpenseActionRollbackTest.php tests/Feature/Revisions/AnnualExpenseRevisionTest.php`; expect root+rows+selection atomicity, row/root optimistic locks, one revision/audit and full rollback. [FR-017–FR-019, FR-036–FR-038]
- [ ] T030 [US2] Update Expense DTO/query/API presentation in `app/Domain/Expenses/Data/ExpenseDetail.php`, `app/Domain/Expenses/Queries/ExpenseDetailQuery.php`, `app/Http/Resources/Api/V1/ExpenseRowResource.php`, `app/Http/Resources/Api/V1/ExpenseDetailResource.php` and `app/Http/Controllers/Api/V1/ExpenseController.php`, then have the Primary integration owner add the literal preview route before `/{expense}` in `routes/api/v1/expenses.php`; prerequisite: T025 red and T028–T029; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Api/Expenses/AnnualExpenseApiTest.php`; expect target preview/CRUD payload with separated year/date and projection totals and no competing route writer. [FR-014, FR-018–FR-022, FR-027–FR-033]
- [ ] T031 [US2] Update Expense TypeScript contracts/adapters in `frontend/src/api/expenses.ts` and editor types in `frontend/src/components/expenses/expenseEditorTypes.ts`; prerequisite: T030 and red T026; run `docker compose exec -T frontend npm run test:unit -- expenses`; expect only target string-decimal/version/selection/date fields and common error handling. [FR-020, FR-022, FR-031–FR-033]
- [ ] T032 [US2] Update editor components in `frontend/src/components/expenses/ExpenseEditor.tsx`, `frontend/src/components/expenses/ExpenseEditorRows.tsx` and `frontend/src/components/expenses/ExpenseTotals.tsx`; prerequisite: T031 and red T026; run `docker compose exec -T frontend npm run test:unit -- expenses ExpenseEditor ExpenseEditorRows`; expect one planning selector, signed Actual input, Date real copy, server preview totals and input preservation. [FR-014–FR-022, FR-031]
- [ ] T033 [US2] Update create/edit/detail pages in `frontend/src/pages/Expenses/ExpenseNew.tsx`, `frontend/src/pages/Expenses/ExpenseEdit.tsx` and `frontend/src/pages/Expenses/ExpenseDetail.tsx`; prerequisite: T032; run `docker compose exec -T frontend npm run test:unit -- ExpenseDetail ExpenseEditor`; expect usable document flow with separate Anno Economico/Data and correct loading/empty/error states. [FR-022, FR-031–FR-033]

**Checkpoint**: US2 works independently against direct Expense endpoints and seeded dimensions.

---

## Phase 5: User Story 3 — Lavorare Senza Lifecycle della Spesa (Priority: P2)

**Goal**: remove every Backend/Frontend surface of Expense open/closed, close outcome and lifecycle-based move.

**Independent Test**: schema/API/route/compiled UI contain no lifecycle field, filter, badge or action and legacy endpoints have no effect.

### Tests first

- [ ] T034 [P] [US3] Add route/resource/register lifecycle-absence tests in `tests/Feature/Api/Expenses/ExpenseLifecycleRemovalTest.php`; prerequisite: Phase 2 and T030; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Api/Expenses/ExpenseLifecycleRemovalTest.php`; expect failure while close/move/bulk variants or legacy fields remain. [FR-023–FR-026, FR-032, SC-008]
- [ ] T035 [P] [US3] Add frontend lifecycle-absence regressions in `frontend/src/pages/Expenses/ExpenseRegister.test.tsx`, `frontend/src/pages/Expenses/ExpenseDetail.test.tsx` and `frontend/src/api/expenses.test.ts`; prerequisite: T031; run `docker compose exec -T frontend npm run test:unit -- ExpenseRegister ExpenseDetail expenses`; expect failure while state filters/types/controls/copy remain. [FR-024–FR-026, FR-031, FR-032, SC-008]

### Implementation

- [ ] T036 [US3] Remove close/move lifecycle code and metadata in `app/Models/Expense.php`, `app/Domain/Expenses/Enums/ExpenseState.php`, `app/Domain/Expenses/Enums/ExpenseClosureOutcome.php`, `app/Domain/Expenses/Actions/CloseExpense.php`, `app/Domain/Expenses/Actions/MoveExpense.php`, `app/Domain/Expenses/Actions/BulkExpenseAction.php`, `app/Domain/Expenses/Data/ExpenseRegisterFilterData.php`, `app/Domain/Expenses/Data/ExpenseRegisterRow.php` and `app/Domain/Expenses/Queries/ExpenseRegisterQuery.php`; prerequisite: red T034; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Api/Expenses/ExpenseLifecycleRemovalTest.php`; expect no lifecycle enum, cast, branch, or implicit reopen in model/domain/query code. [FR-023–FR-026]
- [ ] T037 [US3] Remove controller/resource lifecycle fields and methods in `app/Http/Controllers/Api/V1/ExpenseController.php`, `app/Http/Resources/Api/V1/ExpenseDetailResource.php` and `app/Http/Resources/Api/V1/ExpenseRegisterResource.php`; prerequisite: T036; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Api/Expenses/ExpenseLifecycleRemovalTest.php`; expect target Resources and bulk validation reject state/close/move payloads. [FR-024–FR-026, FR-032]
- [ ] T038 [US3] Primary integration owner: remove close/move routes from `routes/api/v1/expenses.php` and verify no superseded route/ability documentation in `tests/Feature/Api/ApiOnlyRouteTest.php` and `tests/Feature/Authorization/PermissionCatalogueTest.php`; prerequisite: red T034 and T036–T037; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Api/Expenses/ExpenseLifecycleRemovalTest.php tests/Feature/Api/ApiOnlyRouteTest.php tests/Feature/Authorization/PermissionCatalogueTest.php`; expect close/move paths absent and existing Expense abilities still coherent. [FR-025, FR-034]
- [ ] T039 [US3] Remove lifecycle UI/types in `frontend/src/api/expenses.ts`, `frontend/src/components/expenses/ExpenseFilters.tsx`, `frontend/src/components/expenses/ExpenseRegisterTable.tsx`, `frontend/src/components/expenses/ExpenseBulkActions.tsx`, `frontend/src/components/expenses/ExpenseCloseModal.tsx` and `frontend/src/components/expenses/ExpenseMoveModal.tsx`; prerequisite: red T035 and T038; run `docker compose exec -T frontend npm run test:unit -- ExpenseRegister ExpenseDetail expenses`; expect no state filter/column/badge/close/move affordance or request type. [FR-024–FR-026, FR-031, FR-032]
- [ ] T040 [US3] Remove remaining lifecycle rendering from `frontend/src/pages/Expenses/ExpenseRegister.tsx`, `frontend/src/pages/Expenses/ExpenseDetail.tsx`, `frontend/src/components/expenses/ExpenseActionModal.tsx` and `frontend/src/components/expenses/ExpenseColumnSettings.tsx`; prerequisite: T039; run `docker compose exec -T frontend npm run test:unit -- ExpenseRegister ExpenseDetail expenses`; expect full lifecycle-absence suite green and valid preferences normalized without `state`. [FR-024–FR-026, SC-008]

**Checkpoint**: US3 works independently using any target Expense fixture; no deprecated capability is callable.

---

## Phase 6: User Story 4 — Riconciliare Ogni Superficie (Priority: P2)

**Goal**: make Document, Register, Budget and Report Drill-Down consume one annual projection with exact Net/Gross parity.

**Independent Test**: canonical dataset sums to the same cent on all four surfaces in both bases, with no N+1 or React formula.

### Tests first

- [ ] T041 [P] [US4] Add four-consumer MySQL reconciliation tests in `tests/Accounting/Integration/AnnualExpenseSurfaceReconciliationTest.php`; prerequisite: T010–T011 and US2; run `docker compose exec -T -u sail laravel.test php artisan test tests/Accounting/Integration/AnnualExpenseSurfaceReconciliationTest.php`; expect failure until every consumer selects the same projection results. [FR-027–FR-031, SC-007]
- [ ] T042 [P] [US4] Add constant-query benchmark in `tests/Accounting/Integration/AnnualExpenseProjectionQueryCountTest.php`; prerequisite: T011; run `docker compose exec -T -u sail laravel.test php artisan test tests/Accounting/Integration/AnnualExpenseProjectionQueryCountTest.php`; expect failure if query count grows between 1 and 1,000 Expenses. [FR-027, FR-030]
- [ ] T043 [P] [US4] Add Budget/Report HTTP parity tests in `tests/Feature/Api/Reporting/AnnualExpenseProjectionApiTest.php`; prerequisite: US2–US3; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Api/Reporting/AnnualExpenseProjectionApiTest.php`; expect failure until target totals/line IDs, abilities, no state metrics and reconciliation are exposed. [FR-008, FR-029–FR-035]
- [ ] T044 [P] [US4] Add surface adapter/component tests in `frontend/src/api/budget.test.ts`, `frontend/src/api/reports.test.ts`, `frontend/src/components/expenses/ExpenseRegisterTable.test.tsx`, `frontend/src/components/budget/BudgetView.test.tsx` and `frontend/src/components/reports/ReportsView.test.tsx`; prerequisite: US2–US3; run `docker compose exec -T frontend npm run test:unit -- budget reports ExpenseRegisterTable BudgetView ReportsView`; expect failure until one `ProjectionTotals` shape is displayed without client calculation or fallback. [FR-029–FR-033, SC-007]

### Implementation

- [ ] T045 [US4] Adapt Expense detail/register consumers in `app/Domain/Expenses/Queries/ExpenseDetailQuery.php`, `app/Domain/Expenses/Queries/ExpenseRegisterQuery.php`, `app/Domain/Expenses/Data/ExpenseDetail.php` and `app/Domain/Expenses/Data/ExpenseRegisterRow.php`; prerequisite: red T041 and T010–T011; run `docker compose exec -T -u sail laravel.test php artisan test tests/Accounting/Integration/AnnualExpenseSurfaceReconciliationTest.php`; expect per-Expense/filtered totals are slices of `AnnualEconomicProjection`. [FR-027–FR-030]
- [ ] T046 [US4] Primary integration owner: adapt Budget consumer in `app/Domain/Budget/Queries/AnnualBudgetQuery.php` and `app/Http/Resources/Api/V1/AnnualBudgetResource.php`; prerequisite: red T041/T043 and T045; run `docker compose exec -T -u sail laravel.test php artisan test tests/Accounting/Integration/AnnualExpenseSurfaceReconciliationTest.php tests/Feature/Api/Reporting/AnnualExpenseProjectionApiTest.php`; expect preparation Budget totals/expenses use the projection with no Expense lifecycle counts/formulas. [FR-027–FR-030, FR-032]
- [ ] T047 [US4] Primary integration owner: adapt Report consumer in `app/Domain/Reporting/Queries/AnnualEconomicReportQuery.php`, `app/Domain/Reporting/Queries/EconomicReportQuery.php` and `app/Http/Controllers/Api/V1/EconomicReportController.php`; prerequisite: red T041/T043 and T045; run `docker compose exec -T -u sail laravel.test php artisan test tests/Accounting/Integration/AnnualExpenseSurfaceReconciliationTest.php tests/Feature/Api/Reporting/AnnualExpenseProjectionApiTest.php`; expect groups/drill-down line identities reconcile and state filters/metrics are absent. [FR-027–FR-030, FR-032]
- [ ] T048 [US4] Align common Resource money shape in `app/Http/Resources/Api/V1/ExpenseMoneyResource.php`, `app/Http/Resources/Api/V1/ExpenseDetailResource.php` and `app/Http/Resources/Api/V1/ExpenseRegisterResource.php`; prerequisite: T045–T047; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Api/Expenses/AnnualExpenseApiTest.php tests/Feature/Api/Reporting/AnnualExpenseProjectionApiTest.php`; expect `currency`, `economic_basis`, `current_planning`, `actual` and component/official strings match the API contract. [FR-020, FR-022, FR-029–FR-033]
- [ ] T049 [US4] Implement target TypeScript projection types/adapters in `frontend/src/api/expenses.ts`, `frontend/src/api/budget.ts` and `frontend/src/api/reports.ts`; prerequisite: red T044 and T048; run `docker compose exec -T frontend npm run test:unit -- expenses budget reports`; expect exact shared shape, no numeric coercion and no legacy state fields. [FR-020, FR-024, FR-029–FR-033]
- [ ] T050 [US4] Update surface components in `frontend/src/components/expenses/ExpenseTotals.tsx`, `frontend/src/components/expenses/ExpenseRegisterTable.tsx`, `frontend/src/components/budget/BudgetView.tsx`, `frontend/src/components/reports/ReportsView.tsx` and `frontend/src/components/reports/ReportEconomicChart.tsx`; prerequisite: T049 and red T044; run `docker compose exec -T frontend npm run test:unit -- budget reports ExpenseRegisterTable BudgetView ReportsView`; expect exact received totals, drill-down navigation and loading/empty/error/no-fallback behavior. [FR-022, FR-030–FR-033, SC-007]
- [ ] T051 [US4] Add diagnostic invariant handling test/implementation in `tests/Accounting/Unit/AnnualEconomicProjectionTest.php` and `app/Domain/Economics/Services/EconomicEngine.php` via the primary integration owner; prerequisite: T010 and T050; run `docker compose exec -T -u sail laravel.test php artisan test tests/Accounting/Unit/AnnualEconomicProjectionTest.php tests/Feature/Api/Contracts/AnnualExpenseErrorContractTest.php`; expect reconciliation failures become `ECONOMIC_RECONCILIATION_FAILED` with no partial/fallback value. [FR-039]

**Checkpoint**: all User Stories work; the four surfaces agree at the cent.

---

## Phase 7: Cross-cutting Verification and Delivery

**Purpose**: prove security, data construction, coverage, parity and complete Done criteria.

- [ ] T052 [P] Extend authorization matrix coverage in `tests/Feature/Expenses/ExpenseAuthorizationTest.php`, `tests/Feature/Api/Tenancy/TenantEconomicBasisApiTest.php` and `tests/Feature/Api/Reporting/AnnualExpenseProjectionApiTest.php`; prerequisite: US1–US4; run those three files; expect allow same Tenant, deny permission, foreign not-found, inactive user/Tenant and zero side effects for each capability. [FR-002, FR-008, FR-034, FR-035, SC-003]
- [ ] T053 [P] Add Factory/Seeder validity regression in `tests/Feature/Expenses/AnnualExpenseSeederTest.php`; prerequisite: T013 and US1–US4; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Expenses/AnnualExpenseSeederTest.php`; expect canonical Net/Gross fixtures, signed Actual/out-of-year Date and no invalid lifecycle/Plafond target records. [FR-042, SC-011]
- [ ] T054 Run the economic Gate defined by `composer.json`, `phpunit.economic-coverage.xml` and `tests/Support/assert-economic-coverage.php`; prerequisite: all economic tasks; run `docker compose exec -T -u sail -e XDEBUG_MODE=coverage laravel.test composer test:economic-coverage`; expect 100% line, 100% branch and exit 0, otherwise return ownership to the missing test/core task. [FR-043, SC-010]
- [ ] T055 Run Backend static/fresh/Accounting/Application gates without editing code in `composer.json`; prerequisite: T052–T054; run `docker compose exec -T -u sail laravel.test composer test:static && docker compose exec -T -u sail laravel.test composer test:prepare && docker compose exec -T -u sail laravel.test composer test:accounting && docker compose exec -T -u sail laravel.test composer test:application`; expect all exit 0 and record any pre-existing/environment failure separately. [FR-040, FR-044, SC-012]
- [ ] T056 [P] Run Frontend unit/dark-token/lint/build gate defined by `frontend/package.json`; prerequisite: US1–US4; run `docker compose exec -T frontend npm run verify`; expect all adapter/component/loading/empty/error tests, ESLint and TypeScript/Vite build exit 0. [FR-031, FR-032, FR-044, SC-012]
- [ ] T057 Execute the complete manual/browser acceptance matrix in `specs/023-annual-expense-workspace/quickstart.md`; prerequisite: T055–T056; run the documented isolated stack and scenarios; expect observable US1–US4 outcomes in desktop/responsive/light/dark with actual evidence, not simulated results. [SC-001–SC-009, SC-011]
- [ ] T058 Perform read-only domain, security/tenancy and surface-parity review against `specs/023-annual-expense-workspace/spec.md`, `contracts/api-contract.md` and the implementation diff; prerequisite: T054–T057; run no mutation command; expect zero CRITICAL/HIGH, every user-facing Backend capability mapped to API client/UI/error/test and any MEDIUM explicitly resolved or justified. [FR-001–FR-044, SC-012]
- [ ] T059 Primary integration owner: after verified implementation only, update feature-local contract if actual route/resource details differ and propagate durable implemented rules/status in `docs/DOMAIN.md`, `docs/ARCHITECTURE.md`, `docs/STATUS.md` and `specs/README.md`; prerequisite: T058 green; run `git diff --check` plus relevant docs checks; expect permanent docs describe only verified behavior and no global task/readiness/analyze report is created. [SC-012]

---

## Dependencies and Execution Order

### Slice Dependency

- **Slice 023 dependency**: none (`READY`).
- Slice 024 and all later program slices depend on the consolidated schema/projection contract delivered here.

### Phase Dependencies

```text
Phase 1 Setup
  └─> Phase 2 Foundation/shared integration
       ├─> US1 Context/Basis
       └─> US2 Authoritative Expense
             └─> US3 Lifecycle Removal
                   └─> US4 Surface Reconciliation
                         └─> Phase 7 Full Gate
```

- US1 and US2 can start in parallel after Phase 2 because their implementation files are distinct except shared contracts already stabilized.
- US3 follows US2 because both edit Expense controller/types/components and must not have concurrent writers.
- US4 follows US2–US3 and consumes the final target payload.
- Primary integration owner tasks are serialized across T008, T010–T012, T022, T038, T046–T047, T051 and T059.

### User Story Independence

- **US1**: independently testable with seeded Expenses; does not require US2 UI.
- **US2**: independently testable via direct Expense API/editor with a fixed Tenant/Year fixture; does not require shell navigation.
- **US3**: independently testable as absence contract against seeded target Expenses.
- **US4**: independently testable with fixtures loaded directly into the projection, although implementation intentionally follows US2/US3 to avoid shared-file conflicts.

## Parallel Examples

### Foundation

```text
T004 schema tests || T005 projection Unit || T006 Accounting || T007 error contract
```

### US1

```text
T014 settings domain || T015 settings API || T016 shell/context || T017 settings frontend
```

### US2

```text
T023 aggregate cases || T024 rollback/revision || T025 HTTP || T026 frontend
```

### US4

```text
T041 reconciliation || T042 query count || T043 HTTP parity || T044 frontend parity
```

No two parallel examples write the same file. MySQL tests may execute in parallel only with isolated databases/Compose project names; otherwise serialize T004/T006/T014/T023/T024/T025/T041–T043/T052–T053.

## Requirement Coverage Map

| Requirement group | Task IDs |
|---|---|
| FR-001–FR-004 Basis | T004, T008–T009, T014–T019 |
| FR-005–FR-010 Shell/context | T016, T020–T022 |
| FR-011–FR-022 Expense aggregate/money/date | T005–T006, T010–T011, T023–T033 |
| FR-023–FR-026 Lifecycle removal | T004, T008–T009, T034–T040 |
| FR-027–FR-033 Projection/API/parity | T005–T012, T025–T033, T041–T051 |
| FR-034–FR-039 Security/revision/observability | T007, T012, T014–T015, T022, T024–T030, T051–T052 |
| FR-040–FR-044 Greenfield/tests | T001–T004, T008, T013, T053–T058 |
| SC-001–SC-012 | T014–T058, especially T054–T058 final evidence |

## Implementation Strategy

### MVP first

1. Complete Setup and Foundation.
2. Complete US1 and validate context/basis independently.
3. Complete US2 and validate authoritative Expense independently.
4. Serialize US3 then US4 because they share consumer files.
5. Stop before delivery unless every Phase 7 Gate is evidenced.

### Done Criteria

- All 59 tasks satisfy their expected result.
- Task format, IDs, paths, prerequisites and commands remain exact after implementation updates.
- Fresh MySQL, seed, static, Accounting, Application, Frontend, lint/build and Xdebug line+branch gates pass.
- Same-Tenant allow, permission deny, foreign Tenant no-leakage, inactive user/Tenant, stale version and rollback are green.
- No lifecycle Expense field/action/copy remains.
- Document/Register/Budget/Report reconcile at the cent in Net and Gross.
- No CRITICAL/HIGH review or parity finding remains.
- No push, PR or remote merge is implied by this task list.
