# Feature 003 — Expense domain

Status: `CLARIFIED AND APPROVED; ROLLING ANALYSIS ACTIVE; IMPLEMENTATION IN PROGRESS`
Logical owner: Product Owner with domain approval  
Actor: tenant user with explicit permission  
Dependencies: Feature 002 and Feature 007  
Additional decisions: Q-035, Q-037, Q-038

## Problem

The target must preserve verified monetary, VAT, funding, allocation and source-row semantics while exposing one current expense identity plus true revision history. Estimate, Quote and Actual must remain independent economic records. Actual rows are correctable and deletable, and contract confirmation must stop automatic overwrite without making the record permanently immutable.

## Objective

Create, edit, version, restore, delete, report, print and export tenant-owned expenses and rows with exact calculations, stable identity, explicit permissions, independent economic types, visible Actual confirmation state and complete exclusion of deleted or historical revisions from current totals.

## Clarifications

### Session 2026-08-04

- Q: Quali formati e quale dimensione massima sono ammessi per ogni allegato di spesa? → A: PDF, JPEG, PNG, CSV e XLSX, massimo 10 MiB per file. Estensione e MIME rilevato devono corrispondere; ogni altro formato è rifiutato.
- Q: A quale elemento può essere associato un allegato? → A: Alla spesa complessiva oppure a una singola riga; ogni file ha esattamente un solo genitore.
- Q: Quando si ripristina una revisione di una spesa o riga, cosa accade agli allegati? → A: Ogni revisione conserva nello storage privato versionato i payload necessari a ricostruire l'insieme completo degli allegati; il ripristino ricrea atomicamente dati e allegati esattamente come in quella revisione.
- Q: Cosa accade ai payload storici quando viene eliminato un allegato o l'intera spesa? → A: La rimozione di un allegato o di una riga conserva i payload storici finché esiste la spesa, così le revisioni restano ripristinabili. La cancellazione definitiva della spesa elimina tutti i payload e conserva solo metadati minimi e checksum.
- Q: Quale quota limita lo storage degli allegati versionati? → A: 2 GiB per tenant come valore predefinito, modificabile separatamente per ciascun tenant solo dall'Amministratore globale; la quota conta payload correnti e storici. Se viene ridotta sotto l'uso corrente, nessun file è cancellato, ma nuovi upload e ripristini che producono payload restano bloccati finché l'uso non scende o la quota non aumenta.
- Q: Una revisione che non modifica gli allegati deve duplicarne fisicamente i payload? → A: No. Ogni revisione conserva un manifest completo, ma le sue voci riutilizzano le versioni immutabili dei payload invariati. Una modifica dei soli dati non crea nuovi byte, non aumenta l'uso quota e non viene bloccata solo perché il tenant è già sopra quota.
- Q: La quota allegati può essere impostata a zero? → A: Sì. Zero byte è un valore valido: non elimina i payload esistenti e blocca soltanto le operazioni che creerebbero nuovi byte; revisioni e ripristini che riutilizzano integralmente payload già presenti restano consentiti.
- Q: Esiste un tetto massimo applicativo per la quota allegati? → A: No. Non viene introdotto alcun massimo di prodotto; sono accettati tutti i valori non negativi rappresentabili dal tipo tecnico persistito, con parsing e confronti interi esatti e senza float.

## User stories

### US-003-01 — Current expense register

An authorized actor sees each current expense once, with server-calculated Net, VAT and Gross totals and no superseded/deleted copies.

### US-003-02 — Independent economic rows

An authorized actor adds Estimate, Quote or Actual rows according to the cost information available, without being forced through a progression workflow.

### US-003-03 — Actual confirmation

An authorized actor distinguishes Actual `Da confermare` from `Confermata` and confirms a valid row without losing later versioned correction/delete abilities.

### US-003-04 — Revision history

An authorized actor compares revisions and restores a valid earlier state, including its complete attachment set, as a new current revision.

### US-003-05 — Delete expense or row

An authorized actor deletes a current expense or row after explicit confirmation; it disappears from the active domain and all current outputs while minimized revision/audit evidence remains.

### US-003-06 — Private attachments

An authorized actor uploads and accesses private attachments on either the whole Expense or one ExpenseRow, with exactly one current same-tenant parent, only when each file satisfies the approved type and size policy.

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

