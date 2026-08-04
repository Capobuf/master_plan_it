# Checklist — Ux Accessibility

Feature: `005-reporting-and-analytics`

Purpose: requirement-quality gate for reporting UX, accessibility, print, and export surfaces
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

## Reporting UX requirement quality

- [ ] CHK001 Are filter defaults, applied-filter visibility, reset behavior, result scope, and export-scope confirmation requirements explicit? [Completeness, Spec §FR-005-020–FR-005-025]
- [ ] CHK002 Are missing-data, zero-denominator, empty-budget, incompatible-source, partial-dimension, and oversized-output states specified distinctly? [Coverage, Edge Cases, Spec §FR-005-020–FR-005-025, §FR-005-047]
- [ ] CHK003 Are chart values, labels, legends, accessible table fallback, lifecycle, and no-client-arithmetic requirements consistent? [Consistency, Spec §FR-005-003]
- [ ] CHK004 Are print, CSV, and XLSX ordering, locale, decimal, date, metadata, branding, and safe-filename requirements measurable? [Measurability, Spec §FR-005-021–FR-005-025, §FR-005-060]
- [ ] CHK005 Are keyboard, focus, responsive table, comparison-source identity, and error-focus requirements documented for every reporting surface? [Coverage, Gap, NFR-005-UX-01]
