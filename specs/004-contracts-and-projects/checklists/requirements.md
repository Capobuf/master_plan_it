# Checklist — Requirements

Feature: `004-contracts-and-projects`

Purpose: requirement-quality gate for project, contract, term, generation, and revision lifecycles
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

## Contract/project lifecycle requirement quality

- [x] CHK001 Are project create/update/stage/irreversible-delete/current-only-restore and deferred-promotion requirements independently defined with concurrency behavior? [Completeness, Spec §FR-004-001, §FR-004-010–FR-004-012, §FR-004-015, §INV-DEL-003]
- [x] CHK002 Are complete-term-set updates and explicit term deletion distinguished from omitted form input? [Clarity, Spec §FR-004-020–FR-004-023]
- [x] CHK003 Are current-only contract/project revision restore requirements consistent with irreversible source deletion, immutable prior evidence and unchanged linked Actual rows? [Consistency, Spec §FR-004-037–FR-004-038, §INV-DEL-003]
- [x] CHK004 Are idempotent synchronization, partial failure rollback, suppression persistence, and notification exception scenarios covered? [Coverage, Exception/Recovery, Spec §FR-004-025–FR-004-039]
- [x] CHK005 Are authorization and tenant-boundary requirements stated for screens, commands, notifications, generation, revisions, and direct identifiers? [Traceability, Authorization contract]
- [x] CHK006 Is project deletion explicitly blocked by any current linked Expense and separated from the prior Feature 003 Expense-deletion flow with no cascade, detach or reassignment? [Clarity, Spec §FR-004-040]
- [x] CHK007 Are irreversible contract and contract-term deletion outcomes complete for retained current generated Expenses, user-authoritative state, unchanged source keys, immutable source/deletion provenance, stopped future generation and denied restoration of the same logical identity? [Completeness, Spec §FR-004-041, §FR-004-044]
- [x] CHK008 Are the always-present prompt, optional-by-default tenant setting, protected global-Administrator-only ability, explicit target tenant, prospective-only effect, trimming and 500-character limit mutually consistent? [Consistency, Spec §FR-004-042–FR-004-043]
- [x] CHK009 Are atomic rollback requirements defined when source deletion cannot update every linked Expense provenance record? [Recovery, Spec §FR-004-041, §FR-004-044]
