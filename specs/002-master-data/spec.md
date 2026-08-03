# Feature 002 — Master data

Status: `CLARIFIED AND APPROVED; IMPLEMENTATION BLOCKED UNTIL /speckit.analyze PASSES`  
Logical owner: Product Owner with domain approval  
Actor: tenant user with explicit permission  
Dependencies: Feature 001 and Feature 007

## Objective

Manage tenant-owned planning years, hierarchical cost centers, and vendors while preserving exact historical references and preventing inactive master data from being selected for new work.

## User stories

### US-002-01 — Planning years

An authorized actor manages non-overlapping planning years for the current tenant.

### US-002-02 — Cost-center hierarchy

An authorized actor manages an acyclic cost-center tree, can deactivate/reactivate nodes, and preserves historical references.

### US-002-03 — Vendors

An authorized actor manages vendors, can deactivate/reactivate them, and preserves historical readability.

## Acceptance scenarios

### AC-002-01 — Tenant and permission scope

Given data in tenants A and B, an actor in tenant A may perform only abilities granted for tenant A. Other-tenant identifiers and parents are rejected without existence leakage.

### AC-002-02 — Vendor deactivation

Given a vendor referenced by existing expenses, when it is deactivated, historical rows and filters still display it, new selections exclude it, deletion is denied, and reactivation restores new-selection eligibility.

### AC-002-03 — Cost-center deactivation

Given a cost center referenced by existing records, when it is deactivated, history remains readable and new selections exclude it. No historical record is reassigned. A parent with an active descendant cannot be deactivated.

### AC-002-04 — Revisions

Given an authorized update to a vendor or cost center, one current record remains and revision history records the change. Restoring a valid revision creates a new current revision and revalidates uniqueness, tenant ownership, references, and tree integrity.

### AC-002-05 — Validation and concurrency

Invalid dates, overlaps, duplicate names, cycles, stale `lock_version`, and forbidden deactivation fail atomically with stable errors and no partial change.

## Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| FR-002-001 | A planning year shall have a unique tenant-scoped numeric identifier, start date, end date, and active state. | AC-002-01, AC-002-05 |
| FR-002-002 | Planning-year date ranges shall not overlap inside one tenant. | AC-002-05 |
| FR-002-003 | A cost center shall have a tenant-scoped unique name and optional same-tenant parent. | AC-002-01, AC-002-05 |
| FR-002-004 | Cost-center parent assignment shall never create a cycle. | AC-002-05 |
| FR-002-005 | Group summaries shall include descendants; leaf summaries shall include only the leaf. | AC-002-01 |
| FR-002-006 | A vendor shall have a tenant-scoped unique name, optional VAT/contact values, and active state. | AC-002-01 |
| FR-002-007 | Inactive vendors shall remain readable historically, be excluded from new selection, resist deletion while referenced, and support reactivation. | AC-002-02 |
| FR-002-008 | Inactive cost centers shall remain readable historically, be excluded from new selection, resist automatic reassignment, and support reactivation. | AC-002-03 |
| FR-002-009 | A cost-center parent with an active descendant shall not be deactivated; branch deactivation requires explicit descendant operations. | AC-002-03 |
| FR-002-010 | Years, cost centers, vendors, revisions, imports, exports, and selections shall be scoped to one tenant. | AC-002-01 |
| FR-002-011 | Create/update/deactivate/reactivate/restore abilities shall be permission-controlled; seeded Editor and Viewer behavior is defined by Feature 007. | AC-002-01, AC-002-04 |
| FR-002-012 | Vendor and cost-center operational revisions shall preserve one current record and support compare/restore under the cross-cutting versioning contract. | AC-002-04 |
| FR-002-013 | Optimistic updates shall require and increment `lock_version`. | AC-002-05 |

## Business invariants

| ID | Rule | Error | Test |
|---|---|---|---|
| INV-YEAR-001 | Year start is not after end. | DomainConflict | TEST-002-001 |
| INV-YEAR-002 | Tenant year ranges do not overlap. | DomainConflict | TEST-002-002 |
| INV-CC-001 | Cost-center graph is acyclic. | DomainConflict | TEST-002-003 |
| INV-CC-002 | Active descendants block parent deactivation. | DomainConflict | TEST-002-004 |
| INV-VEN-001 | Historical vendor references survive deactivation. | DomainConflict | TEST-002-005 |
| INV-MD-REV-001 | A restored master-data revision must satisfy current uniqueness, tenant, and reference invariants. | DomainConflict | TEST-002-006 |
| INV-TEN-002 | Master data cannot reference another tenant. | Authorization/DomainConflict | TEST-002-007 |

## Out of scope

- permanent deletion of referenced vendors or cost centers;
- automatic reassignment of historical records;
- recursive implicit cost-center deactivation;
- cross-tenant master-data sharing;
- role-name authorization branches.

## Clarification result

Q-030 and Q-031 are closed. The prior plan/tasks and authorization matrices must be regenerated against configurable permissions and operational revisions before implementation.
