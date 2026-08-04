# Checklist — Ux Accessibility

Feature: `001-platform-foundation`

Purpose: requirement-quality gate for application-shell UX and accessibility
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

## Shell and accessibility requirement quality

- [ ] CHK001 Are keyboard order, visible focus, validation focus, destructive confirmation, and modal escape requirements defined for every shared shell pattern? [Completeness, Spec §FR-001-004–FR-001-005]
- [ ] CHK002 Are responsive navigation and content-breakpoint requirements measurable rather than described only as responsive? [Clarity, Spec §FR-001-004]
- [ ] CHK003 Are tenant-configured output language and the non-white-labelled Master Plan IT application shell consistently distinguished? [Consistency, Spec §FR-001-019; Q-013, Q-039]
- [ ] CHK004 Are loading, empty, denied, stale-conflict, and unexpected-error presentation requirements defined with stable error references? [Coverage, Spec §FR-001-005]
- [ ] CHK005 Are contrast, semantic labeling, chart fallback, print accessibility, and browser-baseline criteria linked to measurable acceptance scenarios? [Measurability, Gap, NFR-001-UX-01]