Given an earlier revision, restore revalidates tenant ownership, current master-data availability rules, money, VAT, dates, funding, project/contract links, source keys, confirmation state, attachment payload availability/checksums and concurrency. Success creates a new revision and atomically restores the exact attachment set captured by that revision; failure leaves current data and files unchanged.

### AC-003-07 — Aggregate revision

Given one save that changes an Expense and several rows, the UI shows one logical revision operation linked by `revision_batch_uuid`, while each persisted model version remains traceable.

### AC-003-08 — Tenant and permission denial

Missing permission, inactive tenant, deactivated user or other-tenant identifier fails before protected data or revision details are disclosed.

### AC-003-09 — Validation and rollback

Invalid money, VAT, dates, funding, references, duplicate source keys or stale `lock_version` rolls back the entire aggregate and file operation.

### AC-003-10 — Attachment validation

Given an authorized attachment upload, `.pdf`, `.jpg`/`.jpeg`, `.png`, `.csv`, and `.xlsx` files are accepted only when the server-detected MIME matches the extension and the file size is at most 10,485,760 bytes. A mismatched, oversized, empty, or unlisted file is rejected before final private storage, and aggregate rollback leaves no temporary or final orphan.

### AC-003-11 — Attachment parent

Given an authorized upload, the actor selects either the current Expense or one current row belonging to that Expense as the attachment parent. The attachment receives exactly that one parent and the same tenant. A missing, deleted, foreign-tenant, unrelated, or multiply specified parent is rejected before file finalization without disclosing protected metadata.

### AC-003-12 — Attachment revision restore

Given a valid earlier Expense aggregate revision, its manifest references one immutable private payload version for every attachment belonging to the Expense and its captured rows. A new revision reuses an existing payload version whenever that attachment's bytes are unchanged; it creates and reserves quota only for genuinely new payload bytes. Restore verifies tenant, authorization, file availability, size, MIME and checksum, then creates a new current revision whose data and active attachment set exactly match that manifest. Attachments added later leave the active set but remain in their own immutable revision evidence. Any missing, corrupt or unauthorized payload rolls back the complete data-and-file restore.

### AC-003-13 — Attachment and parent deletion

Given an authorized attachment or ExpenseRow deletion, the removed attachment leaves the active set and the aggregate records a new revision, while immutable payload versions remain privately available to revisions for as long as the Expense exists. Given authorized permanent Expense deletion, every current and historical attachment payload is deleted with compensating cleanup; only minimized filename, MIME, size, checksum, actor and correlation metadata may remain, and the deleted Expense cannot be restored from operational revisions.

### AC-003-14 — Attachment storage quota

