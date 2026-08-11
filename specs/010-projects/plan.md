# Implementation plan — Feature 010 Projects

**Branch**: `feature/010-projects`
**Spec**: `specs/010-projects/spec.md`
**Baseline**: `laravel-replatform@7132a39271b31479b9ad477b0ed212bbfc6359a9`

## Technical context

- Backend: PHP 8.3.32, Laravel 13.22, Sanctum SPA, Spatie Permission 8.3.
- Persistence: MySQL 8.4, InnoDB, strict mode, forward-only migrations.
- Revisions: `overtrue/laravel-versionable` snapshots plus application `RevisionBatch` primitives.
- Economics: exact decimal strings/BCMath through the existing `EconomicDatasetQuery` and
  `EconomicEngine`.
- Frontend: React 19, TypeScript, React Router and TailAdmin React Free 2.3.0.
- Tests: PHPUnit/Pest suites grouped as Architecture, Accounting and Application; database tests use
  transactions and the guarded persistent test database.
- New dependencies: none.
- Global OpenAPI: documented as authoritative but absent at baseline; feature-local contracts are the
  implementation contract and permanent docs will be corrected to the real state.

## Constitution Check

### Pre-design gate

| Principle | Result | Evidence |
|---|---|---|
| Permanent docs remain minimal | PASS | Only implemented durable rules/status will be propagated after verification |
| Vertical Spec Kit | PASS | Tasks are grouped by Project user story and include persistence→UI flows |
| Current implementation authority | PASS | Contract, Expense, economics, revision, tenancy, migration and frontend patterns were read at HEAD |
| No invented product decisions | PASS | Product semantics come from the handoff/spec; ordinary technical choices are recorded in research |
| Laravel owns domain/economics/auth | PASS | All invariants and bucket calculations remain server-side |
| Tenant isolation fail-closed | PASS | Composite keys, scoped queries, policies and foreign-ID 404 behavior are planned |
| Exact money and one economic engine | PASS | Existing decimal kernel is extended in place |
| Explicit Actions for mutations | PASS | Create/Update/Delete/Restore/Promote Actions; no generic repository/service |
| TailAdmin-only UI | PASS | Domain components compose installed primitives only |
| Tests and no false claims | PASS | Test-first story tasks and final command matrix are explicit |

No justified constitution exception exists.

## Architecture and vertical slice boundaries

```text
projects table / expenses.project_id
  ↓
Project model + explicit Actions/Queries + Policy
  ↓
/api/v1/projects + Expense/reporting deltas
  ↓
React clients and TailAdmin Project/Expense/reporting surfaces
  ↓
authorized user-visible outcome per story
```

Only the Project migration/enum/model/factory/policy and shared test helpers are foundational. Every phase
after readiness finishes a user story across all applicable layers; there is no independent backend or
frontend delivery phase.

## Project aggregate

- Create `projects` with tenant-safe Cost Center and Deferred PlanningYear references, five-value stage,
  optimistic `lock_version`, deletion provenance, timestamps and terminal soft delete.
- Add only the missing composite FK from existing `expenses.(tenant_id, project_id)` to Project; do not
  recreate `project_id`.
- `ProjectStage` uses technical English values and supplies no localized business logic.
- `Project` uses `SoftDeletes`, `Versionable`, relationships and a focused factory.
- `SaveProjectData` carries title, Cost Center, stage, target and expected lock version.
- A `ManagesProjects` concern centralizes persisted context checks, current reference validation, revision
  linkage, audit and deletion reason normalization without becoming a pass-through service.

## Authorization

- Reuse the already-present six Project abilities in `PermissionCatalogue`; add no duplicate names.
- `ProjectPolicy` mirrors tenant ownership and active-context behavior of `ContractPolicy`.
- Route middleware and Policy/Action checks both apply. Controllers resolve records through scoped queries,
  so foreign Tenant IDs return 404.
- UI abilities only hide affordances; the server remains authoritative.

## Project API

- Implement the endpoints in `contracts/projects-api.md` under `routes/api/v1/projects.php`.
- `ProjectController` rejects unsupported fields, validates request shapes, authorizes abilities, builds the
  DTO and delegates. It contains no economic rules.
- `ProjectListQuery` paginates current Project with Cost Center, target and current Expense count.
- `ProjectDetailQuery` returns the current Project, current linked Expense summaries and recent revision
  metadata without N+1 queries.
- Resources whitelist fields and never expose deletion provenance or raw version payloads.

## US-010-01 React/TailAdmin surfaces

- `frontend/src/api/projects.ts` owns typed CRUD, lookup and revision calls.
- Routes are `/progetti`, `/progetti/nuovo`, `/progetti/:projectId`, and
  `/progetti/:projectId/modifica`.
- Navigation adds Progetti beside Spese/Contratti with `project.view`.
- `ProjectForm` and `ProjectTable` compose existing Label/InputField/Select/Button/Alert/Table/Badge and
  common cards, with a responsive card layout below desktop widths; no new primitive or UI package.
- Pages implement tenant/ability gating, loading, empty, error and correlation-ID presentation.

## Deferred

