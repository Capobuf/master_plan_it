# Feature 001 — Platform foundation

Status: PARTIALLY READY — Q-001 through Q-015 propagated; Q-016, Q-020, Q-024, and Q-026 remain open where applicable  
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

### AC-001-05 — Tenant context and role enforcement

Given two active tenants and users for each product role, when protected routes and the application shell are used, then Administrator explicitly selects tenant context, Editor and Viewer remain fixed to their tenant, side navigation and breadcrumbs show the current tenant, and another tenant's route/identifier is denied server-side.

## Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| FR-001-001 | The application shall authenticate local users with email and password. | AC-001 |
| FR-001-002 | Every protected route shall require authentication and an active user. | AC-002 |
| FR-001-003 | The roles shall be exactly Administrator, Editor, and Viewer and shall be enforced server-side. | AC-003 |
| FR-001-004 | The shell shall expose Dashboard, Expenses, Projects, Contracts, Master Data, Reports and Settings according to permission. | AC-004 |
| FR-001-005 | Assets shall be precompiled and no Node.js runtime shall be required in production. | AC-005 |
| FR-001-006 | The scheduler shall be invokable by one cron entry and shall use overlap prevention. | AC-006 |
| FR-001-007 | Platform shall use UTC storage and apply the current tenant language, timezone, currency, and default VAT configuration to tenant-facing output. | AC-007 |
| FR-001-008 | Global settings, tenant lifecycle, tenant users, and tenant settings writes shall be restricted to Administrator. | AC-008 |

| FR-001-009 | Tenant-bound routes shall require explicit valid tenant context; current tenant shall be visible in side navigation and breadcrumbs. | AC-001-05 |
| FR-001-010 | Only Administrator shall create, deactivate, reactivate, and manage tenant users; Editor and Viewer belong to exactly one tenant. | AC-001-05 |
| FR-001-011 | Tenant creation shall require display name, unique code, currency, language, timezone, and default VAT rate; application shell branding remains Master Plan IT. | AC-001-05 |
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

| INV-TEN-001 | Missing or unauthorized tenant context fails closed and cannot expose another tenant. | DomainConflict | TEST-007-002 |

## Clarifications

### Resolved from repository

The feature preserves the verified rules listed in `docs/replatform/source-traceability.md` and `current-state.md`.

### Approved product decisions

Q-001–Q-005 and Q-010, Q-012, Q-013, Q-015 define the three roles, tenant users, explicit context, tenant settings, and shell branding. Feature 007 and `docs/replatform/approved-decisions.md` are normative for tenant scope.

### Proposed target

Optimistic concurrency uses an integer `lock_version`; updates include the expected version and increment it atomically.

### Unresolved

Questions Q-016 onward in `docs/replatform/product-clarification-register.md` and the remaining items in `docs/replatform/open-questions.md`; none may be resolved implicitly by the coding agent.
