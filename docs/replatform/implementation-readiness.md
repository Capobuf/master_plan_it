# Implementation readiness

Status: `TASK REMEDIATION COMPLETE — RE-ANALYSIS REQUIRED`

Constitution 3.0.1, Q-001–Q-041, integrated architecture, technical research, physical models, Feature 001–007 plans and remediated task files are current on this branch.

| Feature | Planned and tasked scope | Status |
|---|---|---|
| 001 Platform foundation | runtime/Sail/test/CI, auth, password operations, settings, audit view/retention, notification primitive, scheduler, release | REMEDIATED; dependency lock pending implementation |
| 002 Master data | shared revisions, years, vendors, cost centers, lifecycle and restore | REMEDIATED |
| 003 Expense domain | Money/VAT/allocation, current aggregate, Actual confirmation, revisions, deletion and attachments | REMEDIATED |
| 004 Contracts/projects | lifecycle, terms, source keys, controlled sync, suppression/resume and renewal notifications | REMEDIATED |
| 005 Reporting/analytics | shared kernel, rolling Budget, scenarios, BudgetVersion, comparison, print/CSV/XLSX | REMEDIATED |
| 006 Migration/operations | staging/apply, portability, conditional backup and immutable deployment | REMEDIATED; NOT CUTOVER READY |
| 007 Tenancy/access | context, reusable ownership concern, lifecycle, configurable RBAC and final isolation matrix | REMEDIATED |

## Task-remediation validation

- 148 tasks across 35 user stories;
- 41 tasks marked `[P]` only after exact prerequisites;
- stable `[FND]`, `[USn]` and `[VER]` classes;
- task entry plus readiness-registry record forms the complete constitutional task contract;
- source links, inherited invariants, stable errors and exact-path expansions recorded;
- notification and tenancy dependency cycles removed;
- exact master-data, Actual-confirmation and scenario dependencies propagated into owning task files;
- audit-view and attachment persistence/policy/UI tasks added;
- source traceability regenerated with current task/test IDs and requirement coverage ledger;
- PHP target and backup Composer gate corrected by technical amendment;
- stale feature header statuses dispositioned by `artifact-status-register.md`.

## Remaining implementation-readiness gates

1. Product Owner/technical review and merge of this remediation branch.
2. Repeat `/speckit.analyze` against the merged artifacts.
3. Require CRITICAL `0` and HIGH `0`; Medium findings must be resolved or explicitly dispositioned.
4. Only then begin `/speckit.implement` through separate reviewed vertical-slice PRs.
5. The first implementation task performs real Composer/frontend lock resolution and package smoke tests.

## Conditional package gate

`spatie/laravel-backup` 10.3.0 remains conditional. T006-012 uses an exact `composer require` operation on platform PHP 8.3.32, verifies both Composer files and restores them on resolution failure. Failure blocks backup implementation and requires a technical amendment; no fallback is approved.

All pinned dependencies require real lock/smoke before feature code relies on them.

## Cutover gates

Feature 006 remains not CUTOVER READY until real source export evidence, final hosting capabilities, signed report inventory, verified restore rehearsal, migration reconciliation/sign-off and deployment/rollback rehearsal are available.

## Prohibited interpretation

- remediation is not application implementation;
- task checkboxes remain unchecked until commands and results are actually executed;
- initial analysis findings are proposed resolved, not closed until repeated `/speckit.analyze` confirms them;
- no package, code, schema, test, workflow or operational command was executed by this documentation cycle;
- fixed-role, immutable-Actual, replacement-state, fixed-retention, implicit-output and Italian-only assumptions remain superseded.