# Feature 003 — Expense domain

Status: `CLARIFIED — PLAN REGENERATION REQUIRED`  
Logical owner: Product Owner with domain approval  
Actor: tenant user with explicit permission  
Dependencies: Feature 002 and Feature 007

## Problem

The target must preserve verified monetary, VAT, funding, allocation, and source-row semantics while replacing the legacy parallel `Active/Replaced/Cancelled` editing model with one current expense identity plus true revision history. Actual rows must be correctable and deletable without making revision or audit storage an economic source.

## Objective

Create, edit, version, restore, delete, report, print, and export tenant-owned expenses and rows with exact calculations, one current logical record, explicit permissions, and complete exclusion of deleted or historical revisions from current totals.

## User stories

### US-003-01 — Current expense register

An authorized actor sees each current expense once, with server-calculated totals and no superseded/deleted copies.

### US-003-02 — Expense editor

An authorized actor creates or edits an Expense aggregate, including Estimate, Quote, Actual, and Plafond rows, in one transaction.

### US-003-03 — Revision history

An authorized actor compares revisions and restores a valid earlier state as a new current revision.

### US-003-04 — Delete expense or row

An authorized actor deletes a current expense or row after explicit confirmation; it disappears from the active domain and all current outputs while minimized revision/audit evidence remains.

## Acceptance scenarios

### AC-003-01 — Current totals

Given current, revised, restored, and deleted expenses, the register, KPI, report, print, CSV, and XLSX use only current non-deleted rows and return identical exact totals.

### AC-003-02 — Correct Actual

Given an Actual row, an actor with update permission changes valid fields. The same logical row remains current, the aggregate receives one revision batch, exact totals update, and the prior state is available only in revision history.

### AC-003-03 — Delete Actual or expense

Given a current Actual row or Expense, an actor with delete permission confirms deletion. The deleted object is absent from ordinary queries, selections, totals, reports, prints, exports, and relationships. Audit/revision data contains only the approved minimized evidence.

### AC-003-04 — Restore revision

Given an earlier revision, restore revalidates tenant ownership, current master-data availability rules, money, VAT, dates, funding, project/contract links, source keys, and concurrency. Success creates a new revision; failure leaves current state unchanged.

### AC-003-05 — Aggregate revision

Given one save that changes an Expense and several rows, the UI shows one logical revision operation linked by `revision_batch_uuid`, while each persisted model version remains traceable.

### AC-003-06 — Tenant and permission denial

Missing permission, inactive tenant, deactivated user, or other-tenant identifier fails before protected data or revision details are disclosed.

### AC-003-07 — Validation and rollback

Invalid money, VAT, dates, funding, references, duplicate source keys, or stale `lock_version` rolls back the entire aggregate and file operation.

## Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| FR-003-001 | Expense kind shall be `Ordinary` or `Plafond`. | AC-003-07 |
| FR-003-002 | Expense shall require tenant, year, cost center, title, and at least one current row. | AC-003-01 |
| FR-003-010 | Ordinary rows shall require vendor and phase `Estimate`, `Quote`, or `Actual`. | AC-003-02 |
| FR-003-011 | An Ordinary expense shall not be both Extra and funded by Plafond. | AC-003-07 |
| FR-003-012 | A referenced Plafond shall belong to the same tenant/year; cost center may differ. | AC-003-07 |
| FR-003-020 | Only current non-deleted expense rows shall contribute to current totals. | AC-003-01 |
| FR-003-021 | Operational revisions, audit events, deleted tombstones, scenarios, and named budget versions shall not contribute to current totals. | AC-003-01 |
| FR-003-030 | An actor with the exact permission may create/update/delete Expense and Estimate/Quote/Actual rows subject to all invariants. | AC-003-02, AC-003-03 |
| FR-003-031 | Actual rows shall support correction, revision compare, restore, and deletion; Actual is not immutable in the target model. | AC-003-02, AC-003-04 |
| FR-003-032 | Expense and row identity shall remain stable across revisions; restore shall create a new current revision rather than rewrite history. | AC-003-04 |
| FR-003-033 | One aggregate operation shall assign a shared `revision_batch_uuid` to the Expense and changed rows. | AC-003-05 |
| FR-003-034 | Permitted deletion shall remove the record from the active domain and retain only approved revision/audit evidence. | AC-003-03 |
| FR-003-040 | Non-zero unit price shall derive entered amount as quantity multiplied by unit price; otherwise amount is manual. | AC-003-07 |
| FR-003-041 | Non-zero amount shall require row VAT rate or tenant default. | AC-003-07 |
| FR-003-050 | A row shall use spend date or complete period dates plus distribution, never both. | AC-003-07 |
| FR-003-051 | Distribution shall be `all`, `start`, or `end`. | AC-003-07 |
| FR-003-052 | Estimate and Quote net amounts shall not be negative; Actual may be negative. | AC-003-07 |
| FR-003-060 | Register, detail, print, export, scenario inputs, and budget-version source rows shall use server-calculated exact values. | AC-003-01 |
| FR-003-061 | Expense aggregate, rows, references, files, revisions, and audit shall belong to exactly one tenant. | AC-003-06 |
| FR-003-062 | Revision history view/restore and delete operations shall be separately permission-controlled. | AC-003-04, AC-003-06 |
| FR-003-063 | Optimistic writes shall require and increment `lock_version`. | AC-003-07 |

## Business invariants

| ID | Rule | Error | Test |
|---|---|---|---|
| INV-EXP-001 | Current non-deleted rows are the sole current total source. | DomainConflict | TEST-003-001 |
| INV-EXP-002 | Expense kind domain is closed. | DomainConflict | TEST-003-002 |
| INV-EXP-003 | At most one project/contract context is present. | DomainConflict | TEST-003-003 |
| INV-PLF-001 | Extra and Plafond funding are mutually exclusive. | DomainConflict | TEST-003-004 |
| INV-PLF-002 | Plafond reference shares tenant and year. | DomainConflict | TEST-003-005 |
| INV-REV-001 | Revision/audit/deleted storage never enters current economic queries. | DomainConflict | TEST-003-006 |
| INV-REV-002 | Restore creates a new revision and cannot rewrite prior history. | DomainConflict | TEST-003-007 |
| INV-REV-003 | Restored state must satisfy every current invariant and reference rule. | DomainConflict | TEST-003-008 |
| INV-AMT-001 | Authoritative money arithmetic is decimal. | DomainConflict | TEST-003-009 |
| INV-VAT-001 | Net plus VAT equals gross at two decimals. | DomainConflict | TEST-003-010 |
| INV-DATE-001 | Date modes are exclusive. | DomainConflict | TEST-003-011 |
| INV-DIST-001 | Monthly allocated sum equals row net exactly. | DomainConflict | TEST-003-012 |
| INV-TEN-003 | Expense, rows, references, attachments, revisions, and audit share one tenant. | Authorization/DomainConflict | TEST-003-013 |

## Migration notes

Legacy `Active/Replaced/Cancelled` rows remain source evidence. `/speckit.plan` must define deterministic migration into one current logical row plus revision/audit evidence where feasible, without changing accepted current totals. A legacy Actual is not migrated into a permanently immutable target state.

## Out of scope

- showing superseded or deleted copies in ordinary expense registers;
- physical audit/revision payload as a report source;
- automatic restore that bypasses current validation;
- role-name conditionals instead of permissions;
- silent deletion or restore without audit/correlation.

## Clarification result

Q-006, Q-018, and Q-024 are fully resolved by the amended product contract. The previous plan, data model, financial-rule contract, authorization matrices, accounting equivalence cases, and tasks must be regenerated before implementation.
