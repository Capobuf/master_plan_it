# Checklist — Migration Integrity

Feature: `006-data-migration-and-operations`

Purpose: requirement-quality gate for migration, reconciliation, and portability integrity
Created: 2026-08-04
Audience/depth: PR reviewer / formal migration-readiness gate

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

## Migration-integrity requirement quality

- [x] CHK001 Are package version, dataset order, schema, encoding, decimal/date normalization, counts, checksums, and attachment references fully specified? [Completeness, Spec §FR-006-001–FR-006-003]
- [x] CHK002 Are immutable tenant target, legacy identity tuple, collision, duplicate, idempotency, and resume semantics unambiguous? [Clarity, Spec §FR-006-002, §FR-006-005–FR-006-009]
- [x] CHK003 Are dry-run, quarantine, explicit exclusion, apply, partial failure, and rollback requirements consistent with no silent fallback? [Consistency, Spec §FR-006-004–FR-006-009]
- [x] CHK004 Are current/history, mutable Actual, replacement-state evidence, BudgetVersion, scenario, generation-exception, and attachment mappings complete? [Coverage, Spec §FR-006-013]
- [x] CHK005 Are source/target counts, exact economic totals, exclusions, anomalies, and sign-off thresholds objectively measurable? [Measurability, Spec §FR-006-007, §FR-006-019; INV-MIG-003]
- [x] CHK006 Are portability inclusions/exclusions, protected abilities, audit/credential exclusions, checksums, and round-trip semantics separately defined? [Completeness, Spec §FR-006-015–FR-006-016]
- [x] CHK007 Are calendar-year identity derivation and non-calendar boundary quarantine/rejection defined without silent normalization? [Clarity, Spec §FR-006-020]
- [x] CHK008 Are legacy versus portability attachment-evidence rules distinguished, including exact validation, quota, manifests, immutable payload versions and prohibition on fabricated history? [Consistency, Spec §FR-006-021]
- [x] CHK009 Are tenant operational-setting conflicts, structured terminal source-deletion provenance, owning-Action apply semantics and the prohibition on reactivating/restoring the same deleted project/contract/term logical identity complete and non-overwriting? [Coverage, Spec §FR-006-022]
- [x] CHK010 Can reconciliation objectively prove current/historical attachment payload/manifests/checksums, quota result, source keys and deletion provenance? [Measurability, Spec §INV-MIG-007]
