# Feature 004 — Contracts and projects

Status: `CLARIFIED AND APPROVED; IMPLEMENTATION READY; IMPLEMENTATION NOT STARTED`
Logical owner: Product Owner with domain approval  
Actor: tenant user with explicit permission  
Dependencies: Feature 003 and Feature 007  
Additional decisions: Q-035, Q-040, Q-041

## Objective

Manage tenant-owned projects and contracts, version their current records, generate Actual expenses through stable source keys, maintain a clear system-managed/user-authoritative boundary, expose generation history and let authorized users suppress, resume or manually create one valid missing occurrence without duplication or silent overwrite.

## Clarifications

### Session 2026-08-04

- Q: Quando può essere eliminato definitivamente un progetto con spese collegate? → A: Solo dopo che tutte le spese correnti collegate sono state eliminate esplicitamente tramite il loro normale flusso. L'eliminazione del progetto non cancella spese a cascata e conserva le evidenze minime di revisioni e audit.
- Q: Cosa accade alle spese generate quando viene eliminato il contratto? → A: Restano correnti e non vengono eliminate. Conservano una nota storica immutabile che identifica il contratto e la data di eliminazione; l'eliminazione raccoglie la motivazione secondo l'impostazione approvata, interrompe ogni generazione futura e mantiene la source key.
- Q: La motivazione di cancellazione è obbligatoria per progetti e contratti? → A: Il campo viene sempre richiesto nell'interfaccia ma è facoltativo per impostazione predefinita; un'impostazione può renderlo obbligatorio o nuovamente facoltativo per entrambe le cancellazioni.
- Q: Qual è l'ambito dell'impostazione e chi può modificarla? → A: È distinta per tenant e modificabile soltanto dall'Administrator globale, per un tenant selezionato esplicitamente, tramite l'abilità protetta dedicata. Editor e ruoli personalizzati non possono riceverla. La motivazione resta facoltativa di default e ha una lunghezza massima di 500 caratteri.
- Q: Cosa accade se viene eliminato un termine contrattuale che ha già generato spese? → A: Il termine viene eliminato senza cancellare le spese; queste diventano user-authoritative e conservano una nota immutabile sul termine eliminato e la source key. La generazione futura del termine si interrompe e la motivazione segue la stessa impostazione del tenant.
- Q: Progetti, contratti o termini eliminati possono essere ripristinati? → A: No. L'eliminazione è irreversibile nell'applicazione. Può restare una tombstone tecnica minima per audit, provenienza e vincoli, ma nessuna revisione, importazione o azione può ripristinare o riattivare la stessa identità logica eliminata.

## User stories

### US-004-01 — Projects

An authorized actor manages project stages and deferred decisions, and may delete a project only after explicitly deleting every linked current Expense, while revision history preserves one current project identity and the economic dataset classifies linked rows consistently.

### US-004-02 — Contracts and terms

An authorized actor manages a contract and non-overlapping term timeline with renewal and revision history, and may delete the contract after the always-visible reason prompt, whose value is required only when the current setting enables it, without deleting generated Expenses, which retain immutable deletion provenance.

### US-004-03 — Generated Actual lifecycle

A valid contract occurrence creates an Actual Da confermare. Synchronization maintains it only while system-managed; manual modification or confirmation makes it user-authoritative.

### US-004-04 — Generated-expense history

An authorized actor opens the contract and sees every expected/generated occurrence, linked current expense, confirmation/ownership state, deletion/suppression state and revision/history links.

### US-004-05 — Delete generated expense

When deleting a contract-generated expense, an authorized actor chooses whether the missing occurrence may be generated again or is explicitly suppressed.

### US-004-06 — Resume or generate one year

An authorized actor resumes a suppressed occurrence, resumes and generates immediately, or generates one compatible missing occurrence for a selected year.

## Acceptance scenarios

### AC-004-01 — Tenant and permission scope

Projects, contracts, terms, generation identities, exceptions, linked expenses, revisions and notifications remain in one tenant. Missing permission or other-tenant identifiers are denied before disclosure.

### AC-004-02 — Contract term integrity

Terms do not overlap. Billing cycle, effective dates, derived non-final end dates and renewals are validated atomically.

### AC-004-03 — Initial generation

Given one valid missing contract occurrence, synchronization creates exactly one Actual Da confermare with stable source key and records it as system-managed.

### AC-004-04 — System-managed synchronization

Given an existing generated Actual that is Da confermare and has not been manually modified, synchronization may update only fields derived from the current contract term/rule. The source identity remains unchanged.

