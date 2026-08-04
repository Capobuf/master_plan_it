# Checklist — Requirements

Feature: `005-reporting-and-analytics`

Purpose: requirement-quality gate for datasets, economic outputs, versions, and scenarios
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

## Reporting/budget requirement quality

- [ ] CHK001 Is one current tenant/year dataset contract defined for Budget, dashboard, reports, print, CSV, XLSX, and current snapshot capture? [Consistency, Spec §FR-005-001–FR-005-003, §FR-005-020–FR-005-025]
- [ ] CHK002 Are scope, filter, grouping, ordering, detail mode, empty-state, and unavailable-dimension semantics explicit without silent widening or invented zeroes? [Clarity, Spec §FR-005-010–FR-005-012, §FR-005-020–FR-005-025]
- [ ] CHK003 Are Actual-primary, Estimate/Quote components, Plafond, Extra, project buckets, and reconciliation formulas complete and non-duplicative? [Completeness, Spec §FR-005-013–FR-005-016, §FR-005-070]
- [ ] CHK004 Are manual, captured, draft, published, duplicate, reference, and comparison requirements measurable for BudgetVersion? [Measurability, Spec §FR-005-040–FR-005-047]
- [ ] CHK005 Are persistent shared scenarios explicitly non-official, tenant/year-bound, permission-controlled, and excluded from current datasets? [Consistency, Spec §FR-005-030; INV-SCN-001]
- [ ] CHK006 Are output size, memory, query-count, p95, format parity, tenant branding, and cross-tenant denial criteria objectively bounded? [Non-Functional, Spec §FR-005-020, §FR-005-060, §FR-005-070]
