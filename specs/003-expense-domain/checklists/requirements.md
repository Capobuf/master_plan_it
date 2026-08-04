# Checklist — Requirements

Feature: `003-expense-domain`

Purpose: requirement-quality gate for Expense lifecycle, revision, authorization, and attachments
Created: 2026-08-04
Audience/depth: PR reviewer / formal implementation-readiness gate

## Preserved cross-feature baseline

- [ ] Every statement is labelled VERIFIED CURRENT, PROPOSED TARGET or another permitted evidence label where current/target could be confused.
- [ ] Every FR is atomic, has an acceptance scenario and maps to at least one test and task.
- [ ] Every invariant has a stable ID, error behavior, transaction boundary and focused test.
- [ ] Every write route/action has allow and deny authorization tests.
- [ ] Money never uses authoritative floats; rounding points and allocation residual are tested.
- [ ] Foreign keys, indexes, nullability, delete behavior and migration order are explicit.
- [ ] Empty, validation, authorization, concurrency, duplicate/idempotency and legacy-anomaly states are covered.
- [ ] UI specifies keyboard, focus, loading, error, empty and responsive behavior.
- [ ] Shared-hosting operation does not require Node runtime, Redis, WebSockets or permanent workers.
- [ ] No task asks the coding agent to choose architecture, packages, names or paths.
- [ ] Quickstart contains future commands only and does not claim execution.
- [ ] Documentation analysis has no unresolved CRITICAL/HIGH finding.
- [ ] Every entity/query/screen in this feature has explicit tenant ownership or is documented as global.
- [ ] Same-tenant allow and other-tenant deny paths are testable.
- [ ] Reports, exports, attachments, direct links, commands, and scheduled work fail closed without valid tenant context.

## Expense-lifecycle requirement quality

- [ ] CHK001 Are current Expense identity, row identity, explicit row deletion, and last-row behavior defined independently from revision snapshots? [Completeness, Spec §FR-003-001–FR-003-002, §FR-003-035]
- [ ] CHK002 Are create, update, delete, confirm, restore, history, attachment, output, and import abilities defined without hard-coded role-name authorization? [Clarity, Spec §FR-003-030–FR-003-035; Authorization contract]
- [ ] CHK003 Are mutable current Actual rows consistently separated from immutable revision and audit evidence across spec, data model, contracts, and tasks? [Consistency, Spec §FR-003-031–FR-003-034]
- [ ] CHK004 Are stale writes, invalid references, mixed-tenant rows, partial aggregate failure, and restoration revalidation covered as atomic exception/recovery scenarios? [Coverage, Spec §FR-003-030, §FR-003-063]
- [ ] CHK005 Are private attachment metadata, parent authorization, size/MIME/checksum, deletion compensation, and payload exclusion requirements complete? [Completeness, Spec §FR-003-061–FR-003-062]