### AC-004-05 — User-authoritative transition

Given a generated Actual, manual modification or confirmation marks it user-authoritative. Later synchronization recognizes the source key but does not overwrite the row. Authorized Feature 003 correction, revision, restore and delete remain available.

### AC-004-06 — Contract generation history

The contract page shows year, source term/rule, expected source key, linked expense, Actual confirmation state, system-managed/user-authoritative state, generation/deletion/suppression/resume actors and timestamps, plus expense and contract revision links.

### AC-004-07 — Delete with regeneration allowed

Deleting a generated expense with `regeneration allowed` removes the current expense. The source occurrence becomes missing and a future synchronization may recreate it.

### AC-004-08 — Delete and suppress

Deleting with `prevent regeneration` removes the current expense and creates one non-economic generation exception for its source key. Later synchronization skips that occurrence.

### AC-004-09 — Resume generation

`Resume generation` removes the exception; `Resume and generate now` also creates the occurrence immediately if still valid and missing.

### AC-004-10 — Generate for selected year

`Generate for year` accepts one compatible planning year, rejects an existing/suppressed/invalid occurrence unless the actor explicitly resumes it, and creates exactly one Actual Da confermare.

### AC-004-11 — Project bucket effect

Estimate/Quote linked to Approved project enter primary; Proposed and Idea enter their named buckets; Deferred/Rejected remain visible in excluded. Every Actual attributed to the year remains primary even after later project-stage change.

### AC-004-12 — Revisions and restore

Project, contract and term updates create operational revisions. Restore creates a new current revision and revalidates term overlap, tenant references, generation identities, user-authoritative occurrences and already-generated history.

### AC-004-13 — Renewal/expiry notifications

The scheduler creates deduplicated database notifications and optional synchronous email at 30/7/1 days and expiration for recipients with the configured permission. No permanent worker is required.

### AC-004-14 — Project deletion

Given a project with one or more linked current Expenses, deletion is denied atomically and identifies that linked Expenses must first be deleted through their separately authorized Feature 003 flow. After no current Expense references the project, an actor with the project delete ability is prompted for a deletion reason and may confirm irreversible deletion. The reason may be empty only when the current deletion-reason setting is optional. The operation never cascades to Expenses and retains only approved minimized project revision/tombstone/audit evidence, including the reason when supplied. No restore, revision restore or import may return the deleted project to the active domain.

### AC-004-15 — Contract deletion with generated Expenses

Given a contract with current generated Expenses, an actor with the contract delete ability is prompted for a deletion reason and confirms irreversible deletion. The reason may be empty only when the current deletion-reason setting is optional. The contract and its future generation schedule leave the active domain permanently, but no Expense is deleted. Each linked current generated Expense becomes user-authoritative and receives immutable provenance containing the contract stable identifier and title, deletion timestamp and supplied reason when present; its source key remains unchanged. Contract deletion, provenance writes and generation shutdown succeed atomically or all roll back. No restore, revision restore or import may reactivate the contract or its generation.

### AC-004-16 — Tenant deletion-reason setting

Given the global Administrator with protected `deletion-reason-setting.manage` and one explicitly selected tenant, the Administrator may switch that tenant's setting between optional and required. A tenant user, Editor, custom tenant role, missing ability or mismatched target tenant is denied before the setting is disclosed or changed. The default is optional; a change affects only later project/contract/term deletion attempts and never rewrites prior tombstones, provenance or audit. A supplied reason is trimmed, may not exceed 500 characters, and an empty result is rejected only while the setting is required.

### AC-004-17 — Contract-term deletion with generated Expenses

Given a contract term with current generated Expenses, an actor with the contract update ability explicitly marks the term for irreversible deletion, is prompted for a reason under the current tenant setting, and confirms the aggregate update. The term permanently leaves the current timeline and cannot generate future occurrences. No Expense is deleted: every linked current generated Expense becomes user-authoritative and receives immutable provenance containing the contract and term stable identifiers, contract title, term date range, deletion timestamp and supplied reason when present; every source key remains unchanged. Term deletion, provenance writes and generation shutdown succeed atomically or all roll back. Revision restore and import cannot restore the same deleted stable term identity.

## Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| FR-004-001 | Project shall require tenant, title, cost center and valid stage. | AC-004-01 |
| FR-004-010 | Project stage shall be `Idea`, `Proposed`, `Approved`, `Deferred`, or `Rejected`. | AC-004-11 |
| FR-004-011 | Project shall remain context only and shall not persist an authoritative independent economic total. | AC-004-11 |
| FR-004-012 | Estimate/Quote linked to project shall map to primary/proposed/idea/excluded according to Q-040; every Actual for the year shall remain primary regardless of later stage. | AC-004-11 |
| FR-004-015 | Deferred shall require target year and shall become Proposed when the approved promotion rule runs for that current year. | AC-004-12 |
| FR-004-020 | Contract shall require tenant, vendor, cost center and at least one non-overlapping term. | AC-004-02 |
| FR-004-021 | Term billing cycle shall be Monthly or Annual. | AC-004-02 |
| FR-004-022 | Missing non-final term end shall resolve to the day before the next term. | AC-004-02 |
| FR-004-023 | Auto-renew shall create at most one non-overlapping successor when year coverage exists. | AC-004-02 |
| FR-004-025 | Synchronization shall create one missing Actual Da confermare per valid term/year source key. | AC-004-03 |
| FR-004-026 | Generated source key shall be tenant-scoped, unique, stable and immutable. | AC-004-03 |
| FR-004-027 | A generated Actual shall be system-managed only while Da confermare and not manually modified. | AC-004-04 |
| FR-004-028 | Synchronization shall update only contract-derived fields of a system-managed occurrence and shall never overwrite a user-authoritative occurrence. | AC-004-04, AC-004-05 |
| FR-004-029 | Manual modification or confirmation shall make the generated Actual user-authoritative; confirmation shall stop synchronization but shall not remove Feature 003 version/update/delete abilities. | AC-004-05 |
| FR-004-030 | Contract detail shall expose expected/generated history, Actual confirmation/ownership state and linked expense/revision navigation. | AC-004-06 |
| FR-004-031 | Deleting a generated expense shall require an explicit regeneration choice. | AC-004-07, AC-004-08 |
| FR-004-032 | A suppressed occurrence shall use a separate non-economic generation exception and shall be skipped by synchronization. | AC-004-08 |
| FR-004-033 | Authorized actors shall be able to resume, resume-and-generate or generate one valid missing year occurrence. | AC-004-09, AC-004-10 |
| FR-004-034 | An expense shall reference at most one project or contract. | AC-004-01 |
| FR-004-035 | Contracts and projects shall not be independent economic total sources. | AC-004-03, AC-004-11 |
| FR-004-036 | Projects, contracts, terms, exceptions, notifications and generated expenses shall stay within one tenant. | AC-004-01 |
| FR-004-037 | Current non-deleted project, contract and term revisions shall support compare/restore while retaining one current record. Revision restore shall never restore or reactivate the same deleted project, contract or term logical identity. | AC-004-12, AC-004-14, AC-004-15, AC-004-17 |
| FR-004-038 | Renewal/expiry notifications shall run synchronously from Laravel scheduler without queue workers. | AC-004-13 |
| FR-004-039 | Generation, suppression, resume, manual-year and confirmation actions shall be separately permission-controlled. | AC-004-01, AC-004-05, AC-004-10 |
| FR-004-040 | Project deletion shall require the distinct project delete ability, explicit confirmation and a deletion-reason prompt, and shall be permitted only when no current Expense references the project. The reason shall be required only when the approved setting enables that requirement. Linked Expenses shall never be deleted, detached, or reassigned as a side effect; prior Expense deletion shall use the Feature 003 flow. Deletion shall be irreversible through UI, Action, revision restore and import while retaining only minimized technical evidence. | AC-004-01, AC-004-14 |
| FR-004-041 | Contract deletion shall require the distinct contract delete ability, explicit confirmation and a deletion-reason prompt. The reason shall be required only when the approved setting enables that requirement. Deletion shall irreversibly stop every future generation operation without deleting current generated Expenses. Each linked current generated Expense shall atomically become user-authoritative and retain immutable provenance with the contract stable identifier/title, deletion timestamp and supplied reason when present while preserving its source key. No UI, Action, revision restore or import may reactivate the contract. | AC-004-01, AC-004-05, AC-004-15 |
| FR-004-042 | Each tenant shall own one deletion-reason setting controlling whether the project/contract/term deletion prompt rejects an empty value. The default shall be optional; only global Administrator with protected `deletion-reason-setting.manage` may change it for an explicitly selected tenant. Tenant roles shall never receive this authority, and a change shall affect only future deletion attempts without rewriting prior evidence. | AC-004-01, AC-004-14, AC-004-15, AC-004-16, AC-004-17 |
| FR-004-043 | A project, contract or contract-term deletion reason shall be trimmed and limited to 500 characters. A blank result shall be accepted only when the current tenant setting is optional. | AC-004-14, AC-004-15, AC-004-16, AC-004-17 |
| FR-004-044 | Explicit contract-term deletion shall use the tenant deletion-reason policy, irreversibly stop future generation for that term and never delete a generated Expense. Linked current generated Expenses shall atomically become user-authoritative and retain immutable contract/term/date/deletion provenance and unchanged source keys. No UI, Action, revision restore or import may restore or reactivate the same deleted stable term identity. | AC-004-05, AC-004-15, AC-004-16, AC-004-17 |

