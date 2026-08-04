# Checklist — Requirements

Feature: `006-data-migration-and-operations`

Purpose: requirement-quality gate for backup, restore, deployment, notification, and cutover requirements
Created: 2026-08-04
Audience/depth: PR reviewer / formal operational-readiness gate

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

## Operations/release requirement quality

- [x] CHK001 Are backup states, archive contents/exclusions, manifest/checksum, source commit, and recoverability evidence explicitly defined? [Completeness, Spec §FR-006-010–FR-006-011, §FR-006-014, §FR-006-017]
- [x] CHK002 Is Created versus Verified backup status unambiguous, including the empty-environment restore rehearsal required for Verified? [Clarity, Backup/restore contract]
- [x] CHK003 Are preflight, immutable artifact, forward migration, activation, health check, rollback record, and failure-stage requirements consistent across deployment artifacts? [Consistency, Spec §FR-006-012]
- [x] CHK004 Are operation-failure recipient selection, deduplication, safe payload, database visibility, optional synchronous mail, and mail-failure behavior complete? [Coverage, Spec §FR-006-018]
- [x] CHK005 Are every cutover evidence field and the prohibition on `CUTOVER READY` with open evidence objectively specified? [Measurability, Spec §FR-006-019]
- [x] CHK006 Are host secret, permission, path, quota, unavailable-runner, and external dependency failures classified without weakening commands or checks? [Gap, Exception Flow, Deployment contract]
