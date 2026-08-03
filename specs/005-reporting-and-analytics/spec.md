# Feature 005 — Reporting and analytics

Status: PARTIALLY READY — Q-001 through Q-015 propagated; Q-019, Q-020, Q-027, and Q-028 remain open  
Logical owner: product owner with domain approval  
Actor: decision maker  
Dependencies: feature 004

## Problem

The Frappe implementation contains verified behavior but couples schema, controller lifecycle, report queries and framework UI. A coding agent cannot safely replatform it from generic requirements without stable invariants, files and tests.

## Objective

A user can view current position, forecast, exceptions and exports from one authoritative dataset while preserving the traced legacy behavior and the target operational constraints.

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

### US-005-01 — dashboard
Priority: P1  
Value: enables view current position, forecast, exceptions and exports from one authoritative dataset.  
Acceptance: AC-005-01; requirements: FR-005-001, FR-005-002.
### US-005-02 — economic position
Priority: P2  
Value: enables view current position, forecast, exceptions and exports from one authoritative dataset.  
Acceptance: AC-005-02; requirements: FR-005-002, FR-005-010.
### US-005-03 — variance/comparison report
Priority: P2  
Value: enables view current position, forecast, exceptions and exports from one authoritative dataset.  
Acceptance: AC-005-03; requirements: FR-005-010, FR-005-011.
### US-005-04 — print/export
Priority: P2  
Value: enables view current position, forecast, exceptions and exports from one authoritative dataset.  
Acceptance: AC-005-04; requirements: FR-005-011, FR-005-012.

## Acceptance scenarios

### AC-005-01 — Main path
Given an authenticated and authorized actor and valid prerequisite records  
When the actor completes the primary reporting and analytics operation  
Then the server persists the result in one transaction  
And the response reflects server-calculated values  
And an unauthorized actor receives 403 without mutation.

### AC-005-02 — Validation and rollback
Given invalid or conflicting input  
When the operation is submitted  
Then validation/domain errors identify the affected field or invariant  
And the transaction is rolled back  
And no partial side effect remains.

### AC-005-03 — Empty and legacy-anomaly state
Given no matching records or a quarantined legacy anomaly  
When the screen/query is opened  
Then an explicit empty/error state is rendered  
And no value is silently invented.

### AC-005-04 — Concurrency
Given two writers loaded the same record version  
When the first saves and the second submits stale data  
Then the second receives a concurrency conflict  
And can reload current data before retrying.

### AC-005-05 — Tenant-scoped reporting and global operational overview

Given economic data in two tenants, when Administrator, Editor, or Viewer opens a tenant report/export, then the dataset contains one current tenant only. When Administrator opens the global overview, only approved operational tenant metadata appears and no combined economic value is calculated.

## Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| FR-005-001 | Dashboard filters shall include year and optional cost center. | AC-001 |
| FR-005-002 | Each KPI shall expose formula, source, empty state and drill-down. | AC-002 |
| FR-005-010 | Economic Position shall not add contract values independently. | AC-010 |
| FR-005-011 | Available budget equals operating budget plus plafond total. | AC-011 |
| FR-005-012 | Year-end forecast equals actual total plus forecast remaining. | AC-012 |
| FR-005-013 | Remaining or over equals available budget minus year-end forecast. | AC-013 |
| FR-005-014 | Usage percent equals actual divided by year-end forecast, or zero when denominator is zero. | AC-014 |
| FR-005-020 | Table, chart, print, CSV and XLSX shall share the same dataset contract. | AC-020 |
| FR-005-021 | Exports shall preserve active filters, order, locale, currency and timezone. | AC-021 |
| FR-005-030 | Viewer may read/print/export but not mutate data. | AC-030 |

| FR-005-031 | Every economic report, dashboard, drill-down, print, CSV, and XLSX shall contain data from exactly one tenant. | AC-005-05 |
| FR-005-032 | Administrator global overview shall expose only the operational fields approved by Q-014 and shall not aggregate economic values across tenants. | AC-005-05 |
## Non-functional requirements

| ID | Measure | Threshold and verification |
|---|---|---|
| NFR-005-PERF-01 | Primary register on 10,000 rows | server response p95 ≤ 800 ms on documented reference environment, measured with seeded feature test profile |
| NFR-005-A11Y-01 | Keyboard/accessibility | all controls keyboard reachable; labels/errors programmatically associated; axe smoke has no critical violations |
| NFR-005-SEC-01 | Authorization | 100% mapped write/read routes have allow and deny tests |
| NFR-005-INT-01 | Integrity | every documented write is transactional and rollback-tested |
| NFR-005-LOG-01 | Logging | domain conflicts are user-safe; unexpected errors include correlation ID and no sensitive payload |

## Business invariants

| ID | Rule | Error | Test |
|---|---|---|---|
| INV-REP-001 | No double counting of contracts/projects. | DomainConflict | TEST-005-001 |
| INV-REP-002 | All presentation forms share semantic rows. | DomainConflict | TEST-005-002 |
| INV-REP-003 | Metric formulas are server-side. | DomainConflict | TEST-005-003 |
| INV-REP-004 | Filter authorization limits visible rows. | DomainConflict | TEST-005-004 |

| INV-REP-005 | No report or export combines economic data from different tenants. | DomainConflict | TEST-005-031 |

## Clarifications

### Resolved from repository

The feature preserves the verified rules listed in `docs/replatform/source-traceability.md` and `current-state.md`.

### Approved product decisions

Q-005, Q-009, Q-010, and Q-014 define read/export rights, ownership, and the non-economic global overview. Feature 007 and `docs/replatform/approved-decisions.md` are normative for tenant scope.

### Proposed target

Optimistic concurrency uses an integer `lock_version`; updates include the expected version and increment it atomically.

### Unresolved

Questions Q-016 onward in `docs/replatform/product-clarification-register.md` and the remaining items in `docs/replatform/open-questions.md`; none may be resolved implicitly by the coding agent.
