# Tasks: Workspace Annuale e Spesa Autorevole

**Input**: design documents from `specs/023-annual-expense-workspace/`

**Prerequisites**: `spec.md`, `plan.md`, `research.md`, `data-model.md`, `contracts/api-contract.md`, `quickstart.md`, `lifecycle-inventory.md`

**Tests**: mandatory and tests-first. Every test task must fail for the missing target behavior before its implementation task begins.

**Shared-owner rule**: tasks explicitly marked “Primary integration owner” may be implemented only by that owner. A Slice writer provides tests/contract and requests integration; it must not create a parallel migration, economic core, error catalogue, route, permission or permanent-doc implementation.

## Format: `[ID] [P?] [Story] Description`

- `[P]`: different files and no dependency on another incomplete task in the same phase.
- `[US#]`: maps to the independently testable User Story in `spec.md`.
- Every task declares exact path or an exact versioned inventory, prerequisite, verification command (directly or by unambiguous task/inventory reference) and expected result.

## Phase 1: Setup and Coverage Tooling

**Purpose**: make the required economic coverage Gate executable before business implementation.

- [x] T001 Install and enable Xdebug as a test-only PHP extension in `docker/8.3/Dockerfile`; prerequisite: none; run `docker compose build laravel.test && docker compose run --rm -e XDEBUG_MODE=coverage laravel.test php -m`; expect exit 0 and one `xdebug` module without changing production dependencies. [FR-043]
- [x] T002 [P] Add the versioned mandatory core manifest in `tests/Support/economic-coverage-classes.php`, focused configuration in `phpunit.economic-coverage.xml`, strict Cobertura assertion in `tests/Support/assert-economic-coverage.php` and parser fixtures/tests in `tests/Architecture/EconomicCoverageGateTest.php`; prerequisite: none; run `docker compose exec -T -u sail laravel.test php artisan test tests/Architecture/EconomicCoverageGateTest.php`; expect fixture-level failures for a missing manifest class, absent line/branch metric and either metric below 100, including every pure economic calculator/value object introduced or modified by this Slice. [FR-043]
- [x] T003 Add the `test:economic-coverage` orchestration command in `composer.json`; prerequisite: T001–T002; run `docker compose exec -T -u sail -e XDEBUG_MODE=coverage laravel.test composer test:economic-coverage`; expect the command to invoke Xdebug/PHPUnit plus the assertion and to fail until the economic tests/core are complete. [FR-043]

---

## Phase 2: Foundational Contracts and Shared Integration

**Purpose**: establish final Greenfield schema, exact projection types and common failure behavior before any User Story implementation.

**CRITICAL**: complete this phase before all User Story phases.

### Failing tests first

- [x] T004 [P] Write Greenfield schema and destructive-environment guard tests in `tests/Feature/Expenses/AnnualExpenseSchemaTest.php`, `tests/Feature/Console/TestResetGreenfieldCommandTest.php` and `tests/Architecture/DevelopmentEnvironmentTest.php`; prerequisite: T001; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Expenses/AnnualExpenseSchemaTest.php tests/Feature/Console/TestResetGreenfieldCommandTest.php tests/Architecture/DevelopmentEnvironmentTest.php`; expect failure until internal `budget_basis` plus lock timestamp exist, lifecycle columns and Project/Contract XOR are absent, RevisionBatchItem uses a composite Tenant FK, revision correlation is non-unique, decimals/FKs are exact and reset rejects unsafe env/driver/host/database/strict-mode combinations. [FR-001, FR-023, FR-035, FR-040, FR-041, SC-008, SC-011]
- [x] T005 [P] Write the pure numeric and projection decision table in `tests/Accounting/Unit/MoneyCalculatorTest.php`, `tests/Accounting/Unit/VatCalculatorTest.php` and `tests/Accounting/Unit/AnnualEconomicProjectionTest.php`; prerequisite: T002; run `docker compose exec -T -u sail laravel.test php artisan test tests/Accounting/Unit/MoneyCalculatorTest.php tests/Accounting/Unit/VatCalculatorTest.php tests/Accounting/Unit/AnnualEconomicProjectionTest.php`; expect failure until direct/calculated input XOR, exact grammar, scale-12 BCMath, half-away-from-zero including `0.005`/`-0.005`, VAT included/excluded, overflow, selection, actual-only, Net/Gross and deleted/non-current branches are implemented. [FR-015–FR-017, FR-020, FR-021, FR-027–FR-030, SC-004, SC-005, SC-007]
- [x] T006 [P] Write MySQL projection persistence/reconciliation and annual-guard concurrency tests in `tests/Accounting/Integration/AnnualExpenseProjectionTest.php` and `tests/Accounting/Integration/AnnualEconomicMutationConcurrencyTest.php`; each test MUST abort unless `DB::connection()->getDriverName()==='mysql'`; prerequisite: T004; run `docker compose exec -T -u sail -e APP_ENV=testing -e DB_CONNECTION=mysql -e DB_HOST=mysql -e DB_DATABASE=master_plan_it_test laravel.test php artisan test tests/Accounting/Integration/AnnualExpenseProjectionTest.php tests/Accounting/Integration/AnnualEconomicMutationConcurrencyTest.php`; expect current rows load once, out-of-year Date remains, per-Expense/annual totals reconcile, same-year approval/close/year-deactivation versus create/update/delete and basis update versus any annual writer cannot write-skew, while distinct years advance independently unless an operation also needs the Tenant lock. [FR-011–FR-014, FR-018, FR-019, FR-027–FR-030, SC-006, SC-007, SC-009]
- [x] T007 [P] Write common error/no-leakage/correlation regressions in `tests/Feature/Api/Contracts/AnnualExpenseErrorContractTest.php`; prerequisite: none; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Api/Contracts/AnnualExpenseErrorContractTest.php`; expect exact inherited 401/403/404/405/409/419/422/429/500 envelopes, UUIDv4 propagation/replacement, identical absent/foreign path 404 and identical absent/foreign body-relation generic 422 with no disclosed resource data. [FR-033–FR-035, FR-038, FR-039, SC-003]

