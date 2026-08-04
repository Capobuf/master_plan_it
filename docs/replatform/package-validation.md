# Package validation

Status: `SECOND REMEDIATION PACKAGE — RE-ANALYSIS REQUIRED ON INTEGRATED BASE`

Scope: second task remediation based on `laravel-replatform` commit `eb75be04d600f5c9ada954da0593ab0a13d94ff8`.

## Task package

- seven feature `tasks.md` files aligned to one task contract;
- `task-readiness-registry.md` limited to sources, invariants and stable errors;
- seven exact command registers;
- one exact path-expansion manifest;
- technical and phase metadata updated;
- source traceability completed for Feature 007 audit/password/retention/branding requirements;
- 150 unchecked tasks;
- 35 user stories;
- 42 `[P]` markers after exact prerequisites;
- no application code or checklist modified.

## Structural validation

- task IDs are unique inside each feature namespace;
- every task uses `- [ ] Txxx-yyy`;
- each task has one class in its owning task file or one non-ambiguous readiness range;
- dependencies and task classes exist only in owning feature task files;
- no dependency field intentionally contains prose, ranges, wildcards or unnamed prerequisites;
- every task ID has one exact command entry;
- every abbreviated target list has one complete row in `task-execution/path-overrides.md`;
- `[VER]` tasks create evidence/documentation only;
- notification dependencies are delivery primitive → feature commands → final scheduler registration;
- complete tenant matrix follows exact owning implementation tasks;
- exact master-data readiness precedes Expense create/update;
- `ConfirmActual` precedes contract synchronization;
- scenario persistence/query precedes scenario comparison;
- tenant branding has one owner and one immutable consumer DTO;
- backup gate uses guarded `composer require` and restores both Composer files on resolution failure;
- T006-017 uses exact requirement IDs and executable document/operation tests;
- shared ownership is documented in `tasks-summary.md`.

These validations are a package self-check and do not replace `/speckit.analyze`.

## Superseded target checks

The target contains no planned `ExpenseRowState`, `ReplaceExpenseRow`, `ExpenseRowAudit`, `ContractAnnualizer`, parallel economic calculator, `ReportPdfRenderer`, Preline dependency, immutable Actual rule, persisted current Budget total or current `Active/Replaced/Cancelled` lifecycle. Mentions may remain only in explicit supersession or forbidden-work statements.

## Technical gates retained

- dependency locks and package smoke are implementation tasks, not claimed results;
- `spatie/laravel-backup` 10.3.0 remains conditional on PHP 8.3.32 Composer resolution;
- MySQL integration tests remain required;
- no implicit database reset is allowed;
- performance thresholds require executable measurement;
- cutover evidence remains external and OPEN.

## Rerun-finding disposition

- contradictory registry ownership: corrected by non-overlapping field ownership;
- incomplete paths: corrected by the sole path manifest;
- `[VER]` runtime work: reclassified;
- narrative prerequisites: replaced with exact IDs;
- early matrix: moved after terminal implementations;
- Feature 007 traceability: completed;
- tenant branding: assigned to T007-019/T007-020 and consumed by T005-021–T005-023;
- T006-017: exact requirements/command/result added;
- stale metadata and feature headers: corrected with stable wording;
- Composer rollback command: made executable.

Every disposition remains proposed until `/speckit.analyze` confirms it.

## Integrity

Git object history and PR diff are the documentation integrity source. No hand-maintained whole-tree manifest is introduced.

## Not performed

No Laravel scaffold, Composer/frontend resolution, migration, static/accounting/application/browser test, workflow, ZIP build, backup, restore, host preflight, import dry-run, deployment or cutover operation was executed.

## Next command

Integration state is read from GitHub PR metadata. On the integrated remediation base, repeat `/speckit.analyze` and require CRITICAL `0` and HIGH `0`.
