# `/speckit.analyze` — Laravel replatform 3.0.1

Status: `FAILED — IMPLEMENTATION BLOCKED`  
Mode: read-only cross-artifact analysis  
Analysis date: 2026-08-03  
Analyzed branch: `laravel-replatform`  
Analyzed commit: `54bc8c5c72167b47eedc7ae9cc62211310035f8c`  
Constitution: 3.0.1

## Result

| Severity | Count | Gate |
|---|---:|---|
| CRITICAL | 2 | must be 0 before implementation |
| HIGH | 10 | must be 0 before implementation |
| MEDIUM | 4 | resolve or explicitly disposition before implementation |
| LOW | 0 | — |

`/speckit.implement` remains blocked. Product clarification is complete; the blockers are task-graph, traceability, technical-contract and implementation-readiness defects.

## Scope and method

The analysis compared:

- Constitution 3.0.1;
- approved decisions Q-001 through Q-041;
- cross-cutting versioning, permission, operations, development and test contracts;
- technical research, permission catalogue, error catalogue and source traceability;
- Feature 001 through 007 `spec.md`, `plan.md` and regenerated `tasks.md`;
- task inventory, shared ownership and execution sequence.

Checks covered constitution compliance, contradictions, orphan requirements, task readiness, bidirectional traceability, dependency validity, user-story coverage, test coverage and superseded assumptions.

This command did not modify specifications, plans, tasks or product decisions.

## CRITICAL findings

### ANALYZE-C-001 — Notification dependency cycles

**Artifacts**

- `specs/001-platform-foundation/tasks.md`: `T001-018` depends on `T004-020` and `T006-014`;
- `specs/004-contracts-and-projects/tasks.md`: `T004-020` depends on `T001-018`;
- `specs/006-data-migration-and-operations/tasks.md`: `T006-014` depends on `T001-018`.

**Conflict**

The graph contains two direct cycles:

```text
T001-018 ↔ T004-020
T001-018 ↔ T006-014
```

No task in either pair can become Ready first.

**Impact**

Scheduler registration, notification delivery, contract-renewal checks and operation-failure notifications are not executable in the declared order.

**Required remediation**

Split the platform-owned notification delivery primitive from scheduler registration. The primitive must precede feature commands; feature commands then precede final scheduler registration. Regenerate the affected task IDs/dependencies without changing Q-023.

### ANALYZE-C-002 — Tenant policy/ownership dependency cycle and non-resolvable prerequisite

**Artifacts**

- `T002-001`, `T003-003` and `T004-001` depend on `T007-012`;
- `T007-012` depends on `T007-011`;
- `T007-011` depends on “each owning feature’s first policy/query task”.

**Conflict**

The owning features wait for the final cross-feature policy integration, while that integration waits for policy/query work in those same features. The quoted prerequisite is also not an exact task ID.

**Impact**

Master data, Expense and contract/project schema work cannot enter a deterministic implementation sequence. The declared acyclic graph is incorrect.

**Required remediation**

Separate the reusable tenant-ownership Policy concern and architecture guards from the later full ability matrix. Foundation features should depend only on the reusable concern/context task; the final matrix should depend on completed feature Policies and Queries.

## HIGH findings

### ANALYZE-H-001 — Constitution Definition of Ready is not satisfied systematically

Constitution C-01 and “Definition of Ready — task” require stable user story, source links, requirements, invariants, exact files, exact symbols, implementation sequence, tests-first, validation commands, errors, forbidden work and a verifiable result.

Across the task corpus:

- no task carries an explicit legacy/product source link;
- setup and foundation tasks frequently have no user-story mapping;
- many tasks omit invariant IDs or stable error codes;
- several tasks use non-exact targets such as migration wildcards, directories, “Pages/Form/Table classes”, “revision page”, “notification classes”, “view/components”, “workflow handoff configuration” or “under `app/...`”.

The task files therefore do not meet the constitution’s own Ready gate even where their functional intent is valid.

**Required remediation:** regenerate task entries with exact paths/symbols, source rule IDs, applicable invariants/error codes and explicit story classification or a constitution-approved foundation marker.

### ANALYZE-H-002 — Bidirectional source traceability remains stale and entirely `PENDING`

`docs/replatform/source-traceability.md` still states that plan/task mapping is pending. Its Task column remains `PENDING`, including migration test IDs.

This contradicts:

- Constitution C-01, which requires bidirectional source-to-requirement-to-task mapping;
- repository state claiming `/speckit.plan` and `/speckit.tasks` complete;
- the task self-check claiming exact execution data is present.

**Required remediation:** map every traceability row to current stable task IDs and tests, add missing migration/operations rows, and remove obsolete regeneration language.

### ANALYZE-H-003 — PHP compatibility contract conflicts with the approved runtime

`versioning-permissions-and-operations-contract.md` requires package validation against Laravel 13, **PHP 8.5**, Filament 5 and MySQL. The approved technical target and task gate use **PHP 8.3.32**.

This creates two incompatible package acceptance standards.

**Required remediation:** amend the contract to the approved runtime floor/target or explicitly approve a PHP 8.5 compatibility matrix. This is a technical-document correction, not a product decision.

### ANALYZE-H-004 — Backup dependency gate command is not executable as written

`T006-012` uses:

```text
composer update spatie/laravel-backup:10.3.0 --with-all-dependencies
```

The package is conditional and is not introduced by `T001-001`. Composer’s package-addition operation is `require vendor/package:constraint`; `update` operates on dependencies already present and does not use the declared command as a reliable add-and-pin gate.

**Required remediation:** define the exact `composer require`/constraint change, lock-file diff and rollback on resolution failure. Do not introduce a fallback package.