### Shared-owner implementation

- [x] T008 Primary integration owner: consolidate schema in `database/migrations/2026_08_03_000007_create_tenants_table.php`, `2026_08_03_010004_create_revision_batches_table.php`, `2026_08_03_010005_create_revision_batch_items_table.php`, `2026_08_03_020001_create_expenses_table.php`, `2026_08_03_020002_create_expense_rows_table.php`, `2026_08_09_100001_add_annual_budget_lifecycle.php`; implement `app/Console/Commands/TestResetGreenfield.php` and register it in `bootstrap/app.php`; remove only Expense lifecycle plus Project/Contract XOR while preserving declared `MIGRATION-ONLY` columns. Prerequisite: red T004; run `docker compose exec -T -u sail laravel.test php artisan app:test-reset-greenfield --seed && docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Expenses/AnnualExpenseSchemaTest.php tests/Feature/Console/TestResetGreenfieldCommandTest.php`; expect the exact disposable MySQL database only is rebuilt with internal `budget_basis`, lock timestamp, row notes, non-unique revision correlation and composite RevisionBatchItem Tenant FK. [FR-001, FR-003, FR-023, FR-035, FR-040, FR-041]
- [x] T009 Primary integration owner: align schema-safe casts/relations in `app/Models/Tenant.php`, `app/Models/Expense.php`, `app/Models/ExpenseRow.php` while retaining internal `app/Domain/Tenancy/Enums/BudgetBasis.php`; defer Expense lifecycle metadata/enum removal to US3 and expose `economic_basis` only at API adapters; prerequisite: T008; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Expenses/AnnualExpenseSchemaTest.php`; expect current internal consumers remain compile-safe, Project and Contract may coexist, row notes version, and lifecycle cleanup remains explicitly ordered. [FR-001, FR-003, FR-023, FR-024]
- [x] T010 Primary integration owner: implement exact services in `app/Domain/Economics/Services/MoneyCalculator.php`, `app/Domain/Economics/Services/VatCalculator.php`, immutable DTOs in `app/Domain/Economics/Data/EconomicMeasure.php`, `ProjectedEconomicLine.php`, `ExpenseEconomicProjection.php`, `AnnualEconomicProjection.php` and make `app/Domain/Economics/Services/EconomicEngine.php` the sole calculator; prerequisite: red T005 and schema T008–T009; run `docker compose exec -T -u sail laravel.test php artisan test tests/Accounting/Unit/MoneyCalculatorTest.php tests/Accounting/Unit/VatCalculatorTest.php tests/Accounting/Unit/AnnualEconomicProjectionTest.php`; expect canonical decimal validation, direct/calculated XOR, scale-12 half-away-from-zero results and projection decisions green with no formula outside the manifest-owned core. [FR-020, FR-021, FR-027–FR-029, FR-039]
- [x] T011 Primary integration owner: adapt the single dataset loader in `app/Domain/Reporting/Queries/EconomicDatasetQuery.php` and inputs in `app/Domain/Economics/Data/EconomicDataset.php`, `app/Domain/Economics/Data/EconomicLine.php` and `app/Domain/Economics/Data/EconomicScope.php` to build the final projection without N+1 queries; prerequisite: T010 and red T006; run `docker compose exec -T -u sail laravel.test php artisan test tests/Accounting/Integration/AnnualExpenseProjectionTest.php`; expect T006 green and only current same-Tenant rows included. [FR-011, FR-027–FR-030]
- [x] T012 Primary integration owner: map the stable/inherited error catalogue and UUIDv4 diagnostic correlation behavior in existing `app/Support/Api/ApiErrorResponse.php`, `app/Support/Diagnostics/CorrelationId.php` and `bootstrap/app.php`; prerequisite: red T007; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Api/Contracts/AnnualExpenseErrorContractTest.php`; expect exact envelopes, invalid UUID replacement, sanitized 500s and identifier-class no-disclosure without a parallel support namespace. [FR-033–FR-035, FR-038, FR-039]
- [x] T013 [P] Update test/demo states in `database/factories/TenantFactory.php`, `database/factories/ExpenseFactory.php`, `database/factories/ExpenseRowFactory.php`, `database/seeders/DemoDataSeeder.php` and wire opt-in demo invocation in `database/seeders/DatabaseSeeder.php`; prerequisite: T008–T010; run `docker compose exec -T -u sail laravel.test php artisan app:test-reset-greenfield --seed`; expect distinct Net/Gross Tenants, valid selected planning, row notes, Project+Contract coexistence, signed Actuals and an out-of-year Date. [FR-042, SC-011]