Given a tenant attachment-storage quota defaulting to 2 GiB (2,147,483,648 bytes), zero is valid and no application-defined maximum exists; every non-negative value representable by the persisted unsigned integer is accepted using exact integer/string parsing. Every upload or revision restore that would create a new physical payload calculates the resulting total of that tenant's distinct, non-purged current and historical payload-version bytes before file finalization. Repeated manifest references to the same immutable payload version are counted once. An operation that would exceed the configured quota fails atomically without temporary or final orphan files. At zero or above-current-usage quota, a data-only revision or restore that reuses every required payload version creates zero payload bytes and remains allowed. Only the global Administrator can edit each tenant's quota separately. Reducing it, including to zero, deletes nothing and blocks only operations that would produce payload bytes until usage is within quota or the quota is raised.

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
| FR-003-064 | Each attachment shall be at most 10 MiB (10,485,760 bytes) and shall use one approved extension/MIME pair: `.pdf`/`application/pdf`, `.jpg` or `.jpeg`/`image/jpeg`, `.png`/`image/png`, `.csv`/`text/csv`, or `.xlsx`/`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`. Server-detected MIME and extension shall match; empty, mismatched, oversized, and unlisted files shall be rejected before final private storage. | AC-003-10 |
| FR-003-065 | An attachment shall belong to exactly one current same-tenant parent: either an Expense or one ExpenseRow that belongs to that Expense. Upload, view, download, and delete shall require both the exact attachment ability and authorization for that parent. | AC-003-08, AC-003-11 |
| FR-003-066 | Before attachment capability is enabled, T003-024 shall backfill a complete verified empty manifest for every pre-capability data-only Expense revision; every subsequent aggregate revision shall atomically capture a complete attachment manifest whose entries reference immutable private payload versions sufficient to reconstruct the Expense and captured ExpenseRow attachment set. No upload is enabled before the backfill succeeds. An unchanged attachment shall reuse its existing payload version rather than duplicate bytes. Revision/audit metadata shall contain only file references, names, MIME, sizes, checksums and actors, never inline payloads. | AC-003-06, AC-003-12 |
| FR-003-067 | Restoring a revision shall atomically restore its business data and exact attachment set as a new current revision after validating authorization, tenant, file availability, approved type, size and checksum; any failure shall leave both current data and files unchanged. | AC-003-06, AC-003-12 |
| FR-003-068 | Removing an attachment or deleting an ExpenseRow shall remove affected files from the active attachment set and create an aggregate revision while retaining immutable private payload versions for the lifetime of the Expense. Permanent Expense deletion shall remove all current and historical payloads with compensating cleanup and retain only minimized metadata and checksums; it shall not be operationally restorable. | AC-003-05, AC-003-13 |
| FR-003-069 | Each tenant shall have a non-negative attachment payload quota in bytes defaulting to 2 GiB (2,147,483,648 bytes), editable separately only by the global Administrator through platform settings; zero is valid and no application-defined maximum shall be added beyond exact technical representability. Each distinct non-purged immutable payload version shall count once; manifest references and metadata shall not add usage. Upload and restore shall reserve/check only genuinely new payload bytes before finalization and fail atomically when resulting usage would exceed quota. Reducing quota, including to zero, deletes nothing and blocks only operations that would create payload bytes; data-only revisions/restores that reuse all payload versions remain allowed. | AC-003-10, AC-003-12, AC-003-14 |

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
| INV-EXP-TEN-001 | Expense, rows, references, attachments, revisions and audit share one tenant. | Authorization/DomainConflict | TEST-003-016 |
| INV-ATT-001 | An attachment reaches final private storage only when its extension, server-detected MIME, non-zero size, and 10 MiB limit satisfy FR-003-064; rollback leaves no orphan file. | Validation/DomainConflict | TEST-003-017 |
| INV-ATT-002 | An attachment has exactly one current Expense or ExpenseRow parent and shares that parent's tenant; foreign, deleted, unrelated, or multiple parents fail before final storage or metadata disclosure. | Authorization/DomainConflict | TEST-003-018 |
| INV-ATT-003 | Each aggregate revision can reconstruct its complete attachment set from immutable private payload versions without placing payload bytes in revision or audit metadata. | DomainConflict | TEST-003-019 |
| INV-ATT-004 | Revision restore changes business data and its active attachment set in one atomic operation; missing, corrupt or unauthorized payload prevents every restore side effect. | DomainConflict | TEST-003-020 |
| INV-ATT-005 | Attachment or row deletion preserves versioned payloads only while the parent Expense exists; permanent Expense deletion purges every payload and prevents operational restore while retaining only minimized evidence. | DomainConflict | TEST-003-021 |
| INV-ATT-006 | Upload and revision restore never make tenant payload usage exceed the configured non-negative quota, including concurrent operations; a zero quota permits no new payload bytes and purges nothing. | DomainConflict | TEST-003-022 |
| INV-ATT-007 | At attachment-capability activation every pre-capability data-only revision has a verified empty manifest and every subsequent revision has a complete manifest; unchanged attachment bytes reuse one immutable payload version, consume no additional quota and remain usable by data-only revision/restore even when existing usage is above quota. | DomainConflict | TEST-003-023 |

## Migration notes

Legacy `Active/Replaced/Cancelled` rows remain source evidence. `/speckit.plan` must define deterministic migration into one current logical row plus revision/audit evidence where feasible, without changing accepted current totals. A legacy Actual is not migrated into a permanently immutable target state.

Legacy Estimate/Quote/Actual relationships must not be converted into a mandatory workflow unless the source contains an explicit same-cost identity that can be preserved deterministically.

## Out of scope

- showing superseded or deleted copies in ordinary expense registers;
- mandatory Estimate → Quote → Actual workflow;
- physical audit/revision payload as a report source;
- automatic restore that bypasses current validation;
- operational restoration of a permanently deleted Expense or its attachment payloads;
- role-name conditionals instead of permissions;
- silent deletion, confirmation or restore without audit/correlation.
- attachment types other than PDF, JPEG, PNG, CSV, and XLSX, or files larger than 10 MiB.

## Clarification result

Q-006, Q-018, Q-024, Q-035, Q-037 and Q-038 remain resolved. All eight decisions in the 2026-08-04 clarification session are encoded and propagated through the specification, plan, data model, contracts, tasks, checklists, and cross-feature registries.
