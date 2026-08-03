# Feature 003 — Expense domain

Status: `CLARIFIED — PLAN REGENERATION REQUIRED`  
Logical owner: Product Owner with domain approval  
Actor: tenant user with explicit permission  
Dependencies: Feature 002 and Feature 007  
Additional decisions: Q-035, Q-037, Q-038

## Problem

The target must preserve verified monetary, VAT, funding, allocation and source-row semantics while exposing one current expense identity plus true revision history. Estimate, Quote and Actual must remain independent economic records. Actual rows are correctable and deletable, and contract confirmation must stop automatic overwrite without making the record permanently immutable.

## Objective

Create, edit, version, restore, delete, report, print and export tenant-owned expenses and rows with exact calculations, stable identity, explicit permissions, independent economic types, visible Actual confirmation state and complete exclusion of deleted or historical revisions from current totals.

## User stories

### US-003-01 — Current expense register

An authorized actor sees each current expense once, with server-calculated Net, VAT and Gross totals and no superseded/deleted copies.

### US-003-02 — Independent economic rows

An authorized actor adds Estimate, Quote or Actual rows according to the cost information available, without being forced through a progression workflow.

### US-003-03 — Actual confirmation

An authorized actor distinguishes Actual `Da confermare` from `Confermata` and confirms a valid row without losing later versioned correction/delete abilities.

### US-003-04 — Revision history

An authorized actor compares revisions and restores a valid earlier state as a new current revision.

### US-003-05 — Delete expense or row

An authorized actor deletes a current expense or row after explicit confirmation; it disappears from the active domain and all current outputs while minimized revision/audit evidence remains.

## Acceptance scenarios

### AC-003-01 — Current totals

Given current, revised, restored and deleted expenses, register, KPI, report, print, CSV and XLSX use only current non-deleted rows and return identical exact Net, VAT and Gross totals.

### AC-003-02 — Independent types

Given one cost being planned, quoted or incurred, an authorized actor may create the applicable Estimate, Quote or Actual directly. No missing predecessor blocks creation. Multiple rows of the same or different type may coexist when they represent distinct costs.

### AC-003-03 — Correct Actual

Given an Actual row, an actor with update permission changes valid fields. The same logical row remains current, the aggregate receives one revision batch, exact totals update and the prior state is available only in revision history.

### AC-003-04 — Confirm Actual

Given a valid Actual Da confermare, confirmation records actor/time and changes confirmation status. If contract-generated, automatic contract synchronization can no longer overwrite it. Confirmation does not remove authorized update, revision, restore or delete operations.

### AC-003-05 — Delete Actual or expense

Given a current Actual row or Expense, an actor with delete permission confirms deletion. The deleted object is absent from ordinary queries, selections, totals, reports, prints, exports and relationships. Audit/revision data contains only approved minimized evidence.

### AC-003-06 — Restore revision

Given an earlier revision, restore revalidates tenant ownership, current master-data availability rules, money, VAT, dates, funding, project/contract links, source keys, confirmation state and concurrency. Success creates a new revision; failure leaves current state unchanged.

### AC-003-07 — Aggregate revision

Given one save that changes an Expense and several rows, the UI shows one logical revision operation linked by `revision_batch_uuid`, while each persisted model version remains traceable.

### AC-003-08 — Tenant and permission denial

Missing permission, inactive tenant, deactivated user or other-tenant identifier fails before protected data or revision details are disclosed.

### AC-003-09 — Validation and rollback

Invalid money, VAT, dates, funding, references, duplicate source keys or stale `lock_version` rolls back the entire aggregate and file operation.

## Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| FR-003-001 | Expense kind shall be `Ordinary` or `Plafond`. | AC-003-09 |
| FR-003-002 | Expense shall require tenant, year, cost center, title and at least one current row. | AC-003-01 |
| FR-003-010 | Ordinary rows shall require vendor and type `Estimate`, `Quote`, or `Actual`. | AC-003-02 |
| FR-003-011 | Estimate, Quote and Actual shall be independent; no mandatory predecessor/progression shall be enforced. | AC-003-02 |
| FR-003-012 | Multiple current rows may coexist when they represent distinct costs; identity/revision operations shall correct the same logical cost rather than display replacement copies. | AC-003-02, AC-003-03 |
| FR-003-013 | An Ordinary expense shall not be both Extra and funded by Plafond. | AC-003-09 |
| FR-003-014 | A referenced Plafond shall belong to the same tenant/year; cost center may differ. | AC-003-09 |
| FR-003-020 | Only current non-deleted expense rows shall contribute to current totals. | AC-003-01 |
| FR-003-021 | Operational revisions, audit events, deleted tombstones, scenarios, generation exceptions and named budget versions shall not contribute to current totals. | AC-003-01 |
| FR-003-022 | Current datasets shall preserve each row's Net, VAT and Gross values irrespective of the tenant's selected official Budget basis. | AC-003-01 |
| FR-003-030 | An actor with the exact permission may create/update/delete Expense and Estimate/Quote/Actual rows subject to all invariants. | AC-003-02, AC-003-05 |
| FR-003-031 | Actual rows shall support correction, revision compare, restore and deletion; Actual is not immutable in the target model. | AC-003-03, AC-003-06 |
| FR-003-032 | Actual confirmation status shall be `ToConfirm` or `Confirmed`; confirmation shall record actor/time and shall not remove later authorized versioned operations. | AC-003-04 |
| FR-003-033 | Expense and row identity shall remain stable across revisions; restore shall create a new current revision rather than rewrite history. | AC-003-06 |
| FR-003-034 | One aggregate operation shall assign a shared `revision_batch_uuid` to the Expense and changed rows. | AC-003-07 |
| FR-003-035 | Permitted deletion shall remove the record from the active domain and retain only approved revision/audit evidence. | AC-003-05 |
| FR-003-040 | Non-zero unit price shall derive entered amount as quantity multiplied by unit price; otherwise amount is manual. | AC-003-09 |
| FR-003-041 | Non-zero amount shall require row VAT rate or tenant default. | AC-003-09 |
| FR-003-042 | Tenant setting shall select official Budget basis `Net` or `Gross`, default `Net`; changing it shall not rewrite current row components or published BudgetVersion values. | AC-003-01 |
| FR-003-050 | A row shall use spend date or complete period dates plus distribution, never both. | AC-003-09 |
| FR-003-051 | Distribution shall be `all`, `start`, or `end`. | AC-003-09 |
| FR-003-052 | Estimate and Quote net amounts shall not be negative; Actual may be negative. | AC-003-09 |
| FR-003-060 | Register, detail, print, export, scenario inputs and budget-version source rows shall use server-calculated exact values. | AC-003-01 |
| FR-003-061 | Expense aggregate, rows, references, files, revisions and audit shall belong to exactly one tenant. | AC-003-08 |
| FR-003-062 | Revision history view/restore, confirmation and delete operations shall be separately permission-controlled. | AC-003-04, AC-003-06, AC-003-08 |
| FR-003-063 | Optimistic writes shall require and increment `lock_version`. | AC-003-09 |

## Business invariants

| ID | Rule | Error | Test |
|---|---|---|---|
| INV-EXP-001 | Current non-deleted rows are the sole current total source. | DomainConflict | TEST-003-001 |
| INV-EXP-002 | Expense kind and row type domains are closed. | DomainConflict | TEST-003-002 |
| INV-EXP-003 | No mandatory Estimate/Quote/Actual progression exists. | DomainConflict | TEST-003-003 |
| INV-EXP-004 | At most one project/contract context is present. | DomainConflict | TEST-003-004 |
| INV-PLF-001 | Extra and Plafond funding are mutually exclusive. | DomainConflict | TEST-003-005 |
| INV-PLF-002 | Plafond reference shares tenant and year. | DomainConflict | TEST-003-006 |
| INV-REV-001 | Revision/audit/deleted storage never enters current economic queries. | DomainConflict | TEST-003-007 |
| INV-REV-002 | Restore creates a new revision and cannot rewrite prior history. | DomainConflict | TEST-003-008 |
| INV-REV-003 | Restored state must satisfy every current invariant and reference rule. | DomainConflict | TEST-003-009 |
| INV-ACT-001 | Confirmation blocks automatic contract overwrite but does not make Actual permanently immutable. | DomainConflict | TEST-003-010 |
| INV-AMT-001 | Authoritative money arithmetic is decimal. | DomainConflict | TEST-003-011 |
| INV-VAT-001 | Net plus VAT equals Gross at two decimals. | DomainConflict | TEST-003-012 |
| INV-BAS-001 | Net/VAT/Gross components remain exact regardless of official presentation basis. | DomainConflict | TEST-003-013 |
| INV-DATE-001 | Date modes are exclusive. | DomainConflict | TEST-003-014 |
| INV-DIST-001 | Monthly allocated sum equals row net exactly. | DomainConflict | TEST-003-015 |
| INV-TEN-003 | Expense, rows, references, attachments, revisions and audit share one tenant. | Authorization/DomainConflict | TEST-003-016 |

## Migration notes

Legacy `Active/Replaced/Cancelled` rows remain source evidence. `/speckit.plan` must define deterministic migration into one current logical row plus revision/audit evidence where feasible, without changing accepted current totals. A legacy Actual is not migrated into a permanently immutable target state.

Legacy Estimate/Quote/Actual relationships must not be converted into a mandatory workflow unless the source contains an explicit same-cost identity that can be preserved deterministically.

## Out of scope

- showing superseded or deleted copies in ordinary expense registers;
- mandatory Estimate → Quote → Actual workflow;
- physical audit/revision payload as a report source;
- automatic restore that bypasses current validation;
- role-name conditionals instead of permissions;
- silent deletion, confirmation or restore without audit/correlation.

## Clarification result

Q-006, Q-018, Q-024, Q-035, Q-037 and Q-038 are resolved. Previous plans, data models, financial-rule contracts, authorization matrices, accounting cases and tasks must be regenerated before implementation.
