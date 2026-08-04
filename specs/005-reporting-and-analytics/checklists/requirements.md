# Checklist — Requirements

Feature: `005-reporting-and-analytics`

Purpose: requirement-quality gate for datasets, economic outputs, versions, and scenarios
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

## Reporting/budget requirement quality

- [x] CHK001 Is one current tenant/year dataset contract defined for Budget, dashboard, reports, print, CSV, XLSX, and current snapshot capture? [Consistency, Spec §FR-005-001–FR-005-003, §FR-005-020–FR-005-025]
- [x] CHK002 Are scope, filter, grouping, ordering, detail mode, empty-state, and unavailable-dimension semantics explicit without silent widening or invented zeroes? [Clarity, Spec §FR-005-010–FR-005-012, §FR-005-020–FR-005-025]
- [x] CHK003 Are Actual-primary, Estimate/Quote components, Plafond, Extra, project buckets, and reconciliation formulas complete and non-duplicative? [Completeness, Spec §FR-005-013–FR-005-016, §FR-005-070]
- [x] CHK004 Are manual, captured, draft, published, duplicate, reference, and comparison requirements measurable for BudgetVersion? [Measurability, Spec §FR-005-040–FR-005-047]
- [x] CHK005 Are persistent shared scenarios explicitly non-official, tenant/year-bound, permission-controlled, and excluded from current datasets? [Consistency, Spec §FR-005-030; INV-SCN-001]
- [x] CHK006 Are output size, memory, query-count, p95, format parity, tenant branding, and cross-tenant denial criteria objectively bounded? [Non-Functional, Spec §FR-005-020, §FR-005-060, §FR-005-070]
- [x] CHK007 Are CSV, XLSX and print specified as uncapped/non-truncating while CSV/XLSX remain incremental and benchmark evidence cannot become a runtime row threshold? [Consistency, Spec §FR-005-026]
- [x] CHK008 Are private temporary-artifact creation, finalize-before-download, complete-or-error behavior, cleanup failure, preserved filters and stable guidance specified for every export failure? [Coverage, Spec §FR-005-027]
- [x] CHK009 Are the deterministic 10,000-row fixture, exact p95/export/print times, 128 MiB ceiling and five-query ceiling objectively measurable without changing semantics? [Measurability, Spec §NFR-005-PERF-01]
- [x] CHK010 Is target-hosting absolute-time authority distinguished from CI's blocking parity/scope/order/memory/query gates and non-blocking timing record? [Clarity, Spec §AC-005-17, §NFR-005-PERF-01]
