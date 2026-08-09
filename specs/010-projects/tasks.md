# Tasks — Feature 010 Projects

## Phase 1 — Spec/plan readiness

- [x] T001 Review `specs/010-projects/spec.md` against current code, migration, tests and `.specify/memory/constitution.md`
- [x] T002 [P] Complete `specs/010-projects/plan.md`, `specs/010-projects/research.md`, `specs/010-projects/data-model.md`, `specs/010-projects/contracts/`, `specs/010-projects/quickstart.md`, and `specs/010-projects/checklists/requirements.md`
- [x] T003 Run the read-only Spec Kit consistency gate across `specs/010-projects/spec.md`, `specs/010-projects/plan.md`, and `specs/010-projects/tasks.md` and resolve all CRITICAL/HIGH findings at source

## Phase 2 — US-010-01 Gestire Project (P1) — vertical slice

**Goal**: an authorized user completes Project create/list/detail/update from TailAdmin UI through Laravel
and MySQL with tenant isolation and optimistic concurrency.

**Independent test**: create a Project from `/progetti/nuovo`, see it in `/progetti`, open detail, update it,
and observe missing-ability/foreign/stale requests fail closed.

- [x] T004 [P] [US-010-01] Add Project schema and the existing Expense column composite FK in `database/migrations/2026_08_09_000001_create_projects_table.php`
- [x] T005 [P] [US-010-01] Add Project lifecycle/API tests first in `tests/Feature/Projects/ProjectLifecycleTest.php` and `tests/Feature/Api/Projects/ProjectApiHttpTest.php`
- [x] T006 [P] [US-010-01] Add `ProjectStage`, `Project`, `ProjectFactory`, and `SaveProjectData` in `app/Domain/Projects/Enums/ProjectStage.php`, `app/Models/Project.php`, `database/factories/ProjectFactory.php`, and `app/Domain/Projects/Data/SaveProjectData.php`
- [x] T007 [US-010-01] Implement tenant/ability/current-reference/concurrency/revision/audit mutation foundations in `app/Policies/ProjectPolicy.php` and `app/Domain/Projects/Actions/Concerns/ManagesProjects.php`
- [x] T008 [US-010-01] Implement create/update Actions and list/detail Queries in `app/Domain/Projects/Actions/CreateProject.php`, `app/Domain/Projects/Actions/UpdateProject.php`, `app/Domain/Projects/Queries/ProjectListQuery.php`, and `app/Domain/Projects/Queries/ProjectDetailQuery.php`
- [x] T009 [US-010-01] Implement Project Resource/controller/routes in `app/Http/Resources/Api/V1/ProjectResource.php`, `app/Http/Controllers/Api/V1/ProjectController.php`, and `routes/api/v1/projects.php`
- [x] T010 [P] [US-010-01] Add typed Project API client and stage labels in `frontend/src/api/projects.ts` and `frontend/src/presentation/labels.ts`
- [x] T011 [US-010-01] Build TailAdmin Project form/table in `frontend/src/components/projects/ProjectForm.tsx` and `frontend/src/components/projects/ProjectTable.tsx`
- [x] T012 [US-010-01] Build Project list/create/edit/detail pages in `frontend/src/pages/Projects/Projects.tsx`, `frontend/src/pages/Projects/NewProject.tsx`, `frontend/src/pages/Projects/EditProject.tsx`, and `frontend/src/pages/Projects/ProjectDetail.tsx`
- [x] T013 [US-010-01] Wire Italian Project routes/navigation in `frontend/src/App.tsx`, `frontend/src/navigation/routes.ts`, and `frontend/src/navigation/applicationNavigation.ts`
- [x] T014 [US-010-01] Run focused lifecycle/API tests and frontend type build for the completed Project CRUD slice using `tests/Feature/Projects/ProjectLifecycleTest.php`, `tests/Feature/Api/Projects/ProjectApiHttpTest.php`, and `frontend/package.json`

## Phase 3 — US-010-02 Deferred (P1) — vertical slice

**Goal**: Deferred target validation and tenant-timezone promotion are complete from form presentation to
persistence/revision/audit and operational command.

**Independent test**: Deferred target validation fails closed; before target no change occurs; at target the
Project becomes Proposed exactly once and the UI reflects target/state.

- [x] T015 [P] [US-010-02] Add Deferred validation/promotion/idempotency/tenant tests first in `tests/Feature/Projects/DeferredProjectTest.php`
- [x] T016 [US-010-02] Complete Deferred same-Tenant/current-target validation in `app/Domain/Projects/Actions/Concerns/ManagesProjects.php`
- [x] T017 [US-010-02] Implement idempotent promotion and command in `app/Domain/Projects/Actions/PromoteDeferredProjects.php` and `app/Console/Commands/PromoteDeferredProjectsCommand.php`
- [x] T018 [US-010-02] Register minimal daily promotion scheduling in `bootstrap/app.php`
- [x] T019 [US-010-02] Complete Deferred conditional target UX/detail presentation in `frontend/src/components/projects/ProjectForm.tsx` and `frontend/src/pages/Projects/ProjectDetail.tsx`
- [x] T020 [US-010-02] Run focused Deferred tests and command discovery for `tests/Feature/Projects/DeferredProjectTest.php` and `app/Console/Commands/PromoteDeferredProjectsCommand.php`

