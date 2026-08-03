# Global execution sequence

Status: `TASK REMEDIATION COMPLETE — RE-ANALYSIS NEXT`

## Spec Kit sequence

| Step | Status | Required result |
|---|---|---|
| S0 Product clarification/Constitution 3.0.1 | COMPLETE | Q-001–Q-041 closed |
| S1 Reconcile prior PRs | COMPLETE | development/test and Budget/kernel inputs merged |
| S2 `/speckit.plan` | COMPLETE AND MERGED | research, ADRs, plans, physical models, contracts and quickstarts |
| S3 Initial `/speckit.tasks` | COMPLETE AND MERGED | seven feature task files |
| S4 Initial `/speckit.analyze` | FAILED AND RECORDED | 2 CRITICAL, 10 HIGH, 4 MEDIUM findings on commit `54bc8c5c72167b47eedc7ae9cc62211310035f8c` |
| S5 `/speckit.tasks` remediation | COMPLETE ON THIS BRANCH | corrected graph, readiness registry, traceability and technical amendment |
| S6 Remediation review/merge | CURRENT | Product Owner confirms remediation boundaries |
| S7 Repeated `/speckit.analyze` | NEXT AFTER MERGE | CRITICAL 0 and HIGH 0 |
| S8 `/speckit.implement` | BLOCKED | separate vertical-slice PRs with real locks/code/tests |
| S9 Cutover evidence | LATER | real export, host, report inventory, restore and deployment rehearsal |

## Remediated implementation critical path

1. Feature 001 scaffold, exact locks, Sail and test/CI guardrails;
2. Feature 007 tenant schema, context and safe tenant lookup;
3. Feature 007 reusable tenant-ownership Policy concern;
4. Feature 001 authentication/shell and Feature 007 tenant/role lifecycle;
5. Feature 002 shared revisions and exact master-data selectors;
6. Feature 003 Money, current Expense and attachment foundation;
7. Feature 003 Actual confirmation;
8. Feature 004 projects, contracts and generated occurrences;
9. Feature 005 kernel, Budget, scenarios, comparisons and outputs;
10. Feature 006 migration, portability, backup and deployment.

Notification execution is explicitly:

```text
T001-018 delivery primitive
→ T004-020 renewal command and T006-014 operation-failure integration
→ T001-025 final scheduler registration
```

The exact task graph, task composition and shared ownership are in `tasks-summary.md`, `task-readiness-registry.md` and the seven feature `tasks.md` files.

## Vertical-slice rule

Every implementation slice includes schema/rollback constraints, owning Action/query, authorization and tenant isolation, revision/audit behavior where relevant, Filament UI, focused tests, documentation and actual validation results. Package-only, database-only and unrelated refactor PRs are prohibited unless the task explicitly owns a verified prerequisite gate.

## Rollback principles

- before real data: reviewed revert and reversible migrations;
- after data: prefer forward correction or feature disable; never claim destructive rollback without verified backup/reconciliation;
- generated occurrences and Published BudgetVersion require explicit preservation;
- cutover rollback separates previous artifact, database recovery and shared files, followed by smoke and reconciliation.