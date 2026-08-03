# Feature 002 — Master data

Status: PARTIALLY READY — Q-001 through Q-015 propagated; Q-030 and Q-031 remain open  
Logical owner: product owner with domain approval  
Actor: authorized business user  
Dependencies: feature 001

## Problem

The Frappe implementation contains verified behavior but couples schema, controller lifecycle, report queries and framework UI. A coding agent cannot safely replatform it from generic requirements without stable invariants, files and tests.

## Objective

A user can manage planning years, hierarchical cost centers and vendors while preserving the traced legacy behavior and the target operational constraints.

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

### US-002-01 — year register/editor
Priority: P1  
Value: enables manage planning years, hierarchical cost centers and vendors.  
Acceptance: AC-002-01; requirements: FR-002-001, FR-002-002.
### US-002-02 — cost-center tree/editor
Priority: P2  
Value: enables manage planning years, hierarchical cost centers and vendors.  
Acceptance: AC-002-02; requirements: FR-002-002, FR-002-003.
### US-002-03 — vendor register/editor
Priority: P2  
Value: enables manage planning years, hierarchical cost centers and vendors.  
Acceptance: AC-002-03; requirements: FR-002-003, FR-002-004.

## Acceptance scenarios

### AC-002-01 — Main path
Given an authenticated and authorized actor and valid prerequisite records  
When the actor completes the primary master data operation  
Then the server persists the result in one transaction  
And the response reflects server-calculated values  
And an unauthorized actor receives 403 without mutation.

### AC-002-02 — Validation and rollback
Given invalid or conflicting input  
When the operation is submitted  
Then validation/domain errors identify the affected field or invariant  
And the transaction is rolled back  
And no partial side effect remains.

### AC-002-03 — Empty and legacy-anomaly state
Given no matching records or a quarantined legacy anomaly  
When the screen/query is opened  
Then an explicit empty/error state is rendered  
And no value is silently invented.

### AC-002-04 — Concurrency
Given two writers loaded the same record version  
When the first saves and the second submits stale data  
Then the second receives a concurrency conflict  
And can reload current data before retrying.

### AC-002-05 — Tenant-owned master data

Given tenants A and B, when an Editor of tenant A manages vendors or cost centers and reads years, then only tenant A data and same-tenant parents are available; financial-year writes are denied; a reference to tenant B is rejected without leakage.

## Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| FR-002-001 | A year shall have unique numeric identifier, start date, end date and active flag. | AC-001 |
| FR-002-002 | Year periods shall not overlap. | AC-002 |
| FR-002-003 | A cost center shall have a unique name and optional parent. | AC-003 |
| FR-002-004 | Cost-center parent assignment shall not create a cycle. | AC-004 |
| FR-002-005 | Group summaries shall include descendants; leaf summaries only the leaf. | AC-005 |
| FR-002-006 | A vendor shall have unique name, optional VAT ID/contact values and active flag. | AC-006 |
| FR-002-007 | Inactive vendors remain readable for historical rows but are excluded from new selection. | AC-007 |
| FR-002-008 | Editor may create and update vendors and cost centers in the assigned tenant; financial-year creation and configuration are Administrator-only. | AC-008 |

| FR-002-009 | Years, cost centers, and vendors shall belong to one tenant and all names, trees, selections, imports, and exports shall be scoped to that tenant. | AC-002-05 |
| FR-002-010 | Editor may create and update vendors and cost centers in the same tenant; only Administrator may create or configure financial years. | AC-002-05 |
## Non-functional requirements

| ID | Measure | Threshold and verification |
|---|---|---|
| NFR-002-PERF-01 | Primary register on 10,000 rows | server response p95 ≤ 800 ms on documented reference environment, measured with seeded feature test profile |
| NFR-002-A11Y-01 | Keyboard/accessibility | all controls keyboard reachable; labels/errors programmatically associated; axe smoke has no critical violations |
| NFR-002-SEC-01 | Authorization | 100% mapped write/read routes have allow and deny tests |
| NFR-002-INT-01 | Integrity | every documented write is transactional and rollback-tested |
| NFR-002-LOG-01 | Logging | domain conflicts are user-safe; unexpected errors include correlation ID and no sensitive payload |

## Business invariants

| ID | Rule | Error | Test |
|---|---|---|---|
| INV-YEAR-001 | Year start is not after end. | DomainConflict | TEST-002-001 |
| INV-YEAR-002 | Year ranges do not overlap. | DomainConflict | TEST-002-002 |
| INV-CC-001 | Cost-center graph is acyclic. | DomainConflict | TEST-002-001 |
| INV-VEN-001 | Historical vendor references survive deactivation. | DomainConflict | TEST-002-001 |

| INV-TEN-002 | A master-data record cannot reference a parent or tenant-owned resource from another tenant. | DomainConflict | TEST-002-009 |

## Clarifications

### Resolved from repository

The feature preserves the verified rules listed in `docs/replatform/source-traceability.md` and `current-state.md`.

### Approved product decisions

Q-007 and Q-010 define Editor master-data powers and tenant ownership. Feature 007 and `docs/replatform/approved-decisions.md` are normative for tenant scope.

### Proposed target

Optimistic concurrency uses an integer `lock_version`; updates include the expected version and increment it atomically.

### Unresolved

Questions Q-016 onward in `docs/replatform/product-clarification-register.md` and the remaining items in `docs/replatform/open-questions.md`; none may be resolved implicitly by the coding agent.
