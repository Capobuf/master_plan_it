# Feature 002 — Master data

Status: `CLARIFIED AND APPROVED; ROLLING ANALYSIS ACTIVE; IMPLEMENTATION IN PROGRESS`
Logical owner: Product Owner with domain approval  
Actor: tenant user with explicit permission  
Dependencies: Feature 001 and Feature 007

## Objective

Manage tenant-owned planning years, hierarchical cost centers, and vendors while preserving exact historical references and preventing inactive master data from being selected for new work.

## Clarifications

### Session 2026-08-04

- Q: Come deve comportarsi un anno di pianificazione quando viene disattivato? → A: Conserva i riferimenti storici, è escluso dai nuovi utilizzi, può essere riattivato e non può essere eliminato permanentemente.
- Q: Qual è la profondità massima della gerarchia dei centri di costo? → A: Massimo 3 livelli di profondità.
- Q: Come si contano i tre livelli e come si ordinano i centri di costo fratelli? → A: La radice è il livello 1; sono ammessi al massimo radice, figlio e nipote. I fratelli sono ordinati per nome senza distinzione tra maiuscole e minuscole, poi per ID. Non viene introdotto un campo `code`.
- Q: Fornitori e centri di costo mai utilizzati possono essere eliminati permanentemente? → A: Sì. Un fornitore è eliminabile solo in assenza di riferimenti correnti o storici; un centro di costo richiede inoltre l'assenza di discendenti. L'operazione richiede l'abilità esplicita corrispondente e non rimuove l'audit.
- Q: Come devono essere definite e corrette le date degli anni di pianificazione? → A: Ogni anno coincide con l'anno solare, dal 1° gennaio al 31 dicembre. Le date sono derivate e non modificabili dall'utente; gli anni non espongono confronto o ripristino di revisioni.

## User stories

### US-002-01 — Planning years

An authorized actor creates tenant planning years identified by their calendar year, with derived immutable January 1 through December 31 boundaries, and can deactivate or reactivate them without losing historical references.

### US-002-02 — Cost-center hierarchy

An authorized actor manages an acyclic cost-center tree with at most three levels of depth, can deactivate/reactivate nodes, can delete only unreferenced leafless nodes, and preserves historical references.

### US-002-03 — Vendors

An authorized actor manages vendors, can deactivate/reactivate them, can delete only unreferenced vendors, and preserves historical readability.

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

Invalid or duplicate calendar years, non-calendar imported ranges, duplicate names, cycles, excessive hierarchy depth, stale `lock_version`, and forbidden lifecycle operations fail atomically with stable errors and no partial change.

### AC-002-06 — Planning-year lifecycle

Given a planning year referenced by existing records, deactivation preserves every historical reference and keeps the year readable in existing records and historical filters, while excluding it from new selections. Reactivation restores new-selection eligibility. Permanent deletion, boundary editing, and revision compare/restore are unavailable.

### AC-002-07 — Cost-center hierarchy depth

Given a cost-center hierarchy where a root is level one, a child is level two, and a grandchild is level three, an attempt to assign a parent that would create level four fails atomically without changing the hierarchy. Siblings are returned in case-insensitive ascending name order with ID as the deterministic tie-breaker.

### AC-002-08 — Vendor and cost-center deletion

Given an actor with the corresponding delete ability, a vendor may be permanently deleted only when no current or historical domain record references it. A cost center may be permanently deleted only when no current or historical domain record references it and it has no descendants. A forbidden deletion fails atomically; a successful deletion preserves its audit evidence.

## Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| FR-002-001 | A planning year shall have a unique tenant-scoped numeric calendar-year identifier and active state. Its start date shall be derived as January 1 and its end date as December 31 of that identifier. | AC-002-01, AC-002-05, AC-002-06 |
| FR-002-002 | Only one planning-year record may exist for the same tenant and calendar-year identifier; its derived boundaries shall be immutable. | AC-002-05, AC-002-06 |
| FR-002-003 | A cost center shall have a tenant-scoped unique name and optional same-tenant parent. | AC-002-01, AC-002-05 |
| FR-002-004 | Cost-center parent assignment shall never create a cycle. | AC-002-05 |
| FR-002-005 | Group summaries shall include descendants; leaf summaries shall include only the leaf. | AC-002-01 |
| FR-002-006 | A vendor shall have a tenant-scoped unique name, optional VAT/contact values, and active state. | AC-002-01 |
| FR-002-007 | Inactive vendors shall remain readable historically, be excluded from new selection, resist deletion while referenced by any current or historical domain record, and support reactivation. | AC-002-02, AC-002-08 |
| FR-002-008 | Inactive cost centers shall remain readable historically, be excluded from new selection, resist automatic reassignment, and support reactivation; permanent deletion additionally requires no current or historical domain references and no descendants. | AC-002-03, AC-002-08 |
| FR-002-009 | A cost-center parent with an active descendant shall not be deactivated; branch deactivation requires explicit descendant operations. | AC-002-03 |
| FR-002-010 | Years, cost centers, vendors, revisions, imports, exports, and selections shall be scoped to one tenant. | AC-002-01 |
| FR-002-011 | Every exposed operation shall be permission-controlled. Planning years expose distinct view/create/deactivate/reactivate abilities; the seeded Editor receives view only, while Administrator may deliberately assign lifecycle abilities to a custom tenant role. Vendor and cost-center create/update/delete/deactivate/reactivate/restore abilities remain distinct. Seeded Viewer behavior is defined by Feature 007. | AC-002-01, AC-002-04, AC-002-06, AC-002-08 |
| FR-002-012 | Vendor and cost-center operational revisions shall preserve one current record and support compare/restore under the cross-cutting versioning contract. | AC-002-04 |
| FR-002-013 | Optimistic updates shall require and increment `lock_version`. | AC-002-05 |
| FR-002-014 | Planning years shall support explicit permission-controlled create/deactivate/reactivate operations; inactive years shall remain historically readable, be excluded from new selections, and never be permanently deleted. Date update and operational revision compare/restore operations shall not exist. | AC-002-06 |
| FR-002-015 | A cost-center hierarchy shall have a maximum depth of three levels, counting a root as level one; parent assignment that would create level four shall fail atomically. | AC-002-07 |
| FR-002-016 | Cost-center siblings shall be ordered by case-insensitive ascending name and then by ID; this ordering shall not require a separate cost-center code field. | AC-002-07 |
| FR-002-017 | A vendor may be permanently deleted only when no current or historical domain record references it. A cost center may be permanently deleted only under the same reference condition and when it has no descendants. Successful deletion shall preserve audit evidence. | AC-002-08 |
| FR-002-018 | Import or migration input that supplies planning-year boundaries other than January 1 through December 31 of its calendar-year identifier shall be rejected or quarantined without silent normalization. | AC-002-05 |

## Business invariants

| ID | Rule | Error | Test |
|---|---|---|---|
| INV-YEAR-001 | Planning-year boundaries are derived as January 1 through December 31 of the numeric calendar-year identifier and cannot be edited. | DomainConflict | TEST-002-001 |
| INV-YEAR-002 | A tenant has at most one planning-year record for each numeric calendar-year identifier. | DomainConflict | TEST-002-002 |
| INV-YEAR-003 | Planning-year deactivation never removes or rewrites historical references, and permanent deletion is unavailable. | DomainConflict | TEST-002-008 |
| INV-YEAR-004 | Planning years expose no operational revision comparison or restoration; allowed lifecycle mutations remain audited. | DomainConflict | TEST-002-012 |
| INV-CC-001 | Cost-center graph is acyclic. | DomainConflict | TEST-002-003 |
| INV-CC-002 | Active descendants block parent deactivation. | DomainConflict | TEST-002-004 |
| INV-CC-003 | Cost-center parent assignment never produces a hierarchy deeper than three levels. | DomainConflict | TEST-002-009 |
| INV-CC-004 | Cost-center hierarchy reads use case-insensitive ascending sibling name order with ID as the deterministic tie-breaker. | DomainConflict | TEST-002-010 |
| INV-VEN-001 | Historical vendor references survive deactivation. | DomainConflict | TEST-002-005 |
| INV-MD-DEL-001 | Vendor and cost-center deletion fails when any current or historical domain reference exists; cost-center deletion also fails when any descendant exists. | DomainConflict | TEST-002-011 |
| INV-MD-REV-001 | A restored master-data revision must satisfy current uniqueness, tenant, and reference invariants. | DomainConflict | TEST-002-006 |
| INV-MD-TEN-001 | Master data cannot reference another tenant. | Authorization/DomainConflict | TEST-002-007 |

## Out of scope

- permanent deletion of referenced vendors or cost centers, and deletion of cost centers with descendants;
- permanent deletion of planning years;
- user-defined or editable planning-year boundaries;
- planning-year operational revision comparison or restoration;
- automatic reassignment of historical records;
- recursive implicit cost-center deactivation;
- cross-tenant master-data sharing;
- role-name authorization branches.

## Clarification result

Q-030 and Q-031 remain closed. The five decisions in the 2026-08-04 clarification session are encoded and propagated through the specification, plan, tasks, contracts, data model, checklists, and cross-feature registries.
