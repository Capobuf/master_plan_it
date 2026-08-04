# Checklist — Requirements

Feature: `004-contracts-and-projects`

Purpose: requirement-quality gate for project, contract, term, generation, and revision lifecycles
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

## Contract/project lifecycle requirement quality

- [ ] CHK001 Are project create/update/stage/delete/restore and deferred-promotion requirements independently defined with concurrency behavior? [Completeness, Spec §FR-004-001, §FR-004-010–FR-004-015]
- [ ] CHK002 Are complete-term-set updates and explicit term deletion distinguished from omitted form input? [Clarity, Spec §FR-004-020–FR-004-023]
- [ ] CHK003 Are contract/project revision restore requirements consistent with immutable prior evidence and unchanged linked Actual rows? [Consistency, Spec §FR-004-037–FR-004-038]
- [ ] CHK004 Are idempotent synchronization, partial failure rollback, suppression persistence, and notification exception scenarios covered? [Coverage, Exception/Recovery, Spec §FR-004-025–FR-004-039]
- [ ] CHK005 Are authorization and tenant-boundary requirements stated for screens, commands, notifications, generation, revisions, and direct identifiers? [Traceability, Authorization contract]
