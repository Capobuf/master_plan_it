# Checklist — Requirements

Feature: `002-master-data`

Purpose: requirement-quality gate for planning-year, cost-center, and vendor behavior
Created: 2026-08-04
Audience/depth: PR reviewer / formal implementation-readiness gate

## Preserved cross-feature baseline

- [x] Every statement is labelled VERIFIED CURRENT, PROPOSED TARGET or another permitted evidence label where current/target could be confused.
- [x] Every FR is atomic, has an acceptance scenario and maps to at least one test and task.
- [x] Every invariant has a stable ID, error behavior, transaction boundary and focused test.
- [x] Every write route/action has allow and deny authorization tests.
- [x] Money never uses authoritative floats; rounding points and allocation residual are tested.
- [x] Foreign keys, indexes, nullability, delete behavior and migration order are explicit.
- [x] Empty, validation, authorization, concurrency, duplicate/idempotency and legacy-anomaly states are covered.
- [x] UI specifies keyboard, focus, loading, error, empty and responsive behavior.
- [x] Shared-hosting operation does not require Node runtime, Redis, WebSockets or permanent workers.
- [x] No task asks the coding agent to choose architecture, packages, names or paths.
- [x] Quickstart contains future commands only and does not claim execution.
- [x] Documentation analysis has no unresolved CRITICAL/HIGH finding.
- [x] Every entity/query/screen in this feature has explicit tenant ownership or is documented as global.
- [x] Same-tenant allow and other-tenant deny paths are testable.
- [x] Reports, exports, attachments, direct links, commands, and scheduled work fail closed without valid tenant context.

## Master-data-specific requirement quality

- [x] CHK001 Are planning-year identity, active-state, uniqueness, and selection semantics defined without an implicit default tenant or year? [Completeness, Spec §FR-002-001–FR-002-003]
- [x] CHK002 Are cost-center tree depth, parent validity, cycle prevention, ordering, and inactive-node behavior unambiguous? [Clarity, Spec §FR-002-004–FR-002-007]
- [x] CHK003 Are vendor deactivate/reactivate, current-reference, selector, and revision requirements consistent with Q-030 and Q-031? [Consistency, Spec §FR-002-008–FR-002-011]
- [x] CHK004 Are stale-write, duplicate-code, referenced-record, restoration, and other-tenant scenarios represented by measurable acceptance criteria? [Coverage, Exception/Recovery, Spec §FR-002-012–FR-002-013]
- [x] CHK005 Is every master-data write requirement mapped to an exact ability while reads and selectors fail closed for inactive or foreign records? [Traceability, Authorization contract]
- [x] CHK006 Are planning years specified as immutable calendar identities with derived January 1/December 31 boundaries and only create/deactivate/reactivate operations? [Clarity, Spec §FR-002-001–FR-002-002, §FR-002-013–FR-002-014]
- [x] CHK007 Are non-calendar import rows explicitly rejected/quarantined without silent normalization? [Exception Flow, Spec §FR-002-018]
- [x] CHK008 Are root-as-level-one, maximum depth three, cycle handling and case-insensitive name-then-ID sibling order complete and mutually consistent? [Consistency, Spec §FR-002-003–FR-002-005, §FR-002-015–FR-002-016]
- [x] CHK009 Are permanent CostCenter/Vendor deletion eligibility, historical/current reference checks, descendant checks, exact abilities and audit requirements specified independently from deactivation? [Completeness, Spec §FR-002-017]
