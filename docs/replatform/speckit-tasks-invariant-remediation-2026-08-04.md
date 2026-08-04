# `/speckit.tasks` invariant-ownership remediation — 2026-08-04

Status: `TASK ARTIFACT REMEDIATION — INTEGRATION STATE IN GITHUB PR METADATA; RE-ANALYSIS REQUIRED`  
Finding: `ANALYZE6-H-001` from `speckit-analyze-2026-08-04-fourth.md`  
Product impact: none  
Task-count impact: none

## Command routing

The Spec Kit `tasks` workflow was selected because the remaining finding concerned executable task/test ownership, not product ambiguity, Constitution text or architecture. The upstream prerequisite command was executed on the clean remediation branch:

```text
.specify/scripts/bash/setup-tasks.sh --json
ERROR: Feature directory not found. Set SPECIFY_FEATURE_DIRECTORY or run the specify command to create .specify/feature.json.
ERROR: Failed to resolve feature paths
exit: 1
```

Spec Kit 0.15.2 assumes one active feature pointer. This repository deliberately integrates seven approved feature packages and has no `.specify/feature.json`; no pointer was invented. The repository-wide task validators remain the applicable integrated check.

## Exact ownership correction

| Invariant | Acceptance/test identity | Existing owning tasks | Existing focused test | Correction |
|---|---|---|---|---|
| `INV-TEN-009` | `AC-007-08`; `TEST-007-009` | `T007-008`, `T007-009` | `tests/Feature/IdentityAccess/TenantUserMembershipTest.php` | The test task now requires unchanged authored/audited tenant history and exact open-assignment IDs; the Action task owns unchanged history and deterministic reassignment-needed output. |
| `INV-TEN-010` | `AC-007-10`; `TEST-007-010` | `T001-019`, `T001-020` | `tests/Feature/Audit/AuditRetentionTest.php` | The test task now names protected business/revision/BudgetVersion boundary assertions; the Action task limits deletion to eligible expired audit-event IDs. |

The task readiness and bidirectional source ledgers carry the same invariant, acceptance/test, task and focused-test ownership. No task ID, dependency, class, user story, requirement, stable decision or validation command changed.

## Validation gate

Before merge, repository-wide checks must confirm:

- 150 unique tasks and the same dependency graph;
- all 154 functional requirements mapped;
- all 75 defined invariants mapped to at least one task;
- all 150 task IDs covered by one exact command;
- no duplicate command ownership;
- no stale role matrices or Q-016 interpretation.

After integration, `/speckit.analyze` must be repeated independently on the authoritative `laravel-replatform` HEAD. This document does not close `ANALYZE6-H-001` by assertion.
