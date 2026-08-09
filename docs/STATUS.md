# Stato funzionale

Stato verificato sulla Feature 010 derivata da
`laravel-replatform@7132a39271b31479b9ad477b0ed212bbfc6359a9`.

Questo file è l'unico riepilogo manuale di stato. Non deve contenere task o cronologia.

## Disponibile alla baseline

| Area | Backend/API | Frontend |
|---|---|---|
| Login, logout, sessione, contesto Tenant | Implementato | Implementato |
| Tenant | CRUD/lifecycle implementato | Implementato |
| Utenti e ruoli | Implementato | Implementato |
| Planning year | List/create/deactivate/reactivate | Implementato |
| Fornitori | CRUD/lifecycle/history/restore | Implementato |
| Centri di costo | CRUD/tree/lifecycle/history/restore | Implementato |
| Spese | Register/detail/create/update/delete/confirm Actual | Implementato |
| Contratti | CRUD/term/history | Implementato |
| Generazione contratti | Sync/generate/suppress/resume/delete generated Expense | Implementato |
| Progetti | CRUD/Deferred/history/compare/restore/delete terminale | Implementato |
| Collegamento Spesa-Progetto | Tenant-safe, esclusivo rispetto al Contract | Implementato |
| Dashboard | Dataset tenant | Implementato |
| Budget corrente | Dataset rolling con bucket Project server-side | Implementato |
| Report economico | Dataset paginato con bucket Project server-side | Implementato |

Il file globale `docs/api/openapi-v1.yaml` non è presente nel repository corrente. Fino alla sua
introduzione completa, i contratti verificabili sono i contract feature-local in `specs/*/contracts/`,
le route e i Resource testati. Non va dichiarato aggiornato un OpenAPI globale inesistente.

## Da implementare

| Priorità logica | Slice verticale | Spec Kit |
|---:|---|---|
| 1 | Operazioni piattaforma, impostazioni, audit e notifiche | `specs/008-platform-operations` |
| 2 | Revision history/compare/restore operativo dove ancora mancante | `specs/009-operational-revisions` |
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
- ricerca testuale e filtro Cost Center nel register Expense.

Gli artefatti approvati non definiscono abbastanza semantica prodotto per trasformarli
automaticamente in requisito. Non sono quindi inclusi negli Spec Kit senza una decisione del
Product Owner.

## Evidenze esterne ancora necessarie per il cutover

- export reale del sistema legacy con anomalie e campioni;
- capacità e vincoli dell'hosting finale;
- inventario approvato dei Report legacy che richiedono parità.

Queste sono evidenze operative, non domande di prodotto da indovinare.
