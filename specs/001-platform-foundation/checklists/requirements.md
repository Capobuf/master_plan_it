# Checklist — Requirements

Feature: `001-platform-foundation`

Purpose: requirement-quality gate for platform identity, audit, scheduling, and release requirements
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

## Platform-specific requirement quality

- [ ] CHK001 Are authentication, logout, password recovery, session invalidation, and inactive-user denial requirements separately defined with measurable outcomes? [Completeness, Spec §FR-001-001–FR-001-003]
- [ ] CHK002 Are global Administrator operations distinguished from tenant-context operations without implying impersonation or a tenant fallback? [Clarity, Spec §FR-001-008, §FR-001-015]
- [ ] CHK003 Are audit minimization, retention, export exclusion, and failure-visibility requirements mutually consistent across platform and operations artifacts? [Consistency, Spec §FR-001-006, §FR-001-018]
- [ ] CHK004 Are scheduler ownership, overlap prevention, synchronous notification, and no-permanent-worker constraints stated for every scheduled operation? [Coverage, Spec §FR-001-006]
- [ ] CHK005 Are release-artifact provenance, checksum, exact-commit, and rollback-evidence criteria objectively measurable? [Measurability, Spec §FR-001-020–FR-001-021]
