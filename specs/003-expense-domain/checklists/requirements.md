# Checklist — Requirements

Feature: `003-expense-domain`

Purpose: requirement-quality gate for Expense lifecycle, revision, authorization, and attachments
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

## Expense-lifecycle requirement quality

- [x] CHK001 Are current Expense identity, row identity, explicit row deletion, and last-row behavior defined independently from revision snapshots? [Completeness, Spec §FR-003-001–FR-003-002, §FR-003-035]
- [x] CHK002 Are create, update, delete, confirm, restore, history, attachment, output, and import abilities defined without hard-coded role-name authorization? [Clarity, Spec §FR-003-030–FR-003-035; Authorization contract]
- [x] CHK003 Are mutable current Actual rows consistently separated from immutable revision and audit evidence across spec, data model, contracts, and tasks? [Consistency, Spec §FR-003-031–FR-003-034]
- [x] CHK004 Are stale writes, invalid references, mixed-tenant rows, partial aggregate failure, and restoration revalidation covered as atomic exception/recovery scenarios? [Coverage, Spec §FR-003-030, §FR-003-063]
- [x] CHK005 Are private attachment metadata, parent authorization, size/MIME/checksum, deletion compensation, and payload exclusion requirements complete? [Completeness, Spec §FR-003-061–FR-003-062]
- [x] CHK006 Are the exact extension/detected-MIME pairs, nonempty rule and 10,485,760-byte maximum stated without relying on a client declaration? [Clarity, Spec §FR-003-064]
- [x] CHK007 Is exactly-one Expense-or-ExpenseRow parent ownership consistent across schema, authorization, revisions and UI requirements? [Consistency, Spec §FR-003-065]
- [x] CHK008 Are complete attachment manifests, immutable payload-version reuse and atomic exact data-plus-file restore requirements defined for unchanged, missing, corrupt, stale and over-quota recovery states? [Coverage, Spec §FR-003-066–FR-003-067, §INV-ATT-007]
- [x] CHK009 Are current/historical payload retention and permanent Expense purge/non-restorability boundaries unambiguous for attachment, row and Expense deletion? [Edge Case, Spec §FR-003-068]
- [x] CHK010 Are default quota, zero-byte behavior, absence of an application maximum, exact non-float arithmetic, authority, counted bytes, concurrency, lowering-below-use behavior and atomic upload/restore denial objectively specified? [Measurability, Spec §FR-003-069]
