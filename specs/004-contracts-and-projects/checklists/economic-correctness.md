# Checklist — Economic Correctness

Feature: `004-contracts-and-projects`

Purpose: requirement-quality gate for project buckets and contract occurrence economics
Created: 2026-08-04
Audience/depth: PR reviewer / formal economic-readiness gate

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

## Contract/project economic requirement quality

- [ ] CHK001 Are project-stage bucket effects defined for every stage without assigning an authoritative monetary total to Project? [Completeness, Spec §FR-004-010–FR-004-012; INV-PRJ-001–INV-PRJ-004]
- [ ] CHK002 Are contract term amount, VAT, billing cycle, derived end, overlap, and renewal-lineage requirements exact and mutually consistent? [Clarity, Spec §FR-004-020–FR-004-023]
- [ ] CHK003 Are canonical occurrence-key components and uniqueness requirements sufficient to distinguish legitimate occurrences without inventing cost identity? [Measurability, Spec §FR-004-025–FR-004-026; INV-CON-002]
- [ ] CHK004 Are generated-row create/update/suppress/resume/delete/regenerate outcomes defined for expected, user-authoritative, and exception states? [Coverage, Spec §FR-004-027–FR-004-036]
- [ ] CHK005 Are generation rules consistent with one shared Expense Action boundary and the prohibition on duplicated economic formulas? [Consistency, Generation-sync contract; INV-CON-003–INV-CON-008]
