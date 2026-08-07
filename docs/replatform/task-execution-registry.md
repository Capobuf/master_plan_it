# Task execution registry

Status: `NORMATIVE TASK SUPPLEMENT — EXACT COMMANDS AND PATH EXPANSIONS`

## API-only package execution order

The new API package overlay is executed in strict order: A2 → A3 → A4 → A5 → A6 → A7 → A8 →
A9. A8 cannot start until A2–A7 have reconciled implemented capabilities. A9-API-002 cannot
remove Laravel UI assets or Composer/frontend dependencies until A9-API-001 records parity.

Package command ownership is explicit: A2–A7 use the focused API contract commands registered in
their feature command files; A8 owns static capability/OpenAPI reconciliation; A9 owns route,
resource, package scans and the final backend command list. Historical browser/UI commands do not
prove any API package complete.

## Contract composition

A Ready task is the non-overlapping combination of:

1. its owning entry in `specs/<feature>/tasks.md` for ID, class, objective, exact dependency IDs, requirements, sequence, tests-first order, expected result and forbidden work;
2. `docs/replatform/task-readiness-registry.md` for source links, inherited invariants and stable errors;
3. one exact command entry in `task-execution/feature-001.md` through `feature-007.md`;
4. one row in `task-execution/path-overrides.md` only when the owning task uses abbreviated filenames or a directory shorthand.

The command files supersede abbreviated validation phrases such as `same tests`, `focused tests`, `schema test` or `Resource test`. The path manifest supersedes only abbreviated path lists; it does not change objectives, symbols, dependencies or requirements.

No file in this execution registry may override dependencies or task classes. No dependency override exists outside the owning `tasks.md` file. Any disagreement among the four records is a documentation blocker and the implementation agent must stop rather than choose a precedence rule.

## Registers

- `task-execution/feature-001.md`;
- `task-execution/feature-002.md`;
- `task-execution/feature-003.md`;
- `task-execution/feature-004.md`;
- `task-execution/feature-005.md`;
- `task-execution/feature-006.md`;
- `task-execution/feature-007.md`;
- `task-execution/path-overrides.md`.

## Execution rule

A task cannot be checked complete until its exact command has actually run and its result is recorded in the owning quickstart/verification artifact. No command in these files was executed while generating or remediating the documentation.