### ANALYZE-H-005 — Expense tasks lack exact master-data prerequisites

Expense creation and update require current planning year, vendor, cost-center and selector behavior, but Feature 003 depends narratively on “master-data selectors” rather than exact completed task IDs.

Schema/revision completion alone does not provide the Actions and selectors required by `CreateExpense` and `UpdateExpense`.

**Required remediation:** add exact dependencies on the relevant Feature 002 planning-year, cost-center and vendor implementation tasks, or create a single explicit master-data readiness gate.

### ANALYZE-H-006 — Contract synchronization does not depend on Actual confirmation implementation

`T004-010` and `T004-011` require confirmation to make a generated Actual user-authoritative. They depend on Expense confirmation tests (`T003-013`) but not the implementation task `T003-014`.

**Impact:** contract synchronization could be implemented against behavior that has tests but no production Action.

**Required remediation:** depend on `T003-014` and the current Expense create/update Actions explicitly.

### ANALYZE-H-007 — Budget comparison includes scenarios before the scenario source exists

`T005-016`/`T005-017` require current/version/**scenario** source variants, but scenario persistence/query implementation is `T005-020` and is not a dependency.

**Required remediation:** either make comparison current/version first and add scenario comparison after `T005-020`, or place scenario implementation before the comparison tasks.

### ANALYZE-H-008 — Approved audit-view capability has no implementation surface

Approved decisions, Feature 007 and the permission catalogue require:

- same-tenant `audit.view`;
- Administrator tenant/global audit view;
- no audit export at launch.

Tasks create `AuditEvent`, retention and authorization matrices, but do not define an exact audit Query, Policy and Filament Resource/Page for reading tenant/global events.

**Required remediation:** add explicit audit read-model, policy and UI tasks with minimization, tenant/global scope and no-export tests.

### ANALYZE-H-009 — Attachment persistence and authorization are incomplete

Technical research defines an application-owned `attachments` table/model with tenant, parent, private path, checksum, metadata and soft deletion. `T003-018` introduces upload/delete Actions and a download controller without an explicit attachment migration/model/factory/policy task.

**Required remediation:** add exact attachment schema/model/policy/query tasks before lifecycle Actions and include parent permission, private download, checksum and rollback tests.

### ANALYZE-H-010 — Existing task self-check asserts disproven facts

`docs/replatform/spec-kit-analysis.md` states that:

- the dependency graph is acyclic;
- exact paths/symbols/dependencies are present;
- resolved notification dependencies are correct.

ANALYZE-C-001, ANALYZE-C-002 and ANALYZE-H-001 disprove those claims.

**Required remediation:** replace the self-check conclusions after the task corrections; do not treat the current self-check as implementation approval.

## MEDIUM findings

### ANALYZE-M-001 — Repository phase metadata is stale after merge

Root and replatform READMEs still identify `tasks/replatform-3.0.1` as active/pending review and retain the former planning-base commit. The merged source of truth is `laravel-replatform` at `54bc8c5c72167b47eedc7ae9cc62211310035f8c`.

### ANALYZE-M-002 — All feature spec/plan status headers are stale

Feature specs still say `PLAN REGENERATION REQUIRED`; plans still say `READY FOR /speckit.tasks AFTER PLAN REVIEW`, although both phases have been completed and merged.

This does not change product behavior but can cause an agent to execute the wrong command.

### ANALYZE-M-003 — Cross-cutting planning gate remains stale

`versioning-permissions-and-operations-contract.md` still lists `/speckit.plan` and `/speckit.tasks` as future commands. It must reflect that the current phase is failed analysis/remediation.

### ANALYZE-M-004 — Requirement coverage is nominal, not provably bidirectional

Broad ranges such as `FR-005-001–FR-005-070` and feature-wide final verification tasks make nominal coverage high, but they do not prove that each acceptance scenario and invariant has one owning implementation task and one focused test.

A regenerated traceability matrix should provide per-requirement coverage and expose any remaining orphan, including planning-year lifecycle operations.

## Coverage summary

| Area | Assessment |
|---|---|
| Product decisions | complete; no open product question found |
| Constitution principles | target architecture broadly aligned; task Ready gate violated |
| Economic source and Money rules | covered in plan/tasks |
| Operational revisions and BudgetVersion separation | covered, subject to exact task readiness corrections |
| Tenant isolation | strongly specified, but dependency graph is cyclic |
| Notifications | product contract complete, task graph cyclic |
| Audit retention | covered |
| Audit viewing | orphan implementation surface |
| Attachments | lifecycle intent covered; persistence/policy incomplete |
| Reporting/output parity | covered |
| Migration/portability | covered; dependency expressions need normalization |
| Backup | covered conditionally; Composer gate command invalid |
| Cutover evidence | correctly remains open and must not be fabricated |

## Required remediation sequence

1. Run `/speckit.tasks` remediation for ANALYZE-C-001 and ANALYZE-C-002.
2. Bring every task into Constitution Definition of Ready compliance.
3. Correct technical-contract/runtime and Composer-gate inconsistencies.
4. Add missing audit-view and attachment persistence/policy tasks.
5. Correct exact master-data, confirmation and scenario dependencies.
6. Regenerate `source-traceability.md` with current task/test IDs.
7. Update stale phase headers and replace the prior self-check conclusions.
8. Re-run `/speckit.analyze`.
9. Permit `/speckit.implement` only when CRITICAL = 0 and HIGH = 0.

## Not performed

No specification, plan, task, Constitution or decision was altered. No application code, dependency resolution, migration, static/accounting/application/browser test, workflow, build, backup, restore, import or deployment was executed by this analysis.
