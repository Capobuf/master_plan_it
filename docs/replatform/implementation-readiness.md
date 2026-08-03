# Implementation readiness

Status: `PLAN COMPLETE — TASKS AND ANALYSIS REQUIRED`

Constitution 3.0.1, 41 product decisions, integrated architecture, technical research and Feature 001–007 plans/data models/quickstarts are current on this branch.

| Feature | Planned scope | Status |
|---|---|---|
| 001 Platform foundation | exact runtime direction, Sail/test/CI, auth, settings, audit, scheduler, release | PLAN COMPLETE; dependency lock execution pending |
| 002 Master data | years/vendors/cost centers, lifecycle, revisions, policies/UI | PLAN COMPLETE |
| 003 Expense domain | Money/VAT/allocation, one current aggregate, Actual confirmation/revisions/deletion | PLAN COMPLETE |
| 004 Contracts/projects | project buckets, terms, source keys, system/user ownership, suppression/resume, notifications | PLAN COMPLETE |
| 005 Reporting/analytics | shared kernel, rolling Budget, scenarios, BudgetVersion, comparison, print/CSV/XLSX | PLAN COMPLETE |
| 006 Migration/operations | staging/import, portability, conditional backup, immutable deployment | PLAN COMPLETE; NOT CUTOVER READY |
| 007 Tenancy/access | explicit context, Spatie teams/Shield, lifecycle, protected abilities, isolation | PLAN COMPLETE |

## Remaining implementation-readiness gates

1. Product Owner/technical review of this plan branch.
2. `/speckit.tasks` regenerates every stale task file with exact files/symbols/dependencies/tests/commands/results/forbidden work.
3. `/speckit.analyze` reports CRITICAL 0 and HIGH 0 for implementation readiness.
4. First implementation task executes exact Composer/frontend lock resolution and package smoke tests. Static research is not an installation claim.
5. Implementation proceeds through separate reviewed PRs in the dependency sequence from `replatform-plan.md`.

## Conditional package gate

`spatie/laravel-backup` 10.3.0 remains conditional because its Composer metadata and documentation disagree on PHP floor. Failure on PHP platform 8.3.32 blocks backup implementation and requires plan/ADR amendment; it does not activate a fallback.

All other pinned package targets also require real lock/smoke before feature code relies on them.

## Cutover gates

Feature 006 remains not CUTOVER READY until:

- real source export anomaly/dry-run evidence;
- final hosting capabilities/paths/tools;
- signed legacy report parity inventory;
- verified installation backup restore rehearsal;
- migration reconciliation/sign-off;
- deployment/rollback rehearsal.

## Prohibited interpretation

- plan complete is not implementation complete;
- current `tasks.md` files are stale until `/speckit.tasks` replaces them;
- no tests/build/package installs/workflows were executed by this documentation plan;
- old fixed-role, immutable-Actual, replacement-state, fixed-retention, implicit-output or Italian-only assumptions remain superseded.
