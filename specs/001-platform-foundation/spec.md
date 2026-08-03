# Feature 001 — Platform foundation

Status: IMPLEMENTATION READY within documented scope  
Logical owner: product owner with domain approval  
Actor: authenticated user  
Dependencies: none

## Problem

The Frappe implementation contains verified behavior but couples schema, controller lifecycle, report queries and framework UI. A coding agent cannot safely replatform it from generic requirements without stable invariants, files and tests.

## Objective

A user can authenticate, load the application shell, enforce roles and run on shared PHP hosting while preserving the traced legacy behavior and the target operational constraints.

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

### US-001-01 — login
Priority: P1  
Value: enables authenticate, load the application shell, enforce roles and run on shared PHP hosting.  
Acceptance: AC-001-01; requirements: FR-001-001, FR-001-002.
### US-001-02 — application shell
Priority: P2  
Value: enables authenticate, load the application shell, enforce roles and run on shared PHP hosting.  
Acceptance: AC-001-02; requirements: FR-001-002, FR-001-003.
### US-001-03 — settings
Priority: P2  
Value: enables authenticate, load the application shell, enforce roles and run on shared PHP hosting.  
Acceptance: AC-001-03; requirements: FR-001-003, FR-001-004.

## Acceptance scenarios

### AC-001-01 — Main path
Given an authenticated and authorized actor and valid prerequisite records  
When the actor completes the primary platform foundation operation  
Then the server persists the result in one transaction  
And the response reflects server-calculated values  
And an unauthorized actor receives 403 without mutation.

### AC-001-02 — Validation and rollback
Given invalid or conflicting input  
When the operation is submitted  
Then validation/domain errors identify the affected field or invariant  
And the transaction is rolled back  
And no partial side effect remains.

### AC-001-03 — Empty and legacy-anomaly state
Given no matching records or a quarantined legacy anomaly  
When the screen/query is opened  
Then an explicit empty/error state is rendered  
And no value is silently invented.

### AC-001-04 — Concurrency
Given two writers loaded the same record version  
When the first saves and the second submits stale data  
Then the second receives a concurrency conflict  
And can reload current data before retrying.

## Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| FR-001-001 | The application shall authenticate local users with email and password. | AC-001 |
| FR-001-002 | Every protected route shall require authentication and an active user. | AC-002 |
| FR-001-003 | The four legacy roles shall be represented and enforced by policies. | AC-003 |
| FR-001-004 | The shell shall expose Dashboard, Expenses, Projects, Contracts, Master Data, Reports and Settings according to permission. | AC-004 |
| FR-001-005 | Assets shall be precompiled and no Node.js runtime shall be required in production. | AC-005 |
| FR-001-006 | The scheduler shall be invokable by one cron entry and shall use overlap prevention. | AC-006 |
| FR-001-007 | Application timezone shall be Europe/Rome, locale Italian, currency EUR. | AC-007 |
| FR-001-008 | Settings writes shall be restricted to Administrator and Administrator. | AC-008 |

## Non-functional requirements

| ID | Measure | Threshold and verification |
|---|---|---|
| NFR-001-PERF-01 | Primary register on 10,000 rows | server response p95 ≤ 800 ms on documented reference environment, measured with seeded feature test profile |
| NFR-001-A11Y-01 | Keyboard/accessibility | all controls keyboard reachable; labels/errors programmatically associated; axe smoke has no critical violations |
| NFR-001-SEC-01 | Authorization | 100% mapped write/read routes have allow and deny tests |
| NFR-001-INT-01 | Integrity | every documented write is transactional and rollback-tested |
| NFR-001-LOG-01 | Logging | domain conflicts are user-safe; unexpected errors include correlation ID and no sensitive payload |

## Business invariants

| ID | Rule | Error | Test |
|---|---|---|---|
| INV-PLT-001 | Unauthenticated requests cannot access business routes. | DomainConflict | TEST-001-001 |
| INV-PLT-002 | A hidden navigation item does not replace server-side authorization. | DomainConflict | TEST-001-002 |
| INV-PLT-003 | Production release contains a Vite manifest and compiled assets. | DomainConflict | TEST-001-003 |
| INV-PLT-004 | No initial feature requires a permanent worker. | DomainConflict | TEST-001-004 |

## Clarifications

### Resolved from repository

The feature preserves the verified rules listed in `docs/replatform/source-traceability.md` and `current-state.md`.

### Proposed target

Optimistic concurrency uses an integer `lock_version`; updates include the expected version and increment it atomically.

### Unresolved

Only questions listed in `docs/replatform/open-questions.md`; none delegates architecture to the coding agent.
