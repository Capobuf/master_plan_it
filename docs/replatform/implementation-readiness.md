# Implementation readiness

Status: `TASKS COMPLETE — ANALYSIS REQUIRED`

Constitution 3.0.1, Q-001–Q-041, integrated architecture, technical research, physical models, Feature 001–007 plans and regenerated task files are current on this branch.

| Feature | Planned and tasked scope | Status |
|---|---|---|
| 001 Platform foundation | runtime/Sail/test/CI, auth, password operations, settings, audit, scheduler, release | TASKS COMPLETE; real dependency lock pending implementation |
| 002 Master data | shared revision infrastructure, years, vendors, cost centers, lifecycle and restore | TASKS COMPLETE |
| 003 Expense domain | Money/VAT/allocation, current aggregate, Actual confirmation, revisions and deletion | TASKS COMPLETE |
| 004 Contracts/projects | project lifecycle, terms, source keys, controlled synchronization, suppression/resume and notifications | TASKS COMPLETE |
| 005 Reporting/analytics | shared kernel, rolling Budget, scenarios, BudgetVersion, comparison, print/CSV/XLSX | TASKS COMPLETE |
| 006 Migration/operations | staging/apply, portability, conditional backup and immutable deployment | TASKS COMPLETE; NOT CUTOVER READY |
| 007 Tenancy/access | explicit context, tenant lifecycle, configurable RBAC, protected abilities and isolation | TASKS COMPLETE |

## Task-generation validation

- 142 tasks across 35 user stories;
- 39 tasks marked `[P]` only after declared prerequisites;
- exact files and symbols identified;
- tests written before production behavior;
- focused validation commands and expected outcomes present;
- forbidden scope and fallback behavior present;
- former task IDs and superseded implementation assumptions explicitly retired;
- shared-file ownership and cross-feature critical path documented in `tasks-summary.md`;
- revision ownership moved to Feature 002 to remove the master-data/Expense cycle;
- tenancy/RBAC resource ownership assigned to Feature 007, with Feature 001 limited to panel/platform integration.

## Remaining implementation-readiness gates

1. Product Owner/technical review and merge of this task branch.
2. `/speckit.analyze` checks consistency, traceability, dependency validity and test coverage and reports CRITICAL 0 and HIGH 0.
3. The first implementation task performs real Composer/frontend lock resolution and package smoke tests.
4. Implementation proceeds through separate reviewed PRs in the dependency sequence from `tasks-summary.md` and `replatform-plan.md`.

## Conditional package gate

`spatie/laravel-backup` 10.3.0 remains conditional because its Composer metadata and documentation disagree on the PHP floor. Failure on platform PHP 8.3.32 blocks backup implementation and requires plan/ADR amendment; it does not activate a fallback.

All pinned dependencies require real lock/smoke before feature code relies on them.

## Cutover gates

Feature 006 remains not CUTOVER READY until real source export evidence, final hosting capabilities, signed report inventory, verified restore rehearsal, migration reconciliation/sign-off and deployment/rollback rehearsal are available.

## Prohibited interpretation

- task generation is not application implementation;
- task checkboxes remain unchecked until their commands and results are actually executed;
- `/speckit.analyze` has not yet been run against these tasks;
- no package, code, schema, test, workflow or operational command was executed by this documentation cycle;
- fixed-role, immutable-Actual, replacement-state, fixed-retention, implicit-output and Italian-only assumptions remain superseded.