## Business invariants

| ID | Rule | Error | Test |
|---|---|---|---|
| INV-PRJ-001 | Project stage is a closed enum. | DomainConflict | TEST-004-001 |
| INV-PRJ-002 | Deferred project has a valid same-tenant target year. | DomainConflict | TEST-004-002 |
| INV-PRJ-003 | Project stage classification never removes an Actual attributed to the year from primary. | DomainConflict | TEST-004-003 |
| INV-CON-001 | Contract terms do not overlap. | DomainConflict | TEST-004-004 |
| INV-CON-002 | Synchronization is idempotent, suppression-aware and no-overwrite. | DomainConflict | TEST-004-005 |
| INV-CON-003 | Generated source key is unique inside one tenant. | DomainConflict | TEST-004-006 |
| INV-CON-004 | Contract and project remain context/generator records, not economic total sources. | DomainConflict | TEST-004-007 |
| INV-CON-005 | A generation exception never contributes to economic totals. | DomainConflict | TEST-004-008 |
| INV-CON-006 | Manual generation cannot duplicate, overwrite or escape term/year applicability. | DomainConflict | TEST-004-009 |
| INV-CON-007 | User-authoritative generated Actual is never overwritten by synchronization. | DomainConflict | TEST-004-010 |
| INV-CON-008 | Confirmation stops automatic sync but does not make the Actual permanently immutable. | DomainConflict | TEST-004-011 |
| INV-REV-004 | Restoring a contract/project revision cannot rewrite generated-expense history or duplicate source keys. | DomainConflict | TEST-004-012 |
| INV-CON-TEN-001 | Generation cannot create or link another tenant's expense or exception. | Authorization/DomainConflict | TEST-004-013 |
| INV-PRJ-004 | A project with any current linked Expense cannot be deleted, and project deletion never cascades, detaches, or reassigns an Expense. | DomainConflict | TEST-004-014 |
| INV-CON-009 | Contract deletion never deletes a generated Expense, preserves each source key, records immutable deletion provenance and prevents every future occurrence generation. | DomainConflict | TEST-004-015 |
| INV-DEL-001 | An empty project, contract or contract-term deletion reason is accepted if and only if the current approved setting marks it optional; setting changes never rewrite prior deletion evidence. | DomainConflict | TEST-004-016 |
| INV-DEL-002 | Only global Administrator may change the explicitly selected tenant's deletion-reason setting; reason validation remains tenant-scoped and a trimmed reason never exceeds 500 characters. | Authorization/DomainConflict | TEST-004-017 |
| INV-CON-010 | Contract-term deletion never deletes or detaches generated Expense history, never changes a source key, and permanently prevents future generation from the deleted term. | DomainConflict | TEST-004-018 |
| INV-DEL-003 | The same deleted project, contract or term logical identity never re-enters the active domain through UI, Action, revision restore, import or synchronization; retained tombstones are evidence only. | DomainConflict | TEST-004-019 |

## Out of scope

- contracts or projects contributing directly to totals;
- automatic regeneration after explicit suppression;
- bulk manual generation that bypasses per-occurrence validation;
- silent overwrite of manually modified or confirmed generated Actual;
- cascade deletion, detachment, or reassignment of linked Expenses when deleting a project;
- cascade deletion of generated Expenses when deleting a contract;
- cascade deletion of generated Expenses when deleting a contract term;
- permanent Actual immutability after confirmation;
- queued/real-time notifications;
- role-name authorization branches.

## Clarification result

Q-008, Q-023, Q-035, Q-040, Q-041 and PD-GEN-001 remain closed. All six decisions in the 2026-08-04 clarification session are encoded and propagated through the specification, plan, data model, contracts, tasks, checklists, permission catalogue, and cross-feature registries.
