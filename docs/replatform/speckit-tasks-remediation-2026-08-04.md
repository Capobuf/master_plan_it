# `/speckit.tasks` remediation — 2026-08-04

Status: `PROPOSED REMEDIATION FOR ANALYZE3-C-001 AND ANALYZE3-H-001–H-005`

## Invocation and repository shape

The installed Spec Kit 0.15.2 `$speckit-tasks` instructions were applied on a temporary branch based on integrated `laravel-replatform` commit `74b29a81348278fc451a4dd3353d194ef22d6711`.

The required command `.specify/scripts/bash/setup-tasks.sh --json` was executed and returned exit code 1 because upstream Spec Kit requires one active feature directory or `.specify/feature.json`. Master Plan IT intentionally contains seven integrated feature directories and no single active-feature pointer. No pointer was invented and no existing artifact was overwritten. The command rules were therefore applied to all seven already-validated feature packages.

`.specify/extensions.yml` is absent, so no before/after task hooks were registered.

## Remediation

| Finding | Resolution |
|---|---|
| ANALYZE3-C-001 | Feature 003 authorization now distinguishes mutable current Actual rows from immutable prior revision/audit evidence and uses exact abilities instead of fixed role names. |
| ANALYZE3-H-001 | T003-005, T005-006, T006-014, and T007-010 now identify every owning path and symbol directly or through the normative path manifest. |
| ANALYZE3-H-002 | T001-024, T002-016, T003-020, T004-021, T005-025, T006-018, and T007-018 now resolve every documentation target through exact repository-relative paths in the normative manifest. |
| ANALYZE3-H-003 | Sparse Feature 003–005 requirement ranges were replaced with exact defined IDs or contiguous defined sub-ranges. |
| ANALYZE3-H-004 | T003-019 maps legacy lifecycle evidence to exact FR/INV IDs; narrative `migration notes` is no longer a requirement token. |
| ANALYZE3-H-005 | Historical audits/method notes are explicitly deprecated and current roadmap, inventory, architecture, language, development-contract, and clarification-result statements reflect Q-001–Q-041. |

## Validation

- 150 task lines, 150 unique task IDs;
- every task retains the required Markdown checkbox and stable ID format;
- sparse-range validator reports no undefined FR crossed by a task or source-traceability range;
- every remediated abbreviated path resolves through `docs/replatform/task-execution/path-overrides.md`;
- `git diff --check` passes;
- no application code, scaffold, dependency, migration, or application test was created or executed;
- `/speckit.implement` was not executed.

This remediation does not close its own findings. Closure requires merge into `laravel-replatform` followed by a new independent `/speckit.analyze` on the integrated HEAD.
