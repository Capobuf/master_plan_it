# `/speckit.tasks` remediation summary

Status: `REMEDIATION COMPLETE — REVIEW AND RE-ANALYSIS REQUIRED`  
Branch: `tasks/remediate-analysis-3.0.1`  
Base: `laravel-replatform` commit `bdd03819d8d2e59f698d42ca33ee706472a3da02`  
Constitution: 3.0.1

## Task inventory

| Feature | Tasks | `[P]` tasks | User stories | Remediation impact |
|---|---:|---:|---:|---|
| 001 Platform foundation | 27 | 8 | 6 | notification primitive split from scheduler; audit view added |
| 002 Master data | 16 | 3 | 3 | readiness classes and exact shared-revision contract |
| 003 Expense domain | 23 | 7 | 5 | exact master-data prerequisites; attachment foundation/UI added |
| 004 Contracts/projects | 21 | 7 | 6 | confirmation implementation required; notification cycle removed |
| 005 Reporting/analytics | 25 | 6 | 6 | scenario implementation precedes comparison |
| 006 Migration/operations | 18 | 5 | 4 | Composer backup gate corrected; notification cycle removed |
| 007 Tenancy/access | 18 | 5 | 5 | reusable ownership concern split from late ability matrix |
| **Total** | **148** | **41** | **35** | — |

`[P]` means the named files/symbols can be worked on concurrently only after every exact prerequisite is complete. It never permits concurrent edits to shared Composer/configuration/provider/workflow files.

## Normative task composition

Each executable task is the combination of:

1. its checklist entry in the owning feature `tasks.md`;
2. the matching class/source/invariant/error/dependency record in `task-readiness-registry.md`;
3. its exact validation command in `task-execution/feature-001.md` through `feature-007.md`;
4. any complete path override in `task-execution/path-overrides.md`.

The execution registry supersedes abbreviated validation phrases in task files. Explicit dependency corrections are propagated into the owning task files. A mismatch among these records is a documentation blocker and does not authorize an implementation guess.

## Remediated critical path

```text
T001-001 scaffold and exact locks
        ↓
T001-005/T001-006 platform persistence and permission catalogue
        ↓
T007-001–T007-004 tenant/context/query foundation
        ↓
T007-012 reusable tenant-ownership Policy concern
        ↓
Feature 001 auth/shell + Feature 007 tenant/role lifecycle
        ↓
T002-001–T002-006 shared revisions and master-data foundation
        ↓
T002-009/T002-012/T002-015 exact selectors/resources
        ↓
T003 Money/current Expense + attachment foundation
        ↓
T003-014 ConfirmActual
        ↓
T004 projects/contracts/generated occurrences
        ↓
T005 kernel/Budget/scenarios/comparisons/output
        ↓
T006 migration/portability/backup/deployment
```

## Notification graph

```text
T001-017 tests delivery primitive
→ T001-018 implements delivery primitive
→ {T004-019/T004-020 renewal command, T006-014 operation-failure integration}
→ T001-025 final scheduler registration
```

No dependency returns from T001-018 to T004-020 or T006-014.

## Tenant authorization graph

```text
T007-003 context
→ T007-004 safe tenant lookup
→ T007-012 reusable ownership Policy concern
→ owning feature schemas/Policies/Queries
→ T007-011 complete ability/IDOR matrix
→ T007-013 architecture boundary
```

The complete matrix is a late integration gate and does not block master-data, Expense or contract schemas.

## Shared ownership

| Surface | Owning tasks | Consumers |
|---|---|---|
| Composer/frontend locks | T001-001, T001-002 | every feature |
| Sail/test/quality configuration | T001-003, T001-004, T001-022 | every feature |
| Permission catalogue | T001-006 | Feature 007 and all Policies |
| Tenant/TenantContext/middleware | T007-001–T007-004 | all tenant features |
| Reusable tenant Policy concern | T007-012 | owning feature Policies |
| Complete ability matrix | T007-011/T007-013 | final security gate only |
| Notification delivery primitive | T001-017/T001-018 | T004-020, T006-014 |
| Scheduler registration | T001-025 | completed commands only |
| Audit view | T001-026/T001-027 | tenant/global authorized readers |
| Revision storage/orchestration | T002-004–T002-006 | Features 002–004 |
| Master-data selectors | T002-009/T002-012/T002-015 | Expense create/update |
| Money/VAT/allocation | T003-001/T003-002 | Features 003–005 |
| Attachment persistence/policy | T003-021/T003-022 | T003-017/T003-018/T003-023 and portability |
| Current Expense Actions/Queries | T003-007–T003-018 | Features 004–006 |
| Contract occurrence generation | T004-010–T004-018 | Feature 005 and migration |
| Economic dataset/kernel | T005-001–T005-004 | dashboard, Budget, output and version capture |
| Scenario dataset | T005-019/T005-020 | comparison T005-016/T005-017 |
| Release artifact | T001-022/T001-023 | T006-015/T006-016 |

A consumer task may not recreate an owning symbol under another namespace.

## Findings addressed

| Analyze finding | Proposed disposition |
|---|---|
| ANALYZE-C-001 | RESOLVED — delivery primitive and scheduler registration split |
| ANALYZE-C-002 | RESOLVED — T007-012 foundational concern; matrix moved late with exact dependencies |
| ANALYZE-H-001 | RESOLVED — complete task contract includes readiness and execution registries |
| ANALYZE-H-002 | RESOLVED — source traceability regenerated with task/test IDs |
| ANALYZE-H-003 | RESOLVED — technical amendment sets PHP 8.3.32 target |
| ANALYZE-H-004 | RESOLVED — exact `composer require` gate in T006-012 |
| ANALYZE-H-005 | RESOLVED — T003-010 depends on T002-009/T002-012/T002-015 |
| ANALYZE-H-006 | RESOLVED — T004-010/T004-011 depend on T003-014 |
| ANALYZE-H-007 | RESOLVED — T005-020 precedes T005-016/T005-017 |
| ANALYZE-H-008 | RESOLVED — T001-026/T001-027 implement audit views with no export |
| ANALYZE-H-009 | RESOLVED — T003-021–T003-023 implement attachment foundation/UI |
| ANALYZE-H-010 | RESOLVED — prior self-check superseded and replaced after remediation |

These dispositions remain proposed until repeated `/speckit.analyze` verifies them.

## Next gate

1. review and merge the remediation PR;
2. run `/speckit.analyze` on the merged branch;
3. require CRITICAL `0` and HIGH `0`;
4. keep `/speckit.implement` blocked until that result.

No Composer install, scaffold, migration, test, workflow, artifact, backup, restore, import, deployment or cutover command was executed while remediating the documentation.