# `/speckit.tasks` second remediation summary

Status: `HISTORICAL SECOND-REMEDIATION SUMMARY — INVENTORY RETAINED; PHASE CLAIM SUPERSEDED`
Provenance branch: `tasks/remediate-analysis-3.0.1-r2`  
Base: `laravel-replatform` commit `eb75be04d600f5c9ada954da0593ab0a13d94ff8`  
Constitution: 3.0.1

Integration state is authoritative only in GitHub PR metadata. This document records the package content and remains valid before and after integration.

Current phase selection and finding counts are owned by `artifact-status-register.md` and the current `/speckit.analyze` result. The table below is historical. The 2026-08-05 vertical-slice remediation adds T001-028/T001-029 and T005-026/T005-027 without renumbering or changing prior checkboxes: the live inventory is 158 tasks across 35 user stories, by feature `29/17/24/21/27/18/22`, with 43 `[P]` tasks.

## Task inventory

| Feature | Tasks | `[P]` tasks | User stories | Second-remediation impact |
|---|---:|---:|---:|---|
| 001 Platform foundation | 27 | 8 | 6 | workflow/release reclassified as foundation; scheduler/audit assigned to US5/US6; Feature 007 audit/password requirements mapped |
| 002 Master data | 16 | 3 | 3 | path ownership aligned with the execution manifest |
| 003 Expense domain | 23 | 7 | 5 | path ownership aligned; deletion/fixture targets fully expanded |
| 004 Contracts/projects | 21 | 7 | 6 | renewal notification tests/implementation assigned to contract lifecycle US2 |
| 005 Reporting/analytics | 25 | 6 | 6 | selected-tenant branding DTO consumed by report/output tasks |
| 006 Migration/operations | 18 | 5 | 4 | exact portable-schema dependencies; deployment foundation class; guarded Composer rollback; executable cutover evidence test |
| 007 Tenancy/access | 20 | 6 | 5 | tenant branding owner added; complete matrix delayed until every exact owning implementation exists |
| **Total** | **150** | **42** | **35** | — |

`[P]` means the named files/symbols can be worked on concurrently only after every exact prerequisite is complete. It never permits concurrent edits to shared Composer/configuration/provider/workflow files.

## Non-overlapping task contract

Each executable task is the combination of four records with distinct ownership:

1. the owning feature `tasks.md` owns ID, class, objective, exact dependency IDs, requirements, sequence, tests-first order, expected result and forbidden work;
2. `task-readiness-registry.md` owns source links, inherited invariants and stable errors only;
3. `task-execution/feature-001.md` through `feature-007.md` own exact validation commands only;
4. `task-execution/path-overrides.md` owns complete expansions for abbreviated path lists only.

No dependency or class override exists outside the owning `tasks.md`. A mismatch is a documentation blocker and never authorizes an implementation guess.

## Current critical path (2026-08-05 remediation)

```text
accepted platform/tenant/auth/authorization foundation
        ↓
Slice 0: T001-028 → T001-029 operational UI contract/lock/shell
        ↓
Slice 1A: T003-007 → T003-008 → T003-009 manual Expense register
        ↓
Slice 1B: T003-010 → T003-011 → T003-012 manual Expense create/edit
        ↓
Slice 2: T005-001 → T005-002 → T005-003 → T005-004 → T005-006 → T005-007 → T005-008
        ↓
Slice 3: T005-026 hardened Expense → current Budget checkpoint
        ↓
attachments/remaining Expense lifecycle
        ↓
Feature 004 projects/contracts/generation → T005-027 project-stage query enrichment
        ↓
BudgetVersion/scenarios/comparisons/output
        ↓
T006 migration/portability/backup/deployment
        ↓
T007-011/T007-013 complete cross-feature security gates
```

## Notification graph

```text
T001-017 tests delivery primitive
→ T001-018 implements delivery primitive
→ {T004-019/T004-020 renewal behavior, T006-014 operation-failure behavior}
→ T001-025 final scheduler registration
```

No dependency returns from T001-018 to T004-020 or T006-014.

## Tenant authorization graph

