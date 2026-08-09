# Feature 014 — Exports and print

Status: `PROPOSED TARGET — current datasets implemented; output capability not implemented`

## Problema

Budget e Report sono consultabili ma mancano output CSV/XLSX e stampa con lo stesso scope e gli
stessi valori del dataset visualizzato.

## Obiettivo

Consentire output completi e verificabili di un singolo Tenant senza ricalcolo client, truncation,
download parziali o ampliamento implicito dello scope.

## User stories

### US-014-01 — Export filtrato

L'utente esporta esattamente il current filtered result.

### US-014-02 — Export completo Report/anno

L'utente sceglie esplicitamente `complete_report_year` e ottiene il dataset completo autorizzato
per quel Report/anno, non per altri Tenant.

### US-014-03 — Stampa

L'utente apre una vista stampabile React equivalente al dataset selezionato e usa il browser per
stampa/Save as PDF.

### US-014-04 — Output di dataset alternativi

Quando BudgetVersion e Scenario sono disponibili, gli output supportano esplicitamente quelle
sorgenti senza confonderle con il current dataset.

## Requisiti

- FR-014-001: ogni output economico contiene esattamente un Tenant.
- FR-014-002: l'actor sceglie esplicitamente `filtered` oppure `complete_report_year`.
- FR-014-003: `filtered` preserva filtri, order, dataset identity, basis, currency, locale e timezone.
- FR-014-004: `complete_report_year` rimuove soltanto i filtri di narrowing definiti come transient,
  preservando Tenant, Report, year, dataset identity, authorization, order, currency, locale,
  timezone e basis.
- FR-014-005: output metadata identifica lo scope scelto; nessun output amplia o restringe lo scope
  silenziosamente.
- FR-014-006: CSV e XLSX non hanno un row cap applicativo e non troncano il dataset.
- FR-014-007: CSV e XLSX processano incrementally l'ordered dataset senza materializzare l'intera
  collection in memoria.
- FR-014-008: la generazione usa un artifact privato request-scoped; il download inizia solo dopo
  finalizzazione completa.
- FR-014-009: failure di query/writer/storage/finalization espone zero artifact parziali e attiva
  cleanup; cleanup failure deve essere visibile e registrato.
- FR-014-010: successo rimuove l'artifact temporaneo dopo delivery.
- FR-014-011: CSV usa UTF-8 e decimal string esatte.
- FR-014-012: XLSX non contiene formule autorevoli.
- FR-014-013: la presentazione di stampa appartiene al frontend React; Laravel resta API-only e
  proprietario di dataset, authorization e file delivery.
- FR-014-014: non viene introdotto un server PDF renderer al launch.
- FR-014-015: audit output registra solo metadata sicuri: actor, Tenant, source dataset, scope,
  filtri, basis, output type e correlation ID, non il payload completo.
- FR-014-016: per la stessa sorgente/scope, screen data, CSV, XLSX e stampa devono riconciliare
  esattamente.
- FR-014-017: audit export resta fuori scope.

## Performance gate approvato

Fixture di riferimento: 10.000 current row per Tenant/year.

Sul profilo hosting finale verificato:

- page/report p95 ≤ 2 s;
- CSV ≤ 10 s;
- XLSX ≤ 20 s;
- print rendering ≤ 10 s;
- peak PHP memory ≤ 128 MiB;
- massimo 5 query SQL per request misurata.

In CI sono bloccanti parity, scope, ordering, memory e query count. I tempi assoluti su runner
variabile sono evidenza, non gate. Il benchmark non può introdurre row cap, materialized current
total o modifica semantica.

## Acceptance

- filtered export coincide con lo screen scope;
- complete export non contiene righe di altri Tenant;
- dataset oltre 10.000 row non viene troncato per una soglia applicativa;
- errore a metà generazione produce nessun download parziale;
- print mostra gli stessi valori server-calculated;
- BudgetVersion/Scenario, quando selezionati, restano chiaramente identificati come dataset diversi.

## Fuori scope

- audit export;
- server-generated PDF;
- cross-Tenant export economico;
- formule economiche nel frontend;
- row limit applicativo.
