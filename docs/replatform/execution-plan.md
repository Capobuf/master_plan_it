# Global execution sequence

Status: `PLAN COMPLETE — TASKS NEXT`

## Spec Kit sequence

| Step | Status | Required result |
|---|---|---|
| S0 Product clarification/Constitution 3.0.1 | COMPLETE | Q-001–Q-041 closed |
| S1 Reconcile prior PRs | COMPLETE | development/test and Budget/kernel inputs merged |
| S2 `/speckit.plan` | COMPLETE ON THIS BRANCH | research, ADRs, integrated/feature plans, physical models, contracts, quickstarts |
| S3 Plan review/merge | CURRENT | Product Owner approves impacts; technical corrections resolved |
| S4 `/speckit.tasks` | NEXT | exact dependency/user-story tasks with files, symbols, tests, commands, results and forbidden work |
| S5 `/speckit.analyze` | BLOCKED BY TASKS | CRITICAL 0; HIGH 0 for implementation readiness |
| S6 `/speckit.implement` | BLOCKED | separate vertical-slice PRs with real locks/code/tests |
| S7 Cutover evidence | LATER | real export/host/report inventory/restore/deployment rehearsal |

## Planned implementation order

1. P0 scaffold/runtime/Sail/test/CI and dependency lock;
2. P1 tenants/users/context/RBAC/settings/audit shell;
3. P2 years/vendors/cost centers/attachments/revision infrastructure;
4. P3 Money/Expense/Actual confirmation/revisions;
5. P4 projects/contracts/terms/generation/notifications;
6. P5 economic kernel/dashboard/current Budget;
7. P6 scenarios/BudgetVersion/comparisons/print/CSV/XLSX;
8. P7 migration/portability/backup/deployment.

This order is planning context, not an executable task list.

## Vertical-slice rule

Every milestone includes schema/rollback, Action/query, authorization/tenant isolation, revision/audit where relevant, Filament UI, tests, documentation and real validation results. Package-only, database-only or broad unrelated refactor PRs are prohibited.

## Rollback principles

- before real data: reviewed revert/reversible migrations;
- after data: prefer forward correction/feature disable; never claim destructive rollback without verified backup/reconciliation;
- generated occurrences and Published BudgetVersion require explicit preservation;
- cutover rollback separates previous artifact, DB recovery and shared files, followed by smoke/reconciliation.
