# Checklist — Requirements

Feature: `002-master-data`

Purpose: requirement-quality gate for planning-year, cost-center, and vendor behavior
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

## Master-data-specific requirement quality

- [ ] CHK001 Are planning-year identity, active-state, uniqueness, and selection semantics defined without an implicit default tenant or year? [Completeness, Spec §FR-002-001–FR-002-003]
- [ ] CHK002 Are cost-center tree depth, parent validity, cycle prevention, ordering, and inactive-node behavior unambiguous? [Clarity, Spec §FR-002-004–FR-002-007]
- [ ] CHK003 Are vendor deactivate/reactivate, current-reference, selector, and revision requirements consistent with Q-030 and Q-031? [Consistency, Spec §FR-002-008–FR-002-011]
- [ ] CHK004 Are stale-write, duplicate-code, referenced-record, restoration, and other-tenant scenarios represented by measurable acceptance criteria? [Coverage, Exception/Recovery, Spec §FR-002-012–FR-002-013]
- [ ] CHK005 Is every master-data write requirement mapped to an exact ability while reads and selectors fail closed for inactive or foreign records? [Traceability, Authorization contract]