```text
T007-003 context
→ T007-004 safe tenant lookup
→ T007-012 reusable ownership Policy concern
→ every exact owning feature implementation
→ T007-011 complete ability/IDOR matrix
→ T007-013 architecture boundary
→ T007-018 final verification
```

T007-011 names every prerequisite task ID directly. It no longer depends on test-only placeholders or narrative phrases.

## Shared ownership

| Surface | Owning tasks | Consumers |
|---|---|---|
| Composer/frontend locks | T001-001, T001-002, T001-029 | every feature; T001-029 is the sole Preline/Tailwind remediation writer |
| Sail/test/quality configuration | T001-003, T001-004, T001-022 | every feature |
| Permission catalogue | T001-006 | Feature 007 and all Policies |
| Tenant/TenantContext/middleware | T007-001–T007-004 | all tenant features |
| Tenant report branding | T007-019/T007-020 | T005-021–T005-023, T006-007, T006-010 |
| Reusable tenant Policy concern | T007-012 | owning feature Policies |
| Complete ability matrix | T007-011/T007-013 | final security gate only |
| Notification delivery primitive | T001-017/T001-018 | T004-020, T006-014 |
| Scheduler registration | T001-025 | completed commands only |
| Audit settings/view | T001-019–T001-021, T001-026/T001-027 | tenant/global authorized readers |
| Revision storage/orchestration | T002-004–T002-006, T002-017 | Features 002–004 |
| Master-data selectors | T002-009/T002-012/T002-015 | Expense create/update |
| Money/VAT/allocation | T003-001/T003-002 | Features 003–005 |
| Attachment persistence/policy | T003-021/T003-022 | T003-017/T003-018/T003-023 and portability |
| Current Expense Actions/Queries | T003-007–T003-018 | Features 004–006 |
| Contract occurrence generation | T004-010–T004-018 | Feature 005 and migration |
| Economic dataset/kernel | T005-001–T005-004; T005-027 project-context enrichment | dashboard, Budget, output and version capture |
| Scenario dataset | T005-019/T005-020 | comparison T005-016/T005-017 |
| Release artifact | T001-022/T001-023 | T006-015/T006-016 |

A consumer task may not recreate an owning symbol under another namespace.

## Rerun findings addressed

| Rerun finding | Proposed disposition |
|---|---|
| RERUN-H-001 | PROPOSED RESOLVED — one four-part contract with non-overlapping field ownership |
| RERUN-H-002 | PROPOSED RESOLVED — one comprehensive path manifest; duplicate readiness path table removed |
| RERUN-H-003 | PROPOSED RESOLVED — runtime behavior moved from `[VER]` to `[FND]` or owning `[USn]` |
| RERUN-H-004 | PROPOSED RESOLVED — narrative dependencies replaced by exact task IDs in owning task files |
| RERUN-H-005 | PROPOSED RESOLVED — T007-011 depends on terminal owning implementations, including T001-027 and output/attachment/command implementations |
| RERUN-H-006 | PROPOSED RESOLVED — Feature 007 audit, password, branding and retention requirements added to source traceability |
| RERUN-H-007 | PROPOSED RESOLVED — T007-019/T007-020 own tenant branding; T005-021–T005-023 consume it |
| RERUN-H-008 | PROPOSED RESOLVED — T006-017 has exact requirement IDs, file paths, command and verifiable OPEN/VERIFIED result rules |
| RERUN-M-001 | PROPOSED RESOLVED — phase metadata use integration-stable wording and defer live state to GitHub |
| RERUN-M-002 | PROPOSED RESOLVED — feature spec/plan headers use stable phase wording |
| RERUN-M-003 | PROPOSED RESOLVED — T006-012 exact command restores Composer files on resolution failure |
| RERUN-M-004 | PROPOSED RESOLVED — inventory is stated as proposed until re-analysis, not self-certified as a PASS |

These dispositions remain proposed until the next `/speckit.analyze` independently verifies them.

## Next gate

On the integrated remediation base:

1. run `/speckit.analyze`;
2. require CRITICAL `0` and HIGH `0`;
3. keep `/speckit.implement` blocked until that result.

No Composer install, scaffold, migration, test, workflow, artifact, backup, restore, import, deployment or cutover command was executed while remediating the documentation.
