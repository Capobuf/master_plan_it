# Feature 009 — Operational revisions

Status: `PROPOSED TARGET — partial backend foundation exists; user capability incomplete`

Nota dipendenza Feature 017: la proiezione annuale read-only a cutoff e i revision batch atomici
sono implementati. Restano in questo scope compare/restore operativo uniforme dei record correnti;
la proiezione storica annuale non è un restore e non sostituisce questa feature.

## Problema

Vendor e Cost Center hanno già operazioni API di history/restore e Contract espone history, mentre
Expense history/restore non è una capability API completa e il frontend non offre un'esperienza
uniforme di compare/restore. La revisione operativa deve restare distinta da audit e BudgetVersion.

## Obiettivo

Fornire un'esperienza end-to-end coerente per consultare, confrontare e, quando consentito,
ripristinare revisioni di record correnti senza duplicare record nel dominio corrente.

## User stories

### US-009-01 — Storico Expense

Un actor autorizzato apre una Expense, vede le revisioni come operazioni logiche e confronta una
revisione con lo stato corrente.

### US-009-02 — Ripristino Expense

Un actor autorizzato ripristina una revisione valida di una Expense corrente. Il restore crea una
nuova revisione corrente e non riscrive la storia.

### US-009-03 — Storico e restore Contract

Un actor autorizzato consulta e confronta revisioni Contract/term correnti e ripristina solo stati
che rispettano gli invarianti correnti e non riattivano sorgenti terminalmente eliminate.

### US-009-04 — Esperienza uniforme

Le superfici di revision history di Vendor, Cost Center, Expense e Contract usano una convenzione
coerente di actor, timestamp, operation, changed fields e source revision.

## Requisiti

- FR-009-001: un logical record deve apparire una sola volta nel dominio corrente.
- FR-009-002: una revisione non è una sorgente economica corrente.
- FR-009-003: history deve essere permission-controlled e tenant-scoped.
- FR-009-004: aggregate change deve poter essere rappresentato come un'unica operazione logica
  anche quando più model snapshot partecipano alla modifica.
- FR-009-005: restore crea una nuova revisione e non modifica o elimina revisioni successive.
- FR-009-006: restore revalida permission, Tenant, riferimenti correnti, money, source key e
  optimistic concurrency.
- FR-009-007: una Expense eliminata definitivamente non è ripristinabile.
- FR-009-008: un Project, Contract o Contract term terminalmente eliminato non può rientrare nel
  dominio attivo tramite revision restore.
- FR-009-009: Contract restore non può modificare o duplicare generated Expense, source key,
  suppression o generation history.
- FR-009-010: revision data e audit devono restare meccanismi distinti.
- FR-009-011: questa feature non deve inventare attachment history per revisioni Expense
  precedenti alla futura attivazione degli allegati; la feature 011 gestirà il backfill dei
  manifest prima di abilitare upload.

## Acceptance

- altro Tenant o permission mancante non rivela l'esistenza della revisione;
- un restore valido produce una nuova revisione e lascia immutata la revisione sorgente;
- un restore con riferimento inattivo/non valido o stale lock fallisce atomically;
- nessuna revisione entra nei totali correnti;
- un terminally deleted Contract/term non può essere restaurato;
- la history non usa audit come snapshot business.

## Fuori scope

- BudgetVersion;
- planning-year revision restore;
- audit export;
- ripristino di identity terminalmente eliminate;
- allegati e payload versionati, coperti dalla feature 011.