**Checkpoint**: Greenfield schema, projection contract, error envelope and coverage tooling are stable; no User Story implementation has to redefine them.

---

## Phase 3: User Story 1 — Impostare il Contesto Economico (Priority: P1) 🎯 MVP

**Goal**: configure official Tenant basis and maintain fail-closed Tenant/Year context in a minimal top shell.

**Independent Test**: switch basis before/after lock, then traverse Expense/Budget/Report across two Tenants/years with dirty and late-response guards.

### Tests first

- [x] T014 [P] [US1] Write settings/approval-bridge lock, allow/deny, inactive-Tenant admin exception, stale, no-op and exact audit-cardinality/redaction tests in `tests/Feature/PlatformOperations/TenantEconomicBasisTest.php`; prerequisite: Phase 2; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/PlatformOperations/TenantEconomicBasisTest.php`; expect failure until persisted lock timestamp works atomically, first existing approval locks the basis, no-op writes nothing, Tenant users fail closed and the protected Platform Administrator with exact ability preserves baseline access. [FR-001–FR-004, FR-034, FR-036, FR-038, SC-001]
- [x] T015 [P] [US1] Write API settings contract tests in `tests/Feature/Api/Tenancy/TenantEconomicBasisApiTest.php`; prerequisite: Phase 2; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Api/Tenancy/TenantEconomicBasisApiTest.php`; expect failure until Resource/request use `economic_basis`/`economic_basis_locked_at` and exact errors. [FR-002, FR-003, FR-033–FR-035]
- [x] T016 [P] [US1] Write top-shell/context/navigation tests in `frontend/src/layout/AppLayout.test.tsx`, `frontend/src/context/PlanningYearContext.test.tsx`, `frontend/src/components/header/WorkspaceContextBar.test.tsx` and `frontend/src/navigation/applicationNavigation.test.ts`; prerequisite: Phase 2; run `docker compose exec -T frontend npm run test:unit -- AppLayout PlanningYearContext WorkspaceContextBar applicationNavigation`; expect no sidebar, Tenant/year reset, dirty guard, loading/empty/error, late-response protection and preservation of every existing authorized destination except later-slice Guide work. [FR-005–FR-010, SC-002]
- [x] T017 [P] [US1] Write settings adapter/form tests in `frontend/src/api/tenantSettings.test.ts` and `frontend/src/components/settings/TenantGeneralSettingsForm.test.tsx`; prerequisite: Phase 2; run `docker compose exec -T frontend npm run test:unit -- tenantSettings TenantGeneralSettingsForm`; expect failure until target names, decimal strings, lock state, 409 and input preservation are handled. [FR-001–FR-004, FR-031, FR-033]

### Implementation

- [x] T018 [US1] Primary integration owner: update `app/Domain/Tenancy/Actions/UpdateTenantSettings.php`, `app/Domain/Tenancy/Data/TenantContext.php`, `app/Http/Resources/Api/V1/{TenantSettingsResource,TenantResource}.php`, `app/Http/Controllers/Api/V1/TenantSettingsController.php` and temporary `app/Domain/Budget/Actions/ApplyBudgetApproval.php`; prerequisite: red T014–T015 and T008–T012; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/PlatformOperations/TenantEconomicBasisTest.php tests/Feature/Api/Tenancy/TenantEconomicBasisApiTest.php tests/Feature/Budget/TenantBudgetBasisFreezeTest.php tests/Accounting/Integration/AnnualEconomicMutationConcurrencyTest.php`; expect internal `budget_basis` mapped to API `economic_basis`, no-op zero side effects, basis update locking Tenant then every existing PlanningYear by ID, and first successful approval locking Tenant then its PlanningYear and setting `economic_basis_locked_at` atomically without otherwise redesigning approval before Slice 025. [FR-001–FR-004, FR-034, FR-036, FR-038]
- [x] T019 [P] [US1] Update settings client/form in `frontend/src/api/tenantSettings.ts` and `frontend/src/components/settings/TenantGeneralSettingsForm.tsx`; prerequisite: red T017 and T018 contract; run `docker compose exec -T frontend npm run test:unit -- tenantSettings TenantGeneralSettingsForm`; expect exact target fields, disabled locked control and preserved input/errors. [FR-001–FR-004, FR-031, FR-033]
- [x] T020 [US1] Implement context transition/dirty registration in `frontend/src/context/PlanningYearContext.tsx`, `frontend/src/context/ApplicationContext.tsx`, `frontend/src/hooks/useWorkspaceContextGuard.ts` and wire `frontend/src/components/expenses/ExpenseEditor.tsx` to register/update/unregister its dirty state; prerequisite: red T016 and T026 contract; run `docker compose exec -T frontend npm run test:unit -- PlanningYearContext WorkspaceContextBar ExpenseEditor`; expect Tenant, year or destination transitions to confirm unsaved edits, clear annual state and ignore late responses. [FR-006–FR-010]
- [x] T021 [US1] Replace the permanent sidebar in `frontend/src/layout/{AppLayout,AppHeader}.tsx`, add `frontend/src/components/header/WorkspaceContextBar.tsx`, and preserve `frontend/src/navigation/applicationNavigation.ts` route/ability filtering for Dashboard, Budget, Expenses, Contracts, Projects, Reports, Settings and platform Tenant management; prerequisite: T020 and red T016; run `docker compose exec -T frontend npm run test:unit -- AppLayout WorkspaceContextBar applicationNavigation`; expect responsive keyboard support, no sidebar reservation and no destination loss; Guide/generalized navigation remains Slice 033. [FR-005–FR-010, SC-002]
- [x] T022 [US1] Primary integration owner: verify existing abilities/middleware/lookups in `database/seeders/PermissionCatalogueSeeder.php`, `routes/api/v1/{tenant-settings,expenses,reporting}.php`, `tests/Feature/Authorization/PermissionCatalogueTest.php` and API authorization tests; prerequisite: T014–T021; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Authorization/PermissionCatalogueTest.php tests/Feature/Api/Tenancy/TenantEconomicBasisApiTest.php tests/Feature/Api/Expenses/AnnualExpenseApiTest.php`; expect `tenant-settings.view|update`, `expense.create|update`, `planning-year.view`, `cost-center.view`, `vendor.view`, protected inactive-Tenant admin exception and fail-closed partial-role behavior with no new role semantics. [FR-002, FR-008, FR-034]

