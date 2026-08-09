# Feature 013 — Scenarios

Status: `PROPOSED TARGET — not implemented; /speckit.clarify required before plan`

## Problema

Gli scenari what-if devono consentire simulazioni condivise senza modificare Expense o valori
ufficiali. Le decisioni approvate definiscono ownership e isolamento, ma non definiscono ancora la
semantica esatta con cui un utente modifica i valori di uno Scenario.

## Obiettivo

Fornire Scenario persistenti, tenant-owned, condivisi e chiaramente non ufficiali, selezionabili
soltanto in viste e confronti espliciti.

## User stories

### US-013-01 — Creare uno Scenario

Un utente autorizzato crea uno Scenario per Tenant/year con nome e descrizione.

### US-013-02 — Modificare ipotesi

Un utente modifica le ipotesi dello Scenario senza scrivere nelle Expense correnti.

### US-013-03 — Consultare e confrontare

Un utente autorizzato confronta Scenario e dataset corrente, e quando disponibile una
BudgetVersion, mantenendo sempre visibile la sorgente.

### US-013-04 — Archiviare o eliminare

La lifecycle dello Scenario è permission-controlled e non altera dati ufficiali.

## Requisiti già approvati

- FR-013-001: Scenario appartiene a un solo Tenant e planning year.
- FR-013-002: Scenario è persistente e condiviso nel Tenant, non privato per user.
- FR-013-003: Scenario deve essere visibilmente non ufficiale.
- FR-013-004: Scenario e le sue row non possono modificare Expense, Budget corrente o published
  BudgetVersion.
- FR-013-005: Scenario entra in calcoli/viste soltanto quando esplicitamente selezionato.
- FR-013-006: Net, VAT e Gross restano valori decimali esatti.
- FR-013-007: source/current references, quando usate, devono restare tenant-scoped.
- FR-013-008: permissions distinte controllano view, create, update, archive/delete e compare.
- FR-013-009: le Scenario row non entrano mai nei totali ufficiali correnti.
- FR-013-010: non deve essere introdotto un secondo economic engine; la risoluzione dello Scenario
  produce un dataset alternativo esplicito compatibile con il confronto.

## OPEN QUESTION

Gli artefatti approvati non stabiliscono come l'utente esprima concretamente una modifica Scenario,
per esempio:

- sostituzione di valori esistenti;
- aggiunta/rimozione di costi simulati;
- variazioni assolute o percentuali;
- combinazioni delle precedenti.

Questa scelta cambia dati, UX e semantica dei confronti. Deve essere decisa dal Product Owner con
`/speckit.clarify` prima di `/speckit.plan`.

## Acceptance già definibile

- Scenario di Tenant A non compare a Tenant B;
- nessuna operazione Scenario scrive su Expense;
- Dashboard/Budget ufficiali non cambiano quando cambia uno Scenario;
- la UI identifica sempre Scenario come non ufficiale;
- il confronto non sostituisce la sorgente corrente.

## Fuori scope

- Scenario privati per user;
- cross-Tenant Scenario;
- modifica diretta dei record ufficiali;
- secondo motore economico;
- semantica di editing non ancora approvata.
