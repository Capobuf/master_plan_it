# Checklist — Shared Hosting

Feature: `001-platform-foundation`

Purpose: requirement-quality gate for shared-hosting and immutable-release constraints
Created: 2026-08-04
Audience/depth: PR reviewer / formal operational-readiness gate

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

## Shared-hosting requirement quality

- [ ] CHK001 Are PHP, extension, MySQL, document-root, writable-path, cron, and command-access prerequisites explicitly bounded? [Completeness, Spec §FR-001-020; Hosting contract]
- [ ] CHK002 Is the distinction between build-time Node usage and the prohibited production Node runtime unambiguous? [Clarity, Plan §Runtime]
- [ ] CHK003 Are immutable artifact creation, transfer, checksum validation, activation, and health-check requirements consistently defined with Feature 006 deployment? [Consistency, Spec §FR-001-021; Dependency, Feature 006]
- [ ] CHK004 Are failed preflight, failed migration, failed activation, and post-activation health failure requirements documented without destructive database rollback claims? [Coverage, Exception/Recovery, Hosting contract]
- [ ] CHK005 Are unknown provider capabilities explicitly retained as cutover evidence rather than treated as implementation defaults? [Assumption, Gap, Spec §FR-001-020]