**Checkpoint**: US1 works independently with existing/seeded Expense data.

---

## Phase 4: User Story 2 — Registrare la Spesa Autorevole (Priority: P1)

**Goal**: create/update one authoritative Expense aggregate with exact selection, signed Actuals and real dates independent of economic year.

**Independent Test**: canonical 2025 Expense with planning plus `40 + 65 - 5`, one 2026 date, optimistic collision, revision/audit and foreign-Tenant attempts.

### Tests first

- [x] T023 [P] [US2] Write aggregate validation/decimal/date/relationship tests in `tests/Feature/Expenses/AuthoritativeExpenseTest.php`; prerequisite: Phase 2; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Expenses/AuthoritativeExpenseTest.php`; expect exact grammar/direct-vs-calculated/rounding decisions, missing/double selection and negative planning failures; signed Actual, actual-only, row notes, Project+Contract coexistence and out-of-year Date success. [FR-012–FR-017, FR-020, FR-021, SC-004–SC-006]
- [x] T024 [P] [US2] Extend rollback/stale/revision/audit tests in `tests/Feature/Expenses/ExpenseActionRollbackTest.php`, add `tests/Feature/Revisions/AnnualExpenseRevisionTest.php`, and exercise the shared annual guard via T006; prerequisite: Phase 2; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Expenses/ExpenseActionRollbackTest.php tests/Feature/Revisions/AnnualExpenseRevisionTest.php tests/Accounting/Integration/AnnualEconomicMutationConcurrencyTest.php`; expect stale-before/race-after safety, complete rollback and, for create/update/delete/restore plus each aggregate in bulk, one full snapshot, exactly one `revision.batch.begin` plus one redacted operation-specific business event with no sensitive payload. [FR-018, FR-019, FR-036–FR-038, SC-009]
- [x] T025 [P] [US2] Write preview/create/read/update/foreign/inactive HTTP tests in `tests/Feature/Api/Expenses/AnnualExpenseApiTest.php`; prerequisite: Phase 2; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Api/Expenses/AnnualExpenseApiTest.php`; expect canonical `planning_year_id`, inactive PlanningYear create/preview rejection for every role, separate protected-admin access on an inactive Tenant using an active PlanningYear, update-preview `STALE_VERSION`, non-persistence, identical absent/foreign body relation 422, path 404, lookup-ability failures and complete target payload. [FR-014–FR-022, FR-032–FR-035]
- [x] T026 [P] [US2] Write editor/api adapter tests in `frontend/src/api/expenses.test.ts`, `frontend/src/components/expenses/ExpenseEditor.test.tsx` and `frontend/src/components/expenses/ExpenseEditorRows.test.tsx`; prerequisite: Phase 2; run `docker compose exec -T frontend npm run test:unit -- expenses ExpenseEditor ExpenseEditorRows`; expect exact string grammar, direct/calculated XOR, row notes, one selection, signed Actual, outside-year Date, dirty registration/unregistration, stale/422 input preservation and no local totals. [FR-014–FR-022, FR-031–FR-033]

### Implementation

- [x] T027 [US2] Align `app/Domain/Expenses/Data/{SaveExpenseData,SaveExpenseRowData}.php` and `app/Domain/Expenses/Services/ExpenseAggregateValidator.php` for notes, direct/calculated mode, exact decimals, active-year create, Project+Contract coexistence and generic scoped relationships; prerequisite: red T023; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Expenses/AuthoritativeExpenseTest.php`; expect deterministic selection, date/sign/rounding/overflow/relationship validation without civil-year coupling or existence leakage. [FR-012–FR-017, FR-020, FR-021]
- [x] T028 [US2] Implement `app/Domain/Expenses/Services/PrepareExpenseAggregate.php` and the read-only `app/Domain/Expenses/Queries/PreviewExpenseQuery.php`; prerequisite: T027 and T010; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Expenses/AuthoritativeExpenseTest.php tests/Feature/Api/Expenses/AnnualExpenseApiTest.php`; expect update-preview checks every supplied version and returns `STALE_VERSION`, persists/locks/reserves nothing, and preview/save share calculations while save still repeats all checks under locks. [FR-018, FR-020–FR-022, FR-027–FR-029]
- [x] T029 [US2] Primary integration owner: implement reusable `app/Domain/Budget/Services/AnnualEconomicMutationGuard.php`; update `CreateExpense`, `UpdateExpense`, `DeleteExpense`, `RestoreExpenseRevision`, `BulkExpenseAction`, every transactional Contract Action under `app/Domain/Contracts/Actions`, every transactional Project Action under `app/Domain/Projects/Actions`, `app/Domain/MasterData/Actions/{DeactivatePlanningYear,ReactivatePlanningYear}.php`, `ApplyBudgetApproval` and `CloseAnnualBudget` to lock scoped PlanningYear IDs ascending before aggregate rows; an operation touching Tenant-level state locks Tenant first. Prerequisite: T006 red, T027–T028 and T024 red; run `docker compose exec -T -u sail laravel.test php artisan test tests/Accounting/Integration/AnnualEconomicMutationConcurrencyTest.php tests/Feature/Expenses/AuthoritativeExpenseTest.php tests/Feature/Expenses/ExpenseActionRollbackTest.php tests/Feature/Revisions/AnnualExpenseRevisionTest.php`; expect no write skew, independent unrelated years, root/row optimistic safety, exact audit cardinality/redaction, non-unique diagnostic correlation and full rollback. [FR-017–FR-019, FR-036–FR-038]
- [x] T030 [US2] Update Expense DTO/query/API presentation in `app/Domain/Expenses/Data/ExpenseDetail.php`, `app/Domain/Expenses/Queries/ExpenseDetailQuery.php`, `app/Http/Resources/Api/V1/ExpenseRowResource.php`, `app/Http/Resources/Api/V1/ExpenseDetailResource.php` and `app/Http/Controllers/Api/V1/ExpenseController.php`, then have the Primary integration owner add the literal preview route before `/{expense}` in `routes/api/v1/expenses.php`; prerequisite: T025 red and T028–T029; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Api/Expenses/AnnualExpenseApiTest.php`; expect target preview/CRUD payload with separated year/date and projection totals and no competing route writer. [FR-014, FR-018–FR-022, FR-027–FR-033]
- [x] T031 [US2] Update Expense TypeScript contracts/adapters with canonical `planning_year_id`, response-level `basis`, row notes, full row/date fields and retained-endpoint compatibility in `frontend/src/api/expenses.ts` and `frontend/src/components/expenses/expenseEditorTypes.ts`; prerequisite: T030 and red T026; run `docker compose exec -T frontend npm run test:unit -- expenses`; expect no numeric coercion, lifecycle field or unowned Trash/retention behavior. [FR-020, FR-022, FR-031–FR-033]
- [x] T032 [US2] Update `frontend/src/components/expenses/{ExpenseEditor,ExpenseEditorRows,ExpenseTotals}.tsx` and dirty-context registration; prerequisite: T031 and red T026; run `docker compose exec -T frontend npm run test:unit -- ExpenseEditor ExpenseEditorRows PlanningYearContext`; expect one planning selector, direct/calculated row mode, notes, signed Actual, real Date copy, server preview totals, preserved input and correct register/unregister lifecycle. [FR-014–FR-022, FR-031]
- [x] T033 [US2] Update create/edit/detail pages in `frontend/src/pages/Expenses/ExpenseNew.tsx`, `frontend/src/pages/Expenses/ExpenseEdit.tsx` and `frontend/src/pages/Expenses/ExpenseDetail.tsx`; prerequisite: T032; run `docker compose exec -T frontend npm run test:unit -- ExpenseDetail ExpenseEditor`; expect usable document flow with separate Anno Economico/Data and correct loading/empty/error states. [FR-022, FR-031–FR-033]

