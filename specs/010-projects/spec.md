# Feature 010 — Projects end-to-end

Status: `PROPOSED TARGET — no implemented Project application capability at baseline`

## Problema

Il motore economico conosce le regole di classificazione dei Project, ma manca una capability
applicativa completa per crearli, gestirne lo stage, collegarli alle Expense e utilizzarli in
Budget e Report.

## Obiettivo

Permettere a un utente autorizzato di gestire Project nel frontend e nel backend e vedere il loro
effetto di classificazione sullo stesso economic dataset già usato da Dashboard, Budget e Report.

## User stories

### US-010-01 — Gestire Project

Creare, consultare e modificare un Project tenant-owned con title, Cost Center e stage.

### US-010-02 — Gestire Deferred

Un Project Deferred possiede un target year valido e può essere promosso a Proposed dalla regola
operativa approvata.

### US-010-03 — Collegare Expense e Budget

Le Expense collegate a Project vengono classificate nei bucket economici senza aggiungere un
secondo importo Project.

### US-010-04 — Revisioni

Un Project corrente usa revision history/compare/restore secondo la feature 009.

### US-010-05 — Eliminare Project

Un Project può essere eliminato definitivamente soltanto dopo che tutte le Expense correnti
collegate sono state eliminate esplicitamente con il loro normale flusso.

## Requisiti

- FR-010-001: Project appartiene a un solo Tenant e richiede title, Cost Center e stage.
- FR-010-002: stage è uno tra `Idea`, `Proposed`, `Approved`, `Deferred`, `Rejected`.
- FR-010-003: Deferred richiede un target planning year dello stesso Tenant.
- FR-010-004: la promozione automatica/operativa da Deferred a Proposed deve essere idempotente.
- FR-010-005: Project non persiste o espone un totale economico autorevole indipendente.
- FR-010-006: Estimate/Quote senza Project o con Project Approved entrano in `primary`.
- FR-010-007: Estimate/Quote con Proposed entrano in `proposed`; con Idea in `idea`;
  Deferred/Rejected restano visibili in `excluded`.
- FR-010-008: ogni Actual attribuito all'anno resta in `primary` anche se lo stage Project cambia.
- FR-010-009: `potential = primary + proposed + idea` è non ufficiale.
- FR-010-010: il collegamento Project deve essere tenant-scoped e non può creare cross-Tenant
  relation.
- FR-010-011: Project history/restore segue la feature 009.
- FR-010-012: la cancellazione è vietata finché esiste una current Expense collegata.
- FR-010-013: la cancellazione non può cancellare, staccare o riassegnare Expense in cascata.
- FR-010-014: il prompt di reason è sempre presente; il valore è obbligatorio solo quando il
  setting Tenant della feature 008 lo richiede ed è massimo 500 caratteri dopo trim.
- FR-010-015: la cancellazione è terminale per la stessa logical identity e non può essere
  annullata da UI, restore, import o sincronizzazione.
- FR-010-016: Dashboard, Budget e Report consumano la classificazione Project attraverso il
  medesimo economic dataset, senza nuovo motore o fonte monetaria.

## Acceptance

- CRUD e stage rispettano permission e tenant isolation;
- la profondità/semantica del Cost Center resta quella implementata;
- un Project linked ad Expense non è eliminabile;
- dopo la rimozione esplicita delle Expense collegate, delete è terminale e auditabile;
- i bucket del Budget coincidono con le regole sopra e non alterano gli Actual;
- nessun totale Project viene sommato separatamente.

## Dipendenze

- feature 008 per deletion-reason setting;
- feature 009 per revision experience;
- Expense e economic kernel correnti.

## Fuori scope

- project accounting separato dal dataset Expense;
- cascade delete;
- cross-Tenant Project;
- workflow di approval aggiuntivi non già definiti;
- nuove formule economiche oltre i bucket approvati.
