# Spec Kit task-generation self-check

Mode: read-only consistency review performed while generating `/speckit.tasks`. This is not the subsequent official `/speckit.analyze` command and does not validate application implementation.

## Coverage

| Check | Result |
|---|---|
| Product questions | Q-001–Q-041 closed; 0 open |
| Constitution | 3.0.1 applied |
| `/speckit.plan` | complete and merged |
| Feature task files | 001–007 regenerated |
| Task inventory | 142 tasks, 35 user stories, 39 `[P]` markers |
| Exact execution data | paths, symbols, dependencies, FR/INV, tests, commands, expected results and forbidden work present |
| Dependency graph | acyclic after moving shared revisions to Feature 002 and removing Feature 001/007 ownership duplication |
| Shared ownership | documented in `tasks-summary.md` |
| Current/history boundary | current, revisions, audit, deletion, scenarios, exceptions and BudgetVersion remain distinct |
| Tenant isolation | explicit context, scoped lookup, policy/Action and test tasks |
| Economic parity | one kernel/query and format-parity tasks |
| Migration/operations | staging, explicit batches, portability, conditional backup and immutable deployment tasks |

## Superseded assumptions excluded from planned implementation

- fixed tenant-role behavior;
- permanent Actual immutability;
- current `Active/Replaced/Cancelled` lifecycle;
- replacement-row graph;
- mandatory Estimate→Quote→Actual;
- persisted synchronized current Budget total;
- Italian-only output;
- fixed audit retention;
- audit export/portability;
- implicit filtered/complete scope;
- Preline/general second UI kit;
- server PDF renderer;
- implicit database reset;
- blanket deadlock retry.

## Findings resolved during task generation

| ID | Initial severity | Finding | Resolution |
|---|---:|---|---|
| TASK-SELF-001 | HIGH | Feature 002 depended on Expense-owned revision infrastructure while Expense depended on master data. | Shared revision package/schema/orchestration moved to Feature 002 T002-004–T002-006; Feature 003 now integrates only its aggregate. |
| TASK-SELF-002 | HIGH | TenantContext, tenant indicator and User/Role resources had duplicate Feature 001/007 ownership. | Feature 007 owns tenant domain and resources; Feature 001 owns panel/platform integration. |
| TASK-SELF-003 | HIGH | Feature 001 scheduler referenced the generated-expense deletion task rather than the renewal command. | Dependency corrected to T004-020; operations failure delivery remains T006-014. |
| TASK-SELF-004 | MEDIUM | Some BudgetVersion foundation tasks lacked their user-story marker. | Historical-year schema/persistence tasks are now `[US2]`; story-neutral kernel foundation remains unmarked. |
| TASK-SELF-005 | MEDIUM | Feature 005 current query referenced an outdated Expense task ID. | Dependency corrected to Feature 003 T003-008. |

## Remaining gates

| ID | Severity before execution | Gate | Disposition |
|---|---:|---|---|
| TASK-GATE-001 | HIGH | Exact Composer/frontend lock and package smoke have not run. | T001-001/T001-002; failure blocks and requires ADR amendment. |
| TASK-GATE-002 | HIGH | Official `/speckit.analyze` has not assessed the merged tasks. | Run after review/merge; require CRITICAL 0 and HIGH 0. |
| TASK-GATE-003 | MEDIUM | Real legacy samples, host profile and report inventory are unavailable. | Keep Feature 006 not CUTOVER READY. |
| TASK-GATE-004 | LOW | Performance thresholds require executable code/data. | T005-024 before cache/preaggregation. |

These are phase gates, not unresolved product decisions. No implementation task may be checked complete until its validation command is actually executed and recorded.

## Phase

- `/speckit.clarify`: COMPLETE;
- `/speckit.plan`: COMPLETE AND MERGED;
- `/speckit.tasks`: COMPLETE ON THIS BRANCH, pending review/merge;
- `/speckit.analyze`: NEXT VALID COMMAND AFTER MERGE;
- `/speckit.implement`: BLOCKED.

## Not performed

No code, dependency resolution, schema migration, test, workflow, artifact, backup, restore, import dry-run or deployment was executed.
