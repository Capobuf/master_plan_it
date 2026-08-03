# Feature 004 — Contracts and projects

Status: `CLARIFIED — PLAN REGENERATION REQUIRED`  
Logical owner: Product Owner with domain approval  
Actor: tenant user with explicit permission  
Dependencies: Feature 003 and Feature 007

## Objective

Manage tenant-owned projects and contracts, version their current records, generate authoritative expenses through stable source keys, show generation history, and let authorized users suppress, resume, or manually create one valid missing occurrence without duplication or silent overwrite.

## User stories

### US-004-01 — Projects

An authorized actor manages project stages and deferred decisions while revision history preserves one current project identity.

### US-004-02 — Contracts and terms

An authorized actor manages a contract and non-overlapping term timeline with renewal and revision history.

### US-004-03 — Generated-expense history

An authorized actor opens the contract and sees every expected/generated occurrence, linked current expense, deletion/suppression state, and revision/history links.

### US-004-04 — Delete generated expense

When deleting a contract-generated expense, an authorized actor chooses whether the missing occurrence may be generated again or is explicitly suppressed.

### US-004-05 — Resume or generate one year

An authorized actor resumes a suppressed occurrence, resumes and generates immediately, or generates one compatible missing occurrence for a selected year.

## Acceptance scenarios

### AC-004-01 — Tenant and permission scope

Projects, contracts, terms, generation identities, exceptions, linked expenses, revisions, and notifications remain in one tenant. Missing permission or other-tenant identifiers are denied before disclosure.

### AC-004-02 — Contract term integrity

Terms do not overlap. Billing cycle, effective dates, derived non-final end dates, and renewals are validated atomically.

### AC-004-03 — Append-missing synchronization

Synchronization calculates expected occurrences, creates only missing unsuppressed source keys, and never overwrites an existing user-edited expense.

### AC-004-04 — Contract generation history

The contract page shows year, source term/rule, expected source key, linked expense, current state, generation/deletion/suppression/resume actors and timestamps, plus links to expense and contract revisions.

### AC-004-05 — Delete with regeneration allowed

Deleting a generated expense with `regeneration allowed` removes the current expense. The source occurrence becomes missing and a future synchronization may recreate it.

### AC-004-06 — Delete and suppress

Deleting with `prevent regeneration` removes the current expense and creates one non-economic generation exception for its source key. Later synchronization skips that occurrence.

### AC-004-07 — Resume generation

`Resume generation` removes the exception; `Resume and generate now` also creates the occurrence immediately if still valid and missing.

### AC-004-08 — Generate for selected year

`Generate for year` accepts one compatible planning year, rejects an existing/suppressed/invalid occurrence unless the actor explicitly resumes it, and creates exactly one expense.

### AC-004-09 — Revisions and restore

Project, contract, and term updates create operational revisions. Restore creates a new current revision and revalidates term overlap, tenant references, generation identities, and already-generated history.

### AC-004-10 — Renewal/expiry notifications

The scheduler creates deduplicated database notifications and optional synchronous email at 30/7/1 days and expiration for recipients with the configured permission. No permanent worker is required.

## Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| FR-004-001 | Project shall require tenant, title, cost center, and valid stage. | AC-004-01 |
| FR-004-010 | Project stage shall be `Idea`, `Proposed`, `Approved`, `Deferred`, or `Rejected`. | AC-004-09 |
| FR-004-015 | Deferred shall require target year and shall become Proposed when the approved promotion rule runs for that current year. | AC-004-09 |
| FR-004-020 | Contract shall require tenant, vendor, cost center, and at least one non-overlapping term. | AC-004-02 |
| FR-004-021 | Term billing cycle shall be Monthly or Annual. | AC-004-02 |
| FR-004-022 | Missing non-final term end shall resolve to the day before the next term. | AC-004-02 |
| FR-004-023 | Auto-renew shall create at most one non-overlapping successor when year coverage exists. | AC-004-02 |
| FR-004-025 | Synchronization shall create one missing generated expense occurrence per valid term/year source key and shall not overwrite an existing expense. | AC-004-03 |
| FR-004-026 | Generated source key shall be tenant-scoped, unique, stable, and immutable. | AC-004-03 |
| FR-004-027 | Contract detail shall expose expected/generated occurrence history and linked expense/revision navigation. | AC-004-04 |
| FR-004-028 | Deleting a generated expense shall require an explicit regeneration choice. | AC-004-05, AC-004-06 |
| FR-004-029 | A suppressed occurrence shall use a separate non-economic generation exception and shall be skipped by synchronization. | AC-004-06 |
| FR-004-030 | Authorized actors shall be able to resume, resume-and-generate, or generate one valid missing year occurrence. | AC-004-07, AC-004-08 |
| FR-004-031 | An expense shall reference at most one project or contract. | AC-004-01 |
| FR-004-032 | Contracts and projects shall not be independent economic total sources. | AC-004-03 |
| FR-004-033 | Projects, contracts, terms, exceptions, notifications, and generated expenses shall stay within one tenant. | AC-004-01 |
| FR-004-034 | Project, contract, and term revisions shall support compare/restore while retaining one current record. | AC-004-09 |
| FR-004-035 | Renewal/expiry notifications shall run synchronously from Laravel scheduler without queue workers. | AC-004-10 |
| FR-004-036 | Generation, suppression, resume, and manual-year actions shall be separately permission-controlled. | AC-004-01, AC-004-05, AC-004-08 |

## Business invariants

| ID | Rule | Error | Test |
|---|---|---|---|
| INV-PRJ-001 | Project stage is a closed enum. | DomainConflict | TEST-004-001 |
| INV-PRJ-002 | Deferred project has a valid same-tenant target year. | DomainConflict | TEST-004-002 |
| INV-CON-001 | Contract terms do not overlap. | DomainConflict | TEST-004-003 |
| INV-CON-002 | Synchronization is idempotent, append-missing, suppression-aware, and no-overwrite. | DomainConflict | TEST-004-004 |
| INV-CON-003 | Generated source key is unique inside one tenant. | DomainConflict | TEST-004-005 |
| INV-CON-004 | Contract and project remain context/generator records, not economic total sources. | DomainConflict | TEST-004-006 |
| INV-CON-005 | A generation exception never contributes to economic totals. | DomainConflict | TEST-004-007 |
| INV-CON-006 | Manual generation cannot duplicate, overwrite, or escape term/year applicability. | DomainConflict | TEST-004-008 |
| INV-REV-004 | Restoring a contract/project revision cannot rewrite generated-expense history or duplicate source keys. | DomainConflict | TEST-004-009 |
| INV-TEN-004 | Generation cannot create or link another tenant's expense or exception. | Authorization/DomainConflict | TEST-004-010 |

## Out of scope

- contracts contributing directly to totals;
- automatic regeneration after explicit suppression;
- bulk manual generation that bypasses per-occurrence validation;
- silent overwrite of user-edited generated expenses;
- queued/real-time notifications;
- role-name authorization branches.

## Clarification result

Q-008, Q-023, and PD-GEN-001 are closed. Existing Feature 004 plans, tasks, contract-screen, generation-sync, data-model, and accounting cases must be regenerated before implementation.
