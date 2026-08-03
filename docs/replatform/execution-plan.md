# Global execution sequence

Status: `TASKS COMPLETE — ANALYSIS NEXT`

## Spec Kit sequence

| Step | Status | Required result |
|---|---|---|
| S0 Product clarification/Constitution 3.0.1 | COMPLETE | Q-001–Q-041 closed |
| S1 Reconcile prior PRs | COMPLETE | development/test and Budget/kernel inputs merged |
| S2 `/speckit.plan` | COMPLETE AND MERGED | research, ADRs, integrated/feature plans, physical models, contracts and quickstarts |
| S3 Plan review/merge | COMPLETE | planning commit `6be02f58ccda27c48d9a43f5c2fb3df9dd161a0e` on `laravel-replatform` |
| S4 `/speckit.tasks` | COMPLETE ON TASK BRANCH | seven dependency/user-story task files plus cross-feature summary |
| S5 Task review/merge | CURRENT | Product Owner confirms task decomposition and boundaries |
| S6 `/speckit.analyze` | NEXT AFTER MERGE | CRITICAL 0 and HIGH 0 for implementation readiness |
| S7 `/speckit.implement` | BLOCKED | separate vertical-slice PRs with real locks/code/tests |
| S8 Cutover evidence | LATER | real export, host, report inventory, restore and deployment rehearsal |

## Implementation critical path

1. Feature 001 scaffold, locks, Sail and test/CI guardrails;
2. Feature 007 tenant schema and explicit context;
3. Feature 001 authentication and panel integration;
4. Feature 007 configurable tenant access;
5. Feature 002 shared revision infrastructure and master data;
6. Feature 003 Money and current Expense aggregate;
7. Feature 004 projects, contracts and generated occurrences;
8. Feature 005 economic kernel, Budget, versions and outputs;
9. Feature 006 migration, portability, backup and deployment.

The exact task graph, shared-file ownership and MVP checkpoints are in `tasks-summary.md` and the seven feature `tasks.md` files.

## Vertical-slice rule

Every implementation slice includes its schema/rollback constraints, owning Action/query, authorization and tenant isolation, revision/audit behavior where relevant, Filament UI, focused tests, documentation and actual validation results. Package-only, database-only and broad unrelated refactor PRs are prohibited unless the task explicitly owns a verified prerequisite gate.

## Rollback principles

- before real data: reviewed revert and reversible migrations;
- after data: prefer forward correction or feature disable; never claim destructive rollback without verified backup/reconciliation;
- generated occurrences and Published BudgetVersion require explicit preservation;
- cutover rollback separates previous artifact, database recovery and shared files, followed by smoke and reconciliation.