- Validation requires an active same-Tenant PlanningYear for a new Deferred reference and clears target
  for non-Deferred stages. The current valid reference remains readable.
- `PromoteDeferredProjects` locks eligible rows for one Tenant, promotes each once to Proposed, increments
  lock version and records revision/audit in the transaction.
- Eligibility is target `year_label <= now(Tenant.timezone)->year`.
- `PromoteDeferredProjectsCommand` finds the protected active Administrator, iterates active Tenants and
  calls the Action. `bootstrap/app.php` registers a daily invocation. Missing operational actor fails
  closed and visibly.

## Expense relationship

- Add `Expense::project()` and pass `project_id` through request validation and `SaveExpenseData`.
- `ExpenseAggregateValidator` accepts only current same-Tenant Project, rejects Project + Contract, and
  preserves source-governed Contract restrictions during update.
- Detail/register Queries and DTO/Resources return Project ID/title/current status with tenant-safe joins.
- Expense editor loads current Project options, mutually clears Contract/Project selectors and submits only
  IDs. Detail/register provide Project navigation only when `project.view` is available.

## Economic-kernel extension

- `EconomicDatasetQuery` adds a tenant-safe LEFT JOIN to current Projects and selects Project ID/stage in
  the existing query; no eager-load/N+1 path.
- `EconomicLine` carries nullable Project context.
- `EconomicEngine` contains the sole classifier. Actual and Plafond contribution are Primary; Estimate/
  Quote follow the approved stage mapping. It produces exact `primary`, `proposed`, `idea`, `excluded`, and
  server-calculated `potential`.
- Funded Plafond consumption is not initially counted. Only uncovered overrun is added to Primary; existing
  net/VAT/gross reconciliation remains unchanged.
- `officialCurrentPosition` equals Primary. Primary-only monthly and by-cost-center outputs remain the
  official presentation. Existing by-type and accounting dimensions remain compatible.
- Reporting summary and line Resources expose the new output. Dashboard, Budget and Report clients consume
  it; React performs formatting only.

## Revision history and restore

- Each Project mutation saves the model before linking `latestVersions()` to a `RevisionBatch`.
- `ProjectRevisionQuery` lists batches, resolves one tenant/subject-scoped batch, returns a safe whitelisted
  snapshot/current comparison and supplies the exact Project `Version` to restore.
- `RestoreProjectRevision` locks the current Project, checks `lock_version`, revalidates current Cost Center,
  stage and target, applies the snapshot, writes a new version/revision/audit and records the source version.
- A soft-deleted Project cannot be resolved by the current-record API and therefore cannot be compared or
  restored. The package direct revert API remains disabled.
- The detail UI renders history, compare and restore with existing TailAdmin Modal/Table/card patterns,
  using a compact card layout for revision activity on mobile.

## Delete semantics

- `DeleteProject` locks the current Project and current linked Expense rows in one transaction.
- Any linked row throws stable `PROJECT_HAS_LINKED_EXPENSES`; no Expense is detached, reassigned or deleted.
- On success, normalize reason, write provenance and increment lock version, create delete revision/audit,
  then soft delete. No restore endpoint accepts a deleted root.
- Project detail always renders a confirmation modal with a reason field and shows the stable Italian
  blocked-delete message while preserving correlation ID.

## Migration compatibility and rollback behavior

- Migration is forward-only compatible with the existing expenses schema and uses composite tenant FKs.
- `down()` first removes the Expense→Project FK then drops Projects; no data migration or duplicate column.
- All complex mutations use transactions. Validation, stale lock, audit/revision failure or linked Expense
  conflict rolls back the complete mutation.
- No cascade path is introduced. Foreign keys restrict deletion.

## Test strategy

- TDD per story: API/Action/domain tests precede implementation tasks.
- Project lifecycle matrix covers same Tenant, missing ability, foreign ID, inactive actor/Tenant, invalid
  enum/references, Deferred target and stale version.
- Expense matrix covers same/foreign/deleted Project, Project+Contract, generated source, API fields.
- Accounting unit matrix covers every requested Estimate/Quote/Actual mapping, Potential, stage changes,
  exact strings and Plafond regression. Integration confirms one tenant/year/current-row scope.
- Revision/delete/promotion tests cover history/compare/restore, source immutability, terminal tombstone,
  atomic failures and idempotency.
- Frontend verification relies on TypeScript build, ESLint and browser inspection of the required routes,
  viewports, dark mode and failure states.

## OpenAPI and documentation propagation

The global file declared by permanent docs is absent. Implementation follows
`specs/010-projects/contracts/*`. Final documentation updates:

- `docs/DOMAIN.md`: only implemented Project, Expense exclusivity and bucket rules;
- `docs/STATUS.md`: mark Projects backend/frontend implemented;
- `docs/ARCHITECTURE.md` and `docs/OPERATIONS.md`: remove the false claim that a missing global OpenAPI is
  currently authoritative and point to implemented routes/resources/tests until a full contract is restored.

## Post-design Constitution Check

PASS with zero exceptions. The design keeps the feature vertical, Project non-monetary, Laravel
authoritative, tenancy fail-closed, exact decimals in one kernel, TailAdmin-only UI and explicit tests.