**Checkpoint**: US2 works independently against direct Expense endpoints and seeded dimensions.

---

## Phase 5: User Story 3 — Lavorare Senza Lifecycle della Spesa (Priority: P2)

**Goal**: remove every Backend/Frontend surface of Expense open/closed, close outcome and lifecycle-based move.

**Independent Test**: schema/API/route/compiled UI contain no lifecycle field, filter, badge or action and legacy endpoints have no effect.

### Tests first

- [x] T034 [P] [US3] Add route/resource/register/architecture lifecycle-absence tests in `tests/Feature/Api/Expenses/ExpenseLifecycleRemovalTest.php` and `tests/Architecture/ExpenseLifecycleAbsenceTest.php`; prerequisite: Phase 2 and T030; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Api/Expenses/ExpenseLifecycleRemovalTest.php tests/Architecture/ExpenseLifecycleAbsenceTest.php`; expect failure while close/move/bulk lifecycle variants, removed symbols, rollback-map references, schema/resource fields or query branches remain. [FR-023–FR-026, FR-032, SC-008]
- [x] T035 [P] [US3] Add frontend lifecycle-absence regressions in `frontend/src/pages/Expenses/{ExpenseRegister,ExpenseDetail}.test.tsx`, `frontend/src/api/expenses.test.ts`, `frontend/src/components/dashboard/DashboardView.test.tsx` and retire `frontend/src/components/expenses/ExpenseMoveModal.test.tsx`; prerequisite: T031; run `docker compose exec -T frontend npm run test:unit -- ExpenseRegister ExpenseDetail expenses DashboardView ExpenseMoveModal`; expect failure while state filters/types/controls/copy or `open|closed` dashboard metrics remain. [FR-024–FR-026, FR-031, FR-032, SC-008]

### Implementation

- [x] T036 [US3] Primary integration owner: apply the exact source actions in `specs/023-annual-expense-workspace/lifecycle-inventory.md` (remove lifecycle enum/Action/modal files; rewrite listed model/query/controller/resource/factory/migration/API/UI/test files; retain Budget lifecycle-only branches), including `app/Models/Expense.php`, `app/Domain/Expenses`, `app/Domain/{Budget,Reporting}/Queries`, `app/Http`, `database`, `frontend/src` and `tests`; prerequisite: red T034; run the inventory's Backend architecture/feature command; expect every listed source/test action resolved, no removed import/cast/implicit reopen and non-lifecycle bulk delete preserved as migration-only. [FR-023–FR-026]
- [x] T037 [US3] Remove controller/resource lifecycle fields and methods in `app/Http/Controllers/Api/V1/ExpenseController.php`, `app/Http/Resources/Api/V1/ExpenseDetailResource.php` and `app/Http/Resources/Api/V1/ExpenseRegisterResource.php`; prerequisite: T036; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Api/Expenses/ExpenseLifecycleRemovalTest.php`; expect target Resources and bulk validation reject state/close/move payloads. [FR-024–FR-026, FR-032]
- [x] T038 [US3] Primary integration owner: remove close/move routes in `routes/api/v1/expenses.php`, update `tests/Feature/Api/ApiOnlyRouteTest.php`, `tests/Feature/Authorization/PermissionCatalogueTest.php`, `tests/Architecture/Fixtures/domain-write-rollback-map.php` and every Backend test marked rewrite/remove in `lifecycle-inventory.md`; prerequisite: red T034 and T036–T037; run the inventory Backend command then `docker compose exec -T -u sail laravel.test composer test:application`; expect close/move absent and retained delete/preferences/attachments/history/revision/restore compile. [FR-025, FR-034]
- [x] T039 [US3] Execute every Frontend source action in `lifecycle-inventory.md`, including exact API/component/page paths for Expense, Budget, Reports and Dashboard; prerequisite: red T035 and T038; run the inventory Frontend focused command; expect no Expense state/filter/badge/close/move request or metric while retained delete/bulk-delete remains migration-only. [FR-024–FR-026, FR-031, FR-032]
- [x] T040 [US3] Execute every Frontend test action in `lifecycle-inventory.md` and remove stale imports; prerequisite: T039; run `docker compose exec -T frontend npm run verify`; expect inventory and lifecycle absence green, preferences normalized without `state`, no stale component import and all non-lifecycle Budget semantics retained. [FR-024–FR-026, SC-008]

