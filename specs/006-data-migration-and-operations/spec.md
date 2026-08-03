# Feature 006 — Data migration and operations

Status: PARTIALLY READY; NOT CUTOVER READY — Q-001 through Q-015 propagated; Q-021, Q-022, and Q-029 remain open  
Logical owner: product owner with domain approval  
Actor: deployment operator  
Dependencies: feature 005

## Problem

The Frappe implementation contains verified behavior but couples schema, controller lifecycle, report queries and framework UI. A coding agent cannot safely replatform it from generic requirements without stable invariants, files and tests.

## Objective

A user can import legacy data repeatably, reconcile it, deploy, back up and restore on shared hosting or minimal Docker while preserving the traced legacy behavior and the target operational constraints.

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

### US-006-01 — migration run status
Priority: P1  
Value: enables import legacy data repeatably, reconcile it, deploy, back up and restore on shared hosting or minimal Docker.  
Acceptance: AC-006-01; requirements: FR-006-001, FR-006-002.
### US-006-02 — reconciliation report
Priority: P2  
Value: enables import legacy data repeatably, reconcile it, deploy, back up and restore on shared hosting or minimal Docker.  
Acceptance: AC-006-02; requirements: FR-006-002, FR-006-003.
### US-006-03 — backup/restore status
Priority: P2  
Value: enables import legacy data repeatably, reconcile it, deploy, back up and restore on shared hosting or minimal Docker.  
Acceptance: AC-006-03; requirements: FR-006-003, FR-006-004.

## Acceptance scenarios

### AC-006-01 — Main path
Given an authenticated and authorized actor and valid prerequisite records  
When the actor completes the primary data migration and operations operation  
Then the server persists the result in one transaction  
And the response reflects server-calculated values  
And an unauthorized actor receives 403 without mutation.

### AC-006-02 — Validation and rollback
Given invalid or conflicting input  
When the operation is submitted  
Then validation/domain errors identify the affected field or invariant  
And the transaction is rolled back  
And no partial side effect remains.

### AC-006-03 — Empty and legacy-anomaly state
Given no matching records or a quarantined legacy anomaly  
When the screen/query is opened  
Then an explicit empty/error state is rendered  
And no value is silently invented.

### AC-006-04 — Concurrency
Given two writers loaded the same record version  
When the first saves and the second submits stale data  
Then the second receives a concurrency conflict  
And can reload current data before retrying.

### AC-006-05 — One-site migration into one tenant

Given the one active Frappe site and an Administrator-selected target tenant, when manual entry or CSV import runs, then staging, identity mapping, reconciliation, and applied records remain bound to that tenant; another tenant cannot be selected mid-run and no generalized multi-site workflow is created.

## Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| FR-006-001 | Migration shall consume versioned UTF-8 CSV files plus a manifest. | AC-001 |
| FR-006-002 | Every imported row shall retain DocType and legacy ID. | AC-002 |
| FR-006-003 | Raw records shall be staged before domain insertion. | AC-003 |
| FR-006-004 | Dry-run shall perform validation and reconciliation without target-domain writes. | AC-004 |
| FR-006-005 | Repeated import of the same manifest shall be idempotent. | AC-005 |
| FR-006-006 | Invalid rows shall be quarantined with machine-readable error code and source location. | AC-006 |
| FR-006-007 | Reconciliation shall compare source/target counts and net sums by year, cost center and phase. | AC-007 |
| FR-006-010 | Backup shall cover database, attachments and environment-independent configuration. | AC-010 |
| FR-006-011 | Restore shall be tested into an empty environment before cutover. | AC-011 |
| FR-006-012 | Shared-hosting deployment shall use precompiled assets and one cron entry. | AC-012 |

| FR-006-013 | The verified migration shall import one Frappe site into one explicitly selected tenant by controlled manual entry or CSV. Only Administrator may run it; every staged and mapped record shall retain target tenant ownership. | AC-006-05 |
## Non-functional requirements

| ID | Measure | Threshold and verification |
|---|---|---|
| NFR-006-PERF-01 | Primary register on 10,000 rows | server response p95 ≤ 800 ms on documented reference environment, measured with seeded feature test profile |
| NFR-006-A11Y-01 | Keyboard/accessibility | all controls keyboard reachable; labels/errors programmatically associated; axe smoke has no critical violations |
| NFR-006-SEC-01 | Authorization | 100% mapped write/read routes have allow and deny tests |
| NFR-006-INT-01 | Integrity | every documented write is transactional and rollback-tested |
| NFR-006-LOG-01 | Logging | domain conflicts are user-safe; unexpected errors include correlation ID and no sensitive payload |

## Business invariants

| ID | Rule | Error | Test |
|---|---|---|---|
| INV-MIG-001 | Legacy identity mapping is unique. | DomainConflict | TEST-006-001 |
| INV-MIG-002 | Same manifest cannot create duplicates. | DomainConflict | TEST-006-002 |
| INV-MIG-003 | Cutover requires signed zero-blocker reconciliation. | DomainConflict | TEST-006-003 |
| INV-OPS-001 | A backup is not valid until restore verification succeeds. | DomainConflict | TEST-006-001 |

| INV-MIG-004 | A migration run has one immutable target tenant and cannot map a source record outside it. | DomainConflict | TEST-006-013 |

## Clarifications

### Resolved from repository

The feature preserves the verified rules listed in `docs/replatform/source-traceability.md` and `current-state.md`.

### Approved product decisions

Q-009, Q-010, and Q-011 define Administrator-only operations, tenant ownership, and the one-site migration boundary. Feature 007 and `docs/replatform/approved-decisions.md` are normative for tenant scope.

### Proposed target

Optimistic concurrency uses an integer `lock_version`; updates include the expected version and increment it atomically.

### Unresolved

Questions Q-016 onward in `docs/replatform/product-clarification-register.md` and the remaining items in `docs/replatform/open-questions.md`; none may be resolved implicitly by the coding agent.
