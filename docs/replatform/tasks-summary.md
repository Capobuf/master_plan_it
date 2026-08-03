# `/speckit.tasks` execution summary

Status: `PROPOSED TARGET — TASK GENERATION COMPLETE; ANALYSIS REQUIRED`  
Branch: `tasks/replatform-3.0.1`  
Base: `laravel-replatform` commit `6be02f58ccda27c48d9a43f5c2fb3df9dd161a0e`  
Constitution: 3.0.1

## Generated task inventory

| Feature | Tasks | `[P]` tasks | User stories | MVP checkpoint |
|---|---:|---:|---:|---|
| 001 Platform foundation | 24 | 7 | 6 | reproducible scaffold, safe tests, authentication with real tenant context |
| 002 Master data | 16 | 3 | 3 | shared revisions plus planning-year management |
| 003 Expense domain | 20 | 6 | 5 | exact Money, current register and independent row create/update |
| 004 Contracts/projects | 21 | 7 | 6 | project lifecycle; contracts/generation follow independently |
| 005 Reporting/analytics | 25 | 6 | 6 | shared kernel and current rolling Budget/dashboard |
| 006 Migration/operations | 18 | 5 | 4 | controlled staging/dry-run/apply; not cutover readiness |
| 007 Tenancy/access | 18 | 5 | 5 | tenant schema, explicit context and Administrator lifecycle/entry |
| **Total** | **142** | **39** | **35** | — |

`[P]` means the named files/symbols can be worked on concurrently after every declared dependency is complete. It does not permit concurrent edits to shared Composer/configuration/provider/workflow files.

## ID migration

The former task files are superseded in full. Their IDs were not reused because they encoded one-story file-by-file work, fixed roles, Preline, immutable Actual, replacement-state records, append-missing-only generation, duplicated reporting queries or a server-PDF boundary.

The current stable namespaces are:

- `T001-001` onward;
- `T002-001` onward;
- `T003-001` onward;
- `T004-001` onward;
- `T005-001` onward;
- `T006-001` onward;
- `T007-001` onward.

No implementation issue or commit may cite a former task ID as current authority.

## Cross-feature critical path

```text
Feature 001 scaffold / package catalogue
        ↓
Feature 007 tenant schema + TenantContext
        ↓
Feature 001 authentication / shell integration
        ↓
Feature 007 configurable tenant access
        ↓
Feature 002 shared revision infrastructure + master data
        ↓
Feature 003 Money + current Expense aggregate
        ↓
Feature 004 projects / contracts / generated occurrences
        ↓
Feature 005 economic kernel / Budget / versions / outputs
        ↓
Feature 006 migration / portability / backup / deployment
```

The late Feature 006 / Feature 007 migration integration is ordered explicitly:

```text
T006-005 dry-run tenancy tests
→ T007-016 tenant-ownership integration test
→ T006-009 apply implementation
→ T007-017 final context/Gate integration
```

This is not a cycle: each edge consumes an already defined lower-level surface.

## Shared-file ownership

| Surface | Owning tasks | Consumers |
|---|---|---|
| Composer/frontend locks | T001-001, T001-002 | every feature |
| Sail/test/quality configuration | T001-003, T001-004, T001-022 | every feature |
| Permission package/catalogue | T001-006 | Feature 007 and all Policies |
| Tenant/TenantContext/middleware | T007-001–T007-004 | Feature 001 and all tenant features |
| Tenant indicator and User/Role/Tenant Resources | T007-006, T007-010 | Feature 001 panel composition |
| Password operations/platform auth | T001-008–T001-016 | Feature 007 resources |
| Revision storage/orchestration | T002-004–T002-006 | Features 002–004 |
| Money/VAT/allocation | T003-001, T003-002 | Features 003–005 |
| Current Expense Actions/Queries | T003-007–T003-018 | Features 004–006 |
| Contract occurrence generation | T004-010–T004-018 | Feature 005 and migration |
| EconomicDataset kernel/query | T005-001–T005-004 | dashboard, Budget, report, output, version capture |
| Notification delivery/schedule | T001-017, T001-018 | T004-020 and T006-014 |
| Release artifact | T001-022, T001-023 | T006-015, T006-016 |

A consumer task may not recreate an owning symbol under another namespace.

## Story independence rule

Each user-story phase declares:

- an observable goal;
- an independent test path;
- exact files and symbols;
- prerequisite task IDs;
- mapped FR/INV identifiers;
- focused validation command;
- expected result;
- explicitly forbidden work.

Tests are required by the specifications and are written before production code. A story checkpoint is not complete until its focused test suite passes without weakening accounting, tenant, authorization, revision or output invariants.

## Next Spec Kit gate

1. Review and merge the task-generation PR.
2. Run `/speckit.analyze` against the merged plans, contracts and regenerated tasks.
3. Require CRITICAL `0` and HIGH `0` for implementation readiness.
4. Start `/speckit.implement` in a separate branch/PR only after that result.

No Composer install, scaffold, migration, test, build, backup, restore, import, deployment or cutover command was executed while generating these tasks.
