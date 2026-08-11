# Stato funzionale

Stato verificato dopo l'implementazione della Feature 020.

Questo file è l'unico riepilogo manuale di stato. Non deve contenere task o cronologia.

## Disponibile alla baseline

| Area | Backend/API | Frontend |
|---|---|---|
| Login, logout, sessione, contesto Tenant | Implementato | Implementato |
| Tenant | CRUD/lifecycle implementato | Implementato |
| Utenti e ruoli | Implementato | Implementato |
| Planning year / Budget annuale | Lifecycle, approvazioni atomiche, chiusura | Implementato |
| Fornitori | CRUD/lifecycle/history/restore | Implementato |
| Centri di costo | CRUD/tree/lifecycle/history/restore | Implementato |
| Spese | Planning selezionato, filtri Register e totali riconciliati, detail year-scoped, bulk atomico, Actual immediato, close/reopen, move e crediti | Workspace operativo con anno globale, colonne utente, selezione/expander, object detail ed editor ERP responsive |
| Contratti | CRUD/term/history | Implementato |
| Generazione contratti | Una Quote annuale, Project, sync protetto e differenza attesa | Implementato |
| Progetti | CRUD/Deferred/history/compare/restore/delete terminale | Implementato |
| Collegamento Spesa-Progetto/Contract | Tenant-safe e coerente con il Project Contract | Implementato |
| Dashboard | Planning selezionato e Actual immediati | Implementato |
| Budget corrente | Riepilogo/dettaglio annuale e warning closed | Implementato |
| Report economico | Filtri tenant-safe, summary riconciliato, cinque raggruppamenti e dataset analitici non paginati | Filtri automatici, KPI, grafici e dettaglio responsive implementati |
| Budget/Report storico | Cutoff Tenant, batch atomici, tombstone, read-only | Implementato |

Il file globale `docs/api/openapi-v1.yaml` non è presente nel repository corrente. Fino alla sua
introduzione completa, i contratti verificabili sono i contract feature-local in `specs/*/contracts/`,
le route e i Resource testati. Non va dichiarato aggiornato un OpenAPI globale inesistente.

## Da implementare

| Priorità logica | Slice verticale | Spec Kit |
|---:|---|---|
| 1 | Operazioni piattaforma, impostazioni, audit e notifiche | `specs/008-platform-operations` |
| 2 | History/compare/restore operativo; distinto dalla proiezione annuale read-only già disponibile | `specs/009-operational-revisions` |
| 3 | Allegati delle spese con revisioni e quota | `specs/011-expense-attachments` |
| 4 | BudgetVersion, riferimento e confronto | `specs/012-budget-versions` |
| 5 | Scenari what-if | `specs/013-scenarios` |
| 6 | Export CSV/XLSX e stampa | `specs/014-exports-and-print` |
| 7 | Migrazione legacy e portabilità Tenant | `specs/015-migration-and-portability` |
| 8 | Backup/restore, scheduler, release e deployment | `specs/016-backup-and-operations` |

L'ordine è una dipendenza tecnica iniziale, non una promessa di priorità prodotto. Il Product Owner
può cambiare l'ordine purché le dipendenze della slice scelta siano soddisfatte.

## Gap verificati ma non ancora specificati

Le API correnti non implementano:

- ricerca testuale dei Contract tramite `q`;

Gli artefatti approvati non definiscono abbastanza semantica prodotto per trasformarli
automaticamente in requisito. Non sono quindi inclusi negli Spec Kit senza una decisione del
Product Owner.

## Evidenze esterne ancora necessarie per il cutover

- export reale del sistema legacy con anomalie e campioni;
- capacità e vincoli dell'hosting finale;
- inventario approvato dei Report legacy che richiedono parità.

Queste sono evidenze operative, non domande di prodotto da indovinare.