**Checkpoint**: US3 works independently using any target Expense fixture; no deprecated capability is callable.

---

## Phase 6: User Story 4 — Riconciliare Ogni Superficie (Priority: P2)

**Goal**: make Document, Register, Budget, Report Drill-Down and Dashboard consume one annual projection with exact Net/Gross parity.

**Independent Test**: canonical dataset sums to the same cent on all five surfaces in both bases, with no N+1 or React formula.

### Tests first

- [x] T041 [P] [US4] Add five-consumer MySQL reconciliation tests in `tests/Accounting/Integration/AnnualExpenseSurfaceReconciliationTest.php`; prerequisite: T010–T011 and US2; run `docker compose exec -T -u sail laravel.test php artisan test tests/Accounting/Integration/AnnualExpenseSurfaceReconciliationTest.php`; expect Document/Register/Budget/Report/Dashboard all select the same projection results and register exposes economic year plus expanded Actual real dates. [FR-027–FR-031, SC-007]
- [x] T042 [P] [US4] Add constant-query benchmark in `tests/Accounting/Integration/AnnualExpenseProjectionQueryCountTest.php`; prerequisite: T011; run `docker compose exec -T -u sail laravel.test php artisan test tests/Accounting/Integration/AnnualExpenseProjectionQueryCountTest.php`; expect failure if query count grows between 1 and 1,000 Expenses. [FR-027, FR-030]
- [x] T043 [P] [US4] Add Budget/Report/Dashboard HTTP parity tests in `tests/Feature/Api/Reporting/AnnualExpenseProjectionApiTest.php`, `tests/Feature/Api/Reporting/ReportingApiHttpTest.php`, `tests/Feature/Api/Budget/HistoricalBudgetApiTest.php` and `tests/Feature/Budget/HistoricalBudgetQueryTest.php`; prerequisite: US2–US3; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Api/Reporting/AnnualExpenseProjectionApiTest.php tests/Feature/Api/Reporting/ReportingApiHttpTest.php tests/Feature/Api/Budget/HistoricalBudgetApiTest.php tests/Feature/Budget/HistoricalBudgetQueryTest.php`; expect canonical `planning_year_id`, response `currency`/`basis`, complete totals/line IDs, current/historical `as_of` compile-safe migration-only blocks, abilities, no Expense state metric and exact reconciliation. [FR-008, FR-029–FR-035]
- [x] T044 [P] [US4] Add surface adapter/component tests in new `frontend/src/api/{budget,reports,dashboard}.test.ts`, `frontend/src/components/expenses/ExpenseRegisterTable.test.tsx`, `frontend/src/components/budget/BudgetView.test.tsx`, `frontend/src/components/reports/ReportsView.test.tsx` and `frontend/src/components/dashboard/DashboardView.test.tsx`; prerequisite: US2–US3; run `docker compose exec -T frontend npm run test:unit -- budget reports dashboard ExpenseRegisterTable BudgetView ReportsView DashboardView`; expect one exact `ProjectionTotals` shape, complete per-expense/group/line values, year/date separation and no client calculation or fallback. [FR-029–FR-033, SC-007]

### Implementation

- [x] T045 [US4] Adapt `app/Domain/Expenses/Queries/{ExpenseDetailQuery,ExpenseRegisterQuery}.php`, `app/Domain/Expenses/Data/{ExpenseDetail,ExpenseRegisterRow}.php` and `app/Http/Resources/Api/V1/{ExpenseDetailResource,ExpenseRegisterResource,ExpenseRowResource}.php` so every item has complete totals, `economic_year_label`, and expanded rows with real `spend_date`; prerequisite: red T041 and T010–T011; run `docker compose exec -T -u sail laravel.test php artisan test tests/Accounting/Integration/AnnualExpenseSurfaceReconciliationTest.php`; expect per-Expense/filtered totals are slices of `AnnualEconomicProjection` without ambiguous date summaries. [FR-027–FR-030]
- [x] T046 [US4] Primary integration owner: adapt `app/Domain/Budget/Queries/{AnnualBudgetQuery,HistoricalAnnualBudgetQuery}.php`, `app/Http/Controllers/Api/V1/CurrentBudgetController.php`, `app/Http/Resources/Api/V1/AnnualBudgetResource.php`, `frontend/src/api/budget.ts` and `frontend/src/pages/Budget/Home.tsx`; prerequisite: red T041/T043 and T045; run the T041/T043 commands and `docker compose exec -T frontend npm run test:unit -- budget BudgetView`; expect projection totals have no Expense lifecycle metrics while approval/history/`as_of` remains compile-safe `MIGRATION-ONLY` until 025. [FR-027–FR-030, FR-032]
- [x] T047 [US4] Primary integration owner: adapt `app/Domain/Reporting/Queries/{AnnualEconomicReportQuery,TenantDashboardQuery}.php` and `app/Http/Controllers/Api/V1/{EconomicReportController,DashboardController}.php`, removing the superseded `EconomicReportQuery`; prerequisite: red T041/T043 and T045; run the exact T041 and T043 commands; expect groups/drill-down/Dashboard identities reconcile and state filters/metrics plus independent summation are absent; comparative Dashboard remains Slice 032. [FR-027–FR-030, FR-032]
- [x] T048 [US4] Align `app/Http/Resources/Api/V1/{ExpenseMoneyResource,ExpenseDetailResource,ExpenseRegisterResource,AnnualBudgetResource}.php` and controller response builders in `EconomicReportController.php`/`DashboardController.php`; prerequisite: T045–T047; run the Expense/Reporting API command; expect outer `currency`/`basis` and `totals.current_planning|actual` with complete component/official strings everywhere. [FR-020, FR-022, FR-029–FR-033]
- [x] T049 [US4] Implement shared TypeScript projection types/adapters in `frontend/src/api/{expenses,budget,reports,dashboard}.ts`; prerequisite: red T044 and T048; run `docker compose exec -T frontend npm run test:unit -- expenses budget reports dashboard`; expect exact shared shape, no numeric coercion and no legacy state fields. [FR-020, FR-024, FR-029–FR-033]
- [x] T050 [US4] Update `frontend/src/components/expenses/{ExpenseTotals,ExpenseRegisterTable}.tsx`, `frontend/src/components/budget/BudgetView.tsx`, `frontend/src/components/reports/{ReportsView,ReportEconomicChart}.tsx`, `frontend/src/components/dashboard/DashboardView.tsx` and pages `frontend/src/pages/{Expenses/ExpenseRegister,Budget/Home,Reports/Home,Dashboard/Home}.tsx`; prerequisite: T049 and red T044; run the T044 command; expect exact totals, register year/date expansion, drill-down and loading/empty/error/no-fallback behavior. [FR-022, FR-030–FR-033, SC-007]
- [x] T051 [US4] Add diagnostic invariant handling test/implementation in `tests/Accounting/Unit/AnnualEconomicProjectionTest.php` and `app/Domain/Economics/Services/EconomicEngine.php` via the primary integration owner; prerequisite: T010 and T050; run `docker compose exec -T -u sail laravel.test php artisan test tests/Accounting/Unit/AnnualEconomicProjectionTest.php tests/Feature/Api/Contracts/AnnualExpenseErrorContractTest.php`; expect reconciliation failures become `ECONOMIC_RECONCILIATION_FAILED` with no partial/fallback value. [FR-039]

**Checkpoint**: all User Stories work; the five surfaces agree at the cent.

---

## Phase 7: Cross-cutting Verification and Delivery

**Purpose**: prove security, data construction, coverage, parity and complete Done criteria.

- [x] T052 [P] Extend authorization/no-leakage/audit matrices in `tests/Feature/Expenses/ExpenseAuthorizationTest.php`, `tests/Feature/Api/Tenancy/TenantEconomicBasisApiTest.php`, `tests/Feature/Api/Expenses/AnnualExpenseApiTest.php`, `tests/Feature/Api/Reporting/AnnualExpenseProjectionApiTest.php` and `tests/Feature/Revisions/AnnualExpenseRevisionTest.php`; prerequisite: US1–US4; run `docker compose exec -T -u sail laravel.test php artisan test` followed by those five paths; expect same-Tenant allow, ability deny, inactive user/tenant-user deny, protected-admin allow, byte-equivalent path 404/body 422, partial lookup-role failure, sanitized audit/log and zero side effects. [FR-002, FR-008, FR-034, FR-035, FR-038, SC-003]
- [x] T053 [P] Add Factory/Seeder validity regression in `tests/Feature/Expenses/AnnualExpenseSeederTest.php`; prerequisite: T013 and US1–US4; run `docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Expenses/AnnualExpenseSeederTest.php`; expect canonical Net/Gross fixtures, signed Actual/out-of-year Date and no invalid lifecycle/Plafond target records. [FR-042, SC-011]
- [x] T054 Run the economic Gate defined by Composer/XML plus the versioned `tests/Support/economic-coverage-classes.php` manifest and assertion; prerequisite: all economic tasks; run `docker compose exec -T -u sail -e XDEBUG_MODE=coverage laravel.test composer test:economic-coverage`; expect every mandatory class present in Cobertura, 100% line, 100% branch where applicable and exit 0; any missing class/metric or below-threshold value fails. [FR-043, SC-010]
- [x] T055 Run Backend static/fresh/Accounting/Application gates without editing code in `composer.json`; prerequisite: T052–T054; run `docker compose exec -T -u sail laravel.test composer test:static && docker compose exec -T -u sail laravel.test composer test:prepare && docker compose exec -T -u sail laravel.test composer test:accounting && docker compose exec -T -u sail laravel.test composer test:application`; expect all exit 0 and record any pre-existing/environment failure separately. [FR-040, FR-044, SC-012]
- [x] T056 [P] Run Frontend unit/dark-token/lint/build gate defined by `frontend/package.json`; prerequisite: US1–US4; run `docker compose exec -T frontend npm run verify`; expect all adapter/component/loading/empty/error tests, ESLint and TypeScript/Vite build exit 0. [FR-031, FR-032, FR-044, SC-012]
- [x] T057 Execute the complete manual/browser acceptance matrix in `specs/023-annual-expense-workspace/quickstart.md`; prerequisite: T055–T056; use only `app:test-reset-greenfield` for any destructive test rebuild; expect observable US1–US4 outcomes in desktop/responsive/light/dark with actual evidence, not simulated results. [SC-001–SC-009, SC-011]
- [x] T058 Perform read-only domain, security/tenancy and surface-parity review against `specs/023-annual-expense-workspace/spec.md`, `contracts/api-contract.md` and the implementation diff; prerequisite: T054–T057; run no mutation command; expect zero CRITICAL/HIGH, every user-facing Backend capability mapped to API client/UI/error/test and any MEDIUM explicitly resolved or justified. [FR-001–FR-044, SC-012]
- [x] T059 Primary integration owner: after verified implementation only, update feature-local contract if actual route/resource details differ and propagate durable implemented rules/status in `docs/DOMAIN.md`, `docs/ARCHITECTURE.md`, `docs/STATUS.md` and `specs/README.md`; prerequisite: T058 green; run `git diff --check` plus relevant docs checks; expect permanent docs describe only verified behavior and no global task/readiness/analyze report is created. [SC-012]

---

## Dependencies and Execution Order

### Slice Dependency

- **Slice 023 dependency**: none; implementata e verificata il 2026-08-12.
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

- US1 and US2 tests can start in parallel after Phase 2. Shared-owner code and the ExpenseEditor dirty-guard integration are serialized; T020 waits for the T026 editor contract.
- US3 follows US2 because both edit Expense controller/types/components and must not have concurrent writers.
- US4 follows US2–US3 and consumes the final target payload.
- Primary integration owner tasks are serialized across T008–T012, T018, T022, T029, T036, T038, T046–T047, T051 and T059.

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
- Protected Greenfield MySQL reset, seed, static, Accounting, Application, Frontend, lint/build and manifest-complete Xdebug line+branch gates pass.
- Same-Tenant allow, permission deny, foreign Tenant no-leakage, inactive account/Tenant (including explicit Platform Admin exception), stale preview/save, annual concurrency and rollback are green.
- No lifecycle Expense field/action/copy remains.
- Document/Register/Budget/Report/Dashboard reconcile at the cent in Net and Gross.
- No CRITICAL/HIGH review or parity finding remains.
- No push, PR or remote merge is implied by this task list.
