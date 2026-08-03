# Feature 004 — Contracts and projects

Status: IMPLEMENTATION READY within documented scope  
Logical owner: product owner with domain approval  
Actor: vCIO planner  
Dependencies: feature 003

## Problem

The Frappe implementation contains verified behavior but couples schema, controller lifecycle, report queries and framework UI. A coding agent cannot safely replatform it from generic requirements without stable invariants, files and tests.

## Objective

A user can manage project decisions and date-versioned contracts that generate authoritative expense rows while preserving the traced legacy behavior and the target operational constraints.

## Out of scope

- Rewriting unrelated legacy behavior.
- SPA or public API.
- Multi-tenant data isolation unless OQ-003 is resolved.
- Untraced formula changes.
- Background workers or real-time notifications.

## Actors

| Role | Operations | Limits |
|---|---|---|
| Administrator | all feature operations | no bypass of domain invariants |
| Administrator | all normal business operations | cannot alter platform bootstrap secrets |
| Editor | read and permitted business writes from authorization contract | no settings/role administration |
| Viewer | read, print and permitted export | no writes |

## User stories

### US-004-01 — project register/editor
Priority: P1  
Value: enables manage project decisions and date-versioned contracts that generate authoritative expense rows.  
Acceptance: AC-004-01; requirements: FR-004-001, FR-004-010.
### US-004-02 — contract register/editor with term timeline
Priority: P2  
Value: enables manage project decisions and date-versioned contracts that generate authoritative expense rows.  
Acceptance: AC-004-02; requirements: FR-004-010, FR-004-015.
### US-004-03 — sync status
Priority: P2  
Value: enables manage project decisions and date-versioned contracts that generate authoritative expense rows.  
Acceptance: AC-004-03; requirements: FR-004-015, FR-004-020.

## Acceptance scenarios

### AC-004-01 — Main path
Given an authenticated and authorized actor and valid prerequisite records  
When the actor completes the primary contracts and projects operation  
Then the server persists the result in one transaction  
And the response reflects server-calculated values  
And an unauthorized actor receives 403 without mutation.

### AC-004-02 — Validation and rollback
Given invalid or conflicting input  
When the operation is submitted  
Then validation/domain errors identify the affected field or invariant  
And the transaction is rolled back  
And no partial side effect remains.

### AC-004-03 — Empty and legacy-anomaly state
Given no matching records or a quarantined legacy anomaly  
When the screen/query is opened  
Then an explicit empty/error state is rendered  
And no value is silently invented.

### AC-004-04 — Concurrency
Given two writers loaded the same record version  
When the first saves and the second submits stale data  
Then the second receives a concurrency conflict  
And can reload current data before retrying.

## Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| FR-004-001 | Project requires title, cost center and valid stage. | AC-001 |
| FR-004-010 | Project stage shall be Idea, Proposed, Approved, Deferred or Rejected. | AC-010 |
| FR-004-015 | Deferred requires target year and is promoted to Proposed when that year is current. | AC-015 |
| FR-004-020 | Contract requires vendor, cost center and at least one non-overlapping term. | AC-020 |
| FR-004-021 | Term billing cycle shall be Monthly or Annual. | AC-021 |
| FR-004-022 | A missing non-final term end shall resolve to day before next term. | AC-022 |
| FR-004-023 | Auto-renew creates at most one successor when year coverage exists and no overlap occurs. | AC-023 |
| FR-004-025 | Synchronization creates one missing generated Actual row per contract term/year source and never overwrites an existing generated row. | AC-025 |
| FR-004-026 | Generated source keys shall be unique and immutable. | AC-026 |
| FR-004-031 | An expense shall reference at most one project or contract. | AC-031 |
| FR-004-032 | Contracts and projects are not independent economic total sources. | AC-032 |

## Non-functional requirements

| ID | Measure | Threshold and verification |
|---|---|---|
| NFR-004-PERF-01 | Primary register on 10,000 rows | server response p95 ≤ 800 ms on documented reference environment, measured with seeded feature test profile |
| NFR-004-A11Y-01 | Keyboard/accessibility | all controls keyboard reachable; labels/errors programmatically associated; axe smoke has no critical violations |
| NFR-004-SEC-01 | Authorization | 100% mapped write/read routes have allow and deny tests |
| NFR-004-INT-01 | Integrity | every documented write is transactional and rollback-tested |
| NFR-004-LOG-01 | Logging | domain conflicts are user-safe; unexpected errors include correlation ID and no sensitive payload |

## Business invariants

| ID | Rule | Error | Test |
|---|---|---|---|
| INV-PRJ-001 | Project stage is closed enum. | DomainConflict | TEST-004-001 |
| INV-PRJ-002 | Deferred target exists. | DomainConflict | TEST-004-002 |
| INV-CON-001 | Terms do not overlap. | DomainConflict | TEST-004-001 |
| INV-CON-002 | Sync is idempotent and append-missing. | DomainConflict | TEST-004-002 |
| INV-CON-003 | Generated row source key is unique. | DomainConflict | TEST-004-003 |
| INV-CON-004 | Contract is context/generator only. | DomainConflict | TEST-004-004 |

## Clarifications

### Resolved from repository

The feature preserves the verified rules listed in `docs/replatform/source-traceability.md` and `current-state.md`.

### Proposed target

Optimistic concurrency uses an integer `lock_version`; updates include the expected version and increment it atomically.

### Unresolved

Only questions listed in `docs/replatform/open-questions.md`; none delegates architecture to the coding agent.
