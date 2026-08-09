# Feature 012 — BudgetVersion

Status: `PROPOSED TARGET — persistence/domain foundation may exist; no implemented API capability`

## Problema

Il Budget corrente è rolling. Serve conservare deliberatamente stati storici, manuali o approvati
senza duplicare il dataset corrente o confonderli con operational revisions.

## Obiettivo

Creare, pubblicare, consultare, selezionare come riferimento e confrontare BudgetVersion
immutabili per un singolo Tenant e planning year.

## User stories

### US-012-01 — Creare una draft da Budget corrente

L'utente autorizzato cattura uno snapshot consistente dell'attuale dataset e lo nomina.

### US-012-02 — Inserire evidenza manuale

L'utente crea una draft manuale total-only, partial o full quando lo storico disponibile non
consente di ricostruire ogni dimensione.

### US-012-03 — Pubblicare

L'utente pubblica una draft valida. Da quel momento contenuto e checksum sono immutabili.

### US-012-04 — Selezionare riferimento

Un Tenant/year può indicare una published BudgetVersion come riferimento di confronto senza
sostituire il Budget corrente.

### US-012-05 — Confrontare

L'utente confronta current-vs-version o version-vs-version e vede aggiunte, rimozioni, variazioni e
dimensioni non disponibili senza valori inventati.

## Requisiti

- FR-012-001: BudgetVersion appartiene a un solo Tenant e planning year.
- FR-012-002: kind è `manual`, `approved` o `snapshot`.
- FR-012-003: source mode è `manual` o `current_snapshot`.
- FR-012-004: stato è `draft` o `published`.
- FR-012-005: nome normalizzato è unico nel Tenant/year.
- FR-012-006: current capture deve rappresentare un dataset consistente e ordinato dello stesso
  economic kernel usato dal Budget corrente.
- FR-012-007: lo snapshot conserva Net, VAT, Gross, official basis, currency, contesto, dimensioni
  disponibili, source references quando presenti e exact summary.
- FR-012-008: la draft manuale può essere total-only, partial o full; dimensioni mancanti sono
  `unavailable`, non zero e non inventate.
- FR-012-009: nessuna versione storica viene marcata `approved` per inferenza; richiede scelta/evidenza
  esplicita dell'actor.
- FR-012-010: publish congela contenuto, actor/time, format version e checksum SHA-256 deterministico.
- FR-012-011: una published version non è modificabile, rebuildabile, ripristinabile tramite
  operational revision o eliminabile al launch.
- FR-012-012: una correzione a una published version produce una nuova draft/version.
- FR-012-013: selezionare un reference non modifica Expense o Budget current data.
- FR-012-014: reference deve essere published e dello stesso Tenant/year.
- FR-012-015: comparison è read-only e richiede stesso Tenant; cross-year è ammesso soltanto tra
  dimensioni compatibili.
- FR-012-016: percentuale di variazione resta unavailable quando il denominatore è zero o la
  semantica non è definita.
- FR-012-017: ogni operazione è permission-controlled e tenant-scoped.
- FR-012-018: BudgetVersion non entra nei totali correnti se non viene esplicitamente selezionata
  come dataset di confronto/versione.

## Acceptance

- modificare Expense dopo publish non cambia la version;
- stessa input canonica produce checksum deterministico;
- una published version rifiuta update/delete/restore;
- manual partial non inventa righe o label;
- confronto non modifica nessuna sorgente;
- altro Tenant non può vedere o selezionare la version.

## Fuori scope

- totale Budget corrente persistito e sincronizzato;
- uso del model revision package come BudgetVersion;
- edit in place di published version;
- approval inferita;
- cross-Tenant comparison.
