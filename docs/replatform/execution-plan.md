# Global execution sequence

Status: `DOCUMENTATION GATE PASSED ON CONSTITUTION 5.0.0 — IMPLEMENTATION IN PROGRESS`

## Spec Kit sequence

| Step | Status | Required result |
|---|---|---|
| S0 Product clarification/Constitution 5.0.0 | COMPLETE | legacy Q-001–Q-041 plus current seven-feature clarification decisions closed |
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
| S13 Fourth 2026-08-04 `/speckit.analyze` | FAILED AND RECORDED | 0 CRITICAL, 1 HIGH, 0 MEDIUM on `348a557c58388ff917646dd6a02cb00cbdc1f513`; orphan INV-TEN-009/010 task ownership only |
| S14 Invariant `/speckit.tasks` remediation | COMPLETE; INTEGRATION STATE IN GITHUB | exact existing task/test/readiness/source owners; unchanged task count and graph |
| S15 Historical Constitution 3.0.1 final `/speckit.analyze` | COMPLETE AND RECORDED; SUPERSEDED | CRITICAL 0, HIGH 0, MEDIUM 0 on `ba3744fbc7043f3f3b036416e6c082e4cb7c6831`; does not authorize later artifacts |
| S16 Constitution 4.0.0/5.0.0 clarification and propagation | COMPLETE; CURRENT WORKTREE | fixed calendar years, attachment manifests/quota, operational settings, output/performance/accessibility and terminal source deletion propagated |
| S17 Historical integrated `/speckit.analyze` | COMPLETE AND RECORDED; SUPERSEDED AS LIVE STATUS | 0 CRITICAL, 0 HIGH, 0 MEDIUM; 300/300 requirement coverage; 153/153 command/readiness coverage; 295/295 checklist items at that snapshot |
| S18 `/speckit.implement` | ACTIVE | coordinator-owned work packages with real locks/code/tests; rolling analysis added T002-017 |
| S19 Cutover evidence | LATER | real export, host, report inventory, restore and deployment rehearsal |

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
