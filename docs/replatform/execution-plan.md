# Global execution sequence

Status: `AUTHORIZATION REMEDIATION INTEGRATED — NEXT GATE /speckit.analyze ON LATEST INTEGRATED HEAD`

## Spec Kit sequence

| Step | Status | Required result |
|---|---|---|
| S0 Product clarification/Constitution 3.0.1 | COMPLETE | Q-001–Q-041 closed |
| S1 Reconcile prior PRs | COMPLETE | development/test and Budget/kernel inputs merged |
| S2 `/speckit.plan` | COMPLETE AND MERGED | research, ADRs, plans, physical models, contracts and quickstarts |
| S3 Initial `/speckit.tasks` | COMPLETE AND MERGED | seven feature task files |
| S4 Initial `/speckit.analyze` | FAILED AND RECORDED | 2 CRITICAL, 10 HIGH, 4 MEDIUM |
| S5 First `/speckit.tasks` remediation | COMPLETE AND MERGED | initial graph/readiness corrections |
| S6 First repeated `/speckit.analyze` | FAILED AND RECORDED | 0 CRITICAL, 8 HIGH, 4 MEDIUM |
| S7 Second `/speckit.tasks` remediation | COMPLETE AND MERGED | non-overlapping task contract, exact dependencies/paths, class correction, full traceability and branding owner |
| S8 Spec Kit/tooling and independent analysis cycle | COMPLETE AND MERGED | Spec Kit 0.15.2; analysis 1 CRITICAL, 5 HIGH, 4 MEDIUM; task/checklist remediations |
| S9 Second 2026-08-04 `/speckit.analyze` | FAILED AND RECORDED | 0 CRITICAL, 1 HIGH, 1 MEDIUM on `0d3a84c38e177f90e53f74bb84f78594ca00d32d` |
| S10 Authorization `/speckit.plan` remediation | COMPLETE AND MERGED | ability-based contracts; no Editor/Viewer domain branches |
| S11 Third 2026-08-04 `/speckit.analyze` | FAILED AND RECORDED | 0 CRITICAL, 1 HIGH, 0 MEDIUM on `f673513c137e797d52bc5f3099ed3c1c10b4a129` |
| S12 Complete C-07/Q-016 contract propagation | COMPLETE AND MERGED | no role matrices; exact abilities; inactive-tenant decision current |
| S13 Final `/speckit.analyze` | NEXT ON LATEST INTEGRATED HEAD | CRITICAL 0 and HIGH 0; Medium corrected or dispositioned |
| S14 `/speckit.implement` | BLOCKED | separate vertical-slice PRs with real locks/code/tests |
| S15 Cutover evidence | LATER | real export, host, report inventory, restore and deployment rehearsal |

## Proposed implementation critical path

1. Feature 001 scaffold, exact locks, Sail and quality/release foundation;
2. Feature 007 tenant schema, context and safe tenant lookup;
3. Feature 007 reusable tenant-ownership Policy concern;
4. Feature 001 authentication/shell and Feature 007 tenant/role lifecycle;
5. Feature 007 selected-tenant report branding;
6. Feature 002 shared revisions and exact master-data selectors;
7. Feature 003 Money, current Expense and attachment foundation;
8. Feature 003 Actual confirmation;
9. Feature 004 projects, contracts, generated occurrences and renewal command;
10. Feature 005 kernel, Budget, scenarios, comparisons and branded outputs;
11. Feature 006 migration, portability, backup and deployment;
12. Feature 007 complete cross-feature ability/IDOR and architecture gates.

Notification execution is explicitly:

```text
T001-018 delivery primitive
→ T004-020 renewal command and T006-014 operation-failure integration
→ T001-025 final scheduler registration
```

The exact task graph, task composition and shared ownership are in `tasks-summary.md`, `task-readiness-registry.md`, `task-execution-registry.md` and the seven feature `tasks.md` files.

## Vertical-slice rule

Every implementation slice includes schema/rollback constraints, owning Action/query, authorization and tenant isolation, revision/audit behavior where relevant, Filament UI, focused tests, documentation and actual validation results. Package-only, database-only and unrelated refactor PRs are prohibited unless the task explicitly owns a verified prerequisite gate.

## Rollback principles

- before real data: reviewed revert and reversible migrations;
- after data: prefer forward correction or feature disable; never claim destructive rollback without verified backup/reconciliation;
- generated occurrences and Published BudgetVersion require explicit preservation;
- cutover rollback separates previous artifact, database recovery and shared files, followed by smoke and reconciliation.