## Phase 4 — US-010-03 Expense + Budget (P1) — vertical slice

**Goal**: users associate Project to Expense and all three economic consumers use one server-calculated
bucket result, with Actual always Primary and Plafond unchanged.

**Independent test**: Expense create/edit/detail/register shows Project; the exact stage/type matrix appears
in server bucket outputs consumed by Dashboard/Budget/Report without React formulas.

- [x] T021 [P] [US-010-03] Add Expense Project relation/API/exclusivity/source-governance tests first in `tests/Feature/Expenses/ExpenseProjectRelationTest.php` and `tests/Feature/Api/Expenses/ExpenseProjectApiHttpTest.php`
- [x] T022 [P] [US-010-03] Add complete bucket matrix, stage-change, Potential and Plafond regression tests first in `tests/Accounting/Unit/EconomicEngineTest.php` and `tests/Accounting/Integration/ProjectEconomicDatasetTest.php`
- [x] T023 [US-010-03] Enable tenant-safe Project association and Project/Contract exclusivity in `app/Models/Expense.php`, `app/Http/Controllers/Api/V1/ExpenseController.php`, `app/Domain/Expenses/Services/ExpenseAggregateValidator.php`, and `app/Domain/Expenses/Actions/UpdateExpense.php`
- [x] T024 [US-010-03] Expose Project in Expense detail/register DTOs, Queries and Resources in `app/Domain/Expenses/Data/ExpenseDetail.php`, `app/Domain/Expenses/Data/ExpenseRegisterRow.php`, `app/Domain/Expenses/Queries/ExpenseDetailQuery.php`, `app/Domain/Expenses/Queries/ExpenseRegisterQuery.php`, `app/Http/Resources/Api/V1/ExpenseDetailResource.php`, and `app/Http/Resources/Api/V1/ExpenseRegisterResource.php`
- [x] T025 [US-010-03] Extend the shared dataset and sole classifier in `app/Domain/Reporting/Queries/EconomicDatasetQuery.php`, `app/Domain/Economics/Data/EconomicLine.php`, and `app/Domain/Economics/Services/EconomicEngine.php`
- [x] T026 [US-010-03] Expose exact Project buckets and line context in `app/Http/Resources/Api/V1/ReportingSummaryResource.php` and `app/Http/Resources/Api/V1/ReportingLineResource.php`
- [x] T027 [US-010-03] Integrate Project lookup, mutual clearing and payload fields in `frontend/src/api/expenses.ts` and `frontend/src/components/expenses/ExpenseEditor.tsx`
- [x] T028 [US-010-03] Present Project links in Expense detail/register in `frontend/src/pages/Expenses/ExpenseDetail.tsx` and `frontend/src/components/expenses/ExpenseRegisterTable.tsx`
- [x] T029 [US-010-03] Consume server bucket fields in `frontend/src/api/reports.ts`, `frontend/src/components/budget/BudgetView.tsx`, `frontend/src/components/reports/ReportsView.tsx`, and `frontend/src/components/dashboard/DashboardView.tsx`
- [x] T030 [US-010-03] Run focused Expense/API/accounting tests and frontend lint/build for the completed economic slice using `tests/Feature/Expenses/ExpenseProjectRelationTest.php`, `tests/Accounting/Unit/EconomicEngineTest.php`, `tests/Accounting/Integration/ProjectEconomicDatasetTest.php`, and `frontend/package.json`

## Phase 5 — US-010-04 Revisioni (P2) — vertical slice

**Goal**: authorized users view, compare and restore valid revisions of a current Project, producing a new
revision without rewriting history.

**Independent test**: history/compare are tenant-scoped; valid restore writes a new revision; stale/invalid
reference/foreign/deleted root fail atomically.

- [x] T031 [P] [US-010-04] Add Project history/compare/restore/source-immutability/tenant/stale tests first in `tests/Feature/Projects/ProjectRevisionTest.php` and `tests/Feature/Api/Projects/ProjectApiHttpTest.php`
- [x] T032 [US-010-04] Implement safe revision list/compare/source resolution in `app/Domain/Projects/Queries/ProjectRevisionQuery.php` and `app/Http/Resources/Api/V1/ProjectRevisionResource.php`
- [x] T033 [US-010-04] Implement restore revalidation and new revision write in `app/Domain/Projects/Actions/RestoreProjectRevision.php`
- [x] T034 [US-010-04] Add history detail/restore API operations in `app/Http/Controllers/Api/V1/ProjectController.php` and `routes/api/v1/projects.php`
- [x] T035 [US-010-04] Implement Project revision history/compare/restore TailAdmin UI in `frontend/src/api/projects.ts` and `frontend/src/pages/Projects/ProjectDetail.tsx`
- [x] T036 [US-010-04] Run focused revision/API tests and frontend build using `tests/Feature/Projects/ProjectRevisionTest.php`, `tests/Feature/Api/Projects/ProjectApiHttpTest.php`, and `frontend/package.json`

## Phase 6 — US-010-05 Delete Project (P2) — vertical slice

**Goal**: Project delete is atomically blocked by current Expense, reason-aware, auditable, terminal and
fully represented in TailAdmin UI.

**Independent test**: blocked delete returns the stable code with no detach/cascade; after normal Expense
delete, Project delete succeeds and no revision restore can resurrect it.

- [x] T037 [P] [US-010-05] Add linked-Expense/reason/rollback/tombstone/no-restore tests first in `tests/Feature/Projects/DeleteProjectTest.php` and `tests/Feature/Api/Projects/ProjectApiHttpTest.php`
- [x] T038 [US-010-05] Implement atomic terminal delete in `app/Domain/Projects/Actions/DeleteProject.php` and stable error mapping in `app/Support/Api/ApiErrorResponse.php`
- [x] T039 [US-010-05] Complete delete API and terminal current-record resolution in `app/Http/Controllers/Api/V1/ProjectController.php` and `routes/api/v1/projects.php`
- [x] T040 [US-010-05] Add always-visible reason confirmation and blocked-delete guidance in `frontend/src/pages/Projects/ProjectDetail.tsx` and `frontend/src/api/client.ts`
- [x] T041 [US-010-05] Run focused delete/API tests and frontend build using `tests/Feature/Projects/DeleteProjectTest.php`, `tests/Feature/Api/Projects/ProjectApiHttpTest.php`, and `frontend/package.json`

## Phase 7 — Integration, documentation and convergence

- [x] T042 [P] Add Project schema and rollback-contract coverage in `tests/Feature/Projects/ProjectSchemaTest.php`, `tests/Feature/Projects/ProjectActionRollbackTest.php`, and `tests/Architecture/Fixtures/domain-write-rollback-map.php`; verify the pre-existing Project permission catalogue
- [x] T043 [P] Propagate implemented Project and economic rules/status and the verified missing-OpenAPI conflict in `README.md`, `specs/README.md`, `docs/DOMAIN.md`, `docs/STATUS.md`, `docs/ARCHITECTURE.md`, and `docs/OPERATIONS.md`
- [x] T044 Validate feature-local API contracts and quickstart against routes/resources in `specs/010-projects/contracts/` and `specs/010-projects/quickstart.md`
- [x] T045 Run `composer test:static`, `composer test:prepare`, `composer test:accounting`, `composer test:application`, and when appropriate `composer verify` from repository root
- [x] T046 Run `php artisan route:list --path=api/v1/projects` and `php artisan route:list --path=api/v1/expenses` from repository root
- [x] T047 Run `npm ci`, `npm run lint`, and `npm run build` from `frontend/`
- [x] T048 Perform browser verification of required Project/Expense/Budget/Report/Dashboard routes, responsive viewports, dark mode and error/empty/stale/delete/Deferred states using the real frontend
- [x] T049 Run Spec Kit convergence against `specs/010-projects/spec.md`, `specs/010-projects/plan.md`, `specs/010-projects/tasks.md`, code, migration, API, frontend and tests; append and implement any concrete remaining tasks

## Convergence additions

- [x] T050 [US-010-05] Align the normal Expense-delete client with the existing API contract in `frontend/src/components/expenses/ExpenseActionModal.tsx` and `frontend/src/api/expenses.ts`, so users can remove the last linked Expense before deleting its Project
- [x] T051 [US-010-01] Replace the compressed Project table with a responsive TailAdmin card composition below desktop widths in `frontend/src/components/projects/ProjectTable.tsx`
- [x] T052 [US-010-04] Present Project revision activity as responsive TailAdmin cards on mobile and use localized date-time formatting in `frontend/src/pages/Projects/ProjectDetail.tsx`

## Dependencies and execution order

- Phase 1 gates all implementation.
- US-010-01 establishes the aggregate/API/UI and gates every later story.
- US-010-02 depends on the aggregate but is independently verifiable.
- US-010-03 depends on current Project selection and the existing Expense/economic kernel.
- US-010-04 depends on Project version writes from US-010-01/02.
- US-010-05 depends on Expense relation from US-010-03 and revision foundation from US-010-04.
- Final integration follows all stories.

Parallel markers are limited to different files or test-first work that does not depend on unfinished code.
The delivery strategy is incremental P1 vertical slices first, followed by P2 revisions/delete, then one
full verification and convergence pass.
