# Task execution registry

Status: `NORMATIVE TASK CONTRACT — EXACT COMMANDS AND PATHS`

A Ready task consists of:

1. its owning feature entry in `specs/<feature>/tasks.md`;
2. its class/source/invariant/error/dependency record in `task-readiness-registry.md`;
3. its exact validation command in `task-execution/feature-<NNN>.md`;
4. any exact-path override in `task-execution/path-overrides.md`.

The execution files supersede abbreviated phrases such as `same tests`, `focused tests`, `schema test` or `Resource test`. A task without one matching exact command is not Ready. Any disagreement among these records is a documentation blocker; the implementation agent must stop rather than guess.

Registers:

- `task-execution/feature-001.md`;
- `task-execution/feature-002.md`;
- `task-execution/feature-003.md`;
- `task-execution/feature-004.md`;
- `task-execution/feature-005.md`;
- `task-execution/feature-006.md`;
- `task-execution/feature-007.md`;
- `task-execution/path-overrides.md`.

No command in these files has been executed. They are implementation contracts only.