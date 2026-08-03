# Implementation readiness

Status: `SECOND TASK REMEDIATION COMPLETE — RE-ANALYSIS REQUIRED`

Constitution 3.0.1, Q-001–Q-041, integrated architecture, technical research, physical models, Feature 001–007 plans and the second-remediation task package are current on this branch.

| Feature | Planned and tasked scope | Status |
|---|---|---|
| 001 Platform foundation | runtime/Sail/test/CI, auth, password operations, settings, audit view/retention, notification primitive, scheduler, release | SECOND REMEDIATION COMPLETE; dependency lock pending implementation |
| 002 Master data | shared revisions, years, vendors, cost centers, lifecycle and restore | SECOND REMEDIATION COMPLETE |
| 003 Expense domain | Money/VAT/allocation, current aggregate, Actual confirmation, revisions, deletion and attachments | SECOND REMEDIATION COMPLETE |
| 004 Contracts/projects | lifecycle, terms, source keys, controlled sync, suppression/resume and renewal notifications | SECOND REMEDIATION COMPLETE |
| 005 Reporting/analytics | shared kernel, rolling Budget, scenarios, BudgetVersion, comparison, tenant-branded print/CSV/XLSX | SECOND REMEDIATION COMPLETE |
| 006 Migration/operations | staging/apply, portability, conditional backup and immutable deployment | SECOND REMEDIATION COMPLETE; NOT CUTOVER READY |
| 007 Tenancy/access | context, ownership concern, lifecycle, configurable RBAC, tenant branding and final isolation matrix | SECOND REMEDIATION COMPLETE |

## Proposed readiness evidence

- 150 tasks across 35 user stories;
- 42 tasks marked `[P]` only after exact prerequisites;
- `[FND]`, `[USn]` and `[VER]` classes with runtime behavior prohibited in `[VER]`;
- task fields divided among four non-overlapping records;
- dependencies and classes owned only by feature `tasks.md` files;
- exact command coverage for every task ID;
- one comprehensive path manifest for every abbreviated target list;
- notification and tenancy graphs acyclic;
- complete matrix delayed until exact terminal implementations exist;
- Feature 007 audit, password, retention and branding requirements traced to task/test owners;
- T007-019/T007-020 own tenant branding and T005-021–T005-023 consume it;
- T006-012 command restores both Composer files on resolution failure;
- T006-017 has exact requirements, paths, command and verifiable OPEN/VERIFIED evidence rules.

These are proposed dispositions, not an analysis PASS.

## Remaining implementation-readiness gates

1. Product Owner/technical review and merge of the second-remediation PR.
2. Repeat `/speckit.analyze` against the merged artifacts.
3. Require CRITICAL `0` and HIGH `0`; Medium findings must be resolved or explicitly dispositioned.
4. Only then begin `/speckit.implement` through separate reviewed vertical-slice PRs.
5. The first implementation task performs real Composer/frontend lock resolution and package smoke tests.

## Conditional package gate

`spatie/laravel-backup` 10.3.0 remains conditional. T006-012 uses the exact guarded shell command in `task-execution/feature-006.md`, resolves on platform PHP 8.3.32, and restores both Composer files when resolution fails. Failure blocks backup implementation and requires a technical amendment; no fallback is approved.

All pinned dependencies require real lock/smoke before feature code relies on them.

## Cutover gates

Feature 006 remains not CUTOVER READY until real source export evidence, final hosting capabilities, signed report inventory, verified restore rehearsal, migration reconciliation/sign-off and deployment/rollback rehearsal are available. T006-017 must keep unavailable evidence `OPEN`.

## Prohibited interpretation

- remediation is not application implementation;
- task checkboxes remain unchecked until commands and results are actually executed;
- second-remediation findings are proposed resolved, not closed until `/speckit.analyze` confirms them;
- no package, code, schema, test, workflow or operational command was executed by this documentation cycle;
- fixed-role, immutable-Actual, replacement-state, fixed-retention and implicit-output assumptions remain superseded;
- application UI remains initially Italian while technical identifiers remain English; tenant report branding does not white-label the Master Plan IT shell.
