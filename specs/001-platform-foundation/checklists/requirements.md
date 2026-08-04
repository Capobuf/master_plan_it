# Checklist — Requirements

Feature: `001-platform-foundation`

Purpose: requirement-quality gate for platform identity, audit, scheduling, and release requirements
Created: 2026-08-04
Audience/depth: PR reviewer / formal implementation-readiness gate

## Preserved cross-feature baseline

- [x] Every statement is labelled VERIFIED CURRENT, PROPOSED TARGET or another permitted evidence label where current/target could be confused.
- [ ] Every FR is atomic, has an acceptance scenario and maps to at least one test and task. (OPEN: FR-001-008 and FR-001-019 aggregate multiple independently verifiable operations.)
- [x] Every invariant has a stable ID, error behavior, transaction boundary and focused test.
- [x] Every write route/action has allow and deny authorization tests.
- [x] Money never uses authoritative floats; rounding points and allocation residual are tested.
- [x] Foreign keys, indexes, nullability, delete behavior and migration order are explicit.
- [x] Empty, validation, authorization, concurrency, duplicate/idempotency and legacy-anomaly states are covered.
- [x] UI specifies keyboard, focus, loading, error, empty and responsive behavior.
- [x] Shared-hosting operation does not require Node runtime, Redis, WebSockets or permanent workers.
- [x] No task asks the coding agent to choose architecture, packages, names or paths.
- [x] Quickstart contains future commands only and does not claim execution.
- [ ] Documentation analysis has no unresolved CRITICAL/HIGH finding. (OPEN: backup cadence/monitoring command and the aggregated requirements above.)
- [x] Every entity/query/screen in this feature has explicit tenant ownership or is documented as global.
- [x] Same-tenant allow and other-tenant deny paths are testable.
- [x] Reports, exports, attachments, direct links, commands, and scheduled work fail closed without valid tenant context.

## Platform-specific requirement quality

- [x] CHK001 Are authentication, logout, password recovery, session invalidation, and inactive-user denial requirements separately defined with measurable outcomes? [Completeness, Spec §FR-001-001–FR-001-002, §FR-001-012, §FR-001-014–FR-001-016]
- [x] CHK002 Are global Administrator operations distinguished from tenant-context operations without implying impersonation or a tenant fallback? [Clarity, Spec §FR-001-008, §FR-001-015]
- [x] CHK003 Are audit minimization, retention, export exclusion, and failure-visibility requirements mutually consistent across platform and operations artifacts? [Consistency, Spec §FR-001-017–FR-001-018, §FR-001-020–FR-001-021]
- [x] CHK004 Are scheduler ownership, overlap prevention, synchronous notification, and no-permanent-worker constraints stated for every scheduled operation? [Coverage, Spec §FR-001-006]
- [x] CHK005 Are release-artifact provenance, checksum, exact-commit, and rollback-evidence criteria objectively measurable? [Measurability, Spec §FR-001-005; AC-001-08; Hosting contract]
- [x] CHK006 Are the audit-retention default, inclusive 1–120-month range, Administrator authority, generic lowering warning, and absence of cutoff/count preview stated consistently? [Clarity, Spec §FR-001-019–FR-001-021; AC-001-07, AC-001-09]
- [x] CHK007 Are ordinary logout current-session invalidation and password change/reset all-session invalidation distinguished without an unspecified alternative? [Consistency, Spec §FR-001-001, §FR-001-015–FR-001-016; AC-001-01, AC-001-05]
