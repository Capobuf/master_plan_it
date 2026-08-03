# Feature 003 — Expense domain

Status: PARTIALLY READY — Q-001 through Q-015 propagated; Q-018 and Q-024 remain open  
Logical owner: product owner with domain approval  
Actor: budget editor  
Dependencies: feature 002

## Problem

The Frappe implementation contains verified behavior but couples schema, controller lifecycle, report queries and framework UI. A coding agent cannot safely replatform it from generic requirements without stable invariants, files and tests.

## Objective

A user can create auditable expenses and rows with exact VAT, funding, replacement and allocation semantics while preserving the traced legacy behavior and the target operational constraints.

## Out of scope

- Rewriting unrelated legacy behavior.
- SPA or public API.
- Separate tenant databases, custom tenant domains, impersonation, and cross-tenant economic analytics are out of scope; tenant isolation itself is mandatory under Feature 007.
- Untraced formula changes.
- Background workers or real-time notifications.

## Actors

| Actor | Scope | Constraint |
|---|---|---|
| Administrator | global platform operations and all approved operations inside an explicitly selected tenant | keeps Administrator identity; cannot bypass domain invariants or impersonate tenant users |
| Editor | approved operations within exactly one assigned tenant | no user/global administration, import, migration, backup, restore, or cross-tenant access |
| Viewer | complete read, print, and export access within exactly one assigned tenant | no writes, global operations, or cross-tenant access |

## User stories

### US-003-01 — expense register
Priority: P1  
Value: enables create auditable expenses and rows with exact VAT, funding, replacement and allocation semantics.  
Acceptance: AC-003-01; requirements: FR-003-001, FR-003-002.
### US-003-02 — expense editor
Priority: P2  
Value: enables create auditable expenses and rows with exact VAT, funding, replacement and allocation semantics.  
Acceptance: AC-003-02; requirements: FR-003-002, FR-003-010.
### US-003-03 — expense audit drawer
Priority: P2  
Value: enables create auditable expenses and rows with exact VAT, funding, replacement and allocation semantics.  
Acceptance: AC-003-03; requirements: FR-003-010, FR-003-011.

## Acceptance scenarios

### AC-003-01 — Main path
Given an authenticated and authorized actor and valid prerequisite records  
When the actor completes the primary expense domain operation  
Then the server persists the result in one transaction  
And the response reflects server-calculated values  
And an unauthorized actor receives 403 without mutation.

### AC-003-02 — Validation and rollback
Given invalid or conflicting input  
When the operation is submitted  
Then validation/domain errors identify the affected field or invariant  
And the transaction is rolled back  
And no partial side effect remains.

### AC-003-03 — Empty and legacy-anomaly state
Given no matching records or a quarantined legacy anomaly  
When the screen/query is opened  
Then an explicit empty/error state is rendered  
And no value is silently invented.

### AC-003-04 — Concurrency
Given two writers loaded the same record version  
When the first saves and the second submits stale data  
Then the second receives a concurrency conflict  
And can reload current data before retrying.

### AC-003-05 — Tenant-owned expense workflow

Given an Editor and Viewer in tenant A plus records in tenant B, when tenant A expenses are created, updated, printed, exported, audited, or accessed through attachments, then only tenant A data is used; Editor receives Q-006 operations, Viewer remains read-only, and recorded Actual/history invariants remain enforced.

## Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| FR-003-001 | Expense kind shall be Ordinary or Plafond. | AC-001 |
| FR-003-002 | Expense requires year, cost center, title and at least one row. | AC-002 |
| FR-003-010 | Ordinary rows require vendor and Estimate, Quote or Actual phase. | AC-010 |
| FR-003-011 | An Ordinary expense cannot be both Extra and funded by Plafond. | AC-011 |
| FR-003-012 | A referenced Plafond shall exist in the same year; its cost center may differ. | AC-012 |
| FR-003-020 | Only Active rows contribute to totals. | AC-020 |
| FR-003-031 | Actual rows cannot be replacement targets. | AC-031 |
| FR-003-032 | Replacement links cannot self-reference or form cycles. | AC-032 |
| FR-003-040 | Non-zero unit price derives amount as quantity multiplied by unit price; otherwise amount is manual. | AC-040 |
| FR-003-041 | Non-zero amount requires row VAT rate or configured default. | AC-041 |
| FR-003-050 | A row uses spend date or complete period dates plus distribution, never both. | AC-050 |
| FR-003-051 | Distribution shall be all, start or end. | AC-051 |
| FR-003-052 | Estimate and Quote net amounts cannot be negative; Actual may be negative. | AC-052 |
| FR-003-060 | Register, detail, print and export shall use server-calculated values. | AC-060 |

| FR-003-061 | Expenses, rows, plafond references, attachments, audit, register, print, and export shall be scoped to one tenant. Editor may perform Q-006 operations; Viewer remains read/print/export only. | AC-003-05 |
## Non-functional requirements

| ID | Measure | Threshold and verification |
|---|---|---|
| NFR-003-PERF-01 | Primary register on 10,000 rows | server response p95 ≤ 800 ms on documented reference environment, measured with seeded feature test profile |
| NFR-003-A11Y-01 | Keyboard/accessibility | all controls keyboard reachable; labels/errors programmatically associated; axe smoke has no critical violations |
| NFR-003-SEC-01 | Authorization | 100% mapped write/read routes have allow and deny tests |
| NFR-003-INT-01 | Integrity | every documented write is transactional and rollback-tested |
| NFR-003-LOG-01 | Logging | domain conflicts are user-safe; unexpected errors include correlation ID and no sensitive payload |

## Business invariants

| ID | Rule | Error | Test |
|---|---|---|---|
| INV-EXP-001 | Active rows are sole total source. | DomainConflict | TEST-003-001 |
| INV-EXP-002 | Expense kind domain is closed. | DomainConflict | TEST-003-002 |
| INV-EXP-003 | At most one project/contract context once feature 004 is installed. | DomainConflict | TEST-003-003 |
| INV-PLF-001 | Extra and Plafond funding are mutually exclusive. | DomainConflict | TEST-003-001 |
| INV-PLF-002 | Plafond reference shares year. | DomainConflict | TEST-003-002 |
| INV-ROW-001 | Actual is immutable as replacement target. | DomainConflict | TEST-003-001 |
| INV-ROW-002 | Replacement graph is acyclic. | DomainConflict | TEST-003-002 |
| INV-AMT-001 | Money arithmetic is decimal. | DomainConflict | TEST-003-001 |
| INV-VAT-001 | VAT split net+vat=gross at two decimals. | DomainConflict | TEST-003-001 |
| INV-DATE-001 | Date modes are exclusive. | DomainConflict | TEST-003-001 |
| INV-DIST-001 | Monthly allocated sum equals row net. | DomainConflict | TEST-003-001 |

| INV-TEN-003 | An expense and every referenced year, cost center, vendor, plafond, project, contract, row, attachment, and audit event share the same tenant. | DomainConflict | TEST-003-061 |

## Clarifications

### Resolved from repository

The feature preserves the verified rules listed in `docs/replatform/source-traceability.md` and `current-state.md`.

### Approved product decisions

Q-005, Q-006, Q-009, and Q-010 define Viewer access, Editor economic operations, utilities, and ownership. Feature 007 and `docs/replatform/approved-decisions.md` are normative for tenant scope.

### Proposed target

Optimistic concurrency uses an integer `lock_version`; updates include the expected version and increment it atomically.

### Unresolved

The open items in `docs/replatform/product-clarification-register.md` and `docs/replatform/open-questions.md` remain unresolved; none may be resolved implicitly by the coding agent.
