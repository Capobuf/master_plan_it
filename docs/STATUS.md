# Stato funzionale

Baseline verificata: `laravel-replatform@8f0f5660b409b562d354589d9e00012f31df8ef2`.

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
| Dashboard | Dataset tenant | Implementato |
| Budget corrente | Dataset rolling | Implementato |
| Report economico | Dataset paginato | Implementato |

Il contratto esatto delle operazioni implementate resta `docs/api/openapi-v1.yaml`.

## Da implementare

| Priorità logica | Slice verticale | Spec Kit |
|---:|---|---|
| 1 | Operazioni piattaforma, impostazioni, audit e notifiche | `specs/008-platform-operations` |
| 2 | Revision history/compare/restore operativo dove ancora mancante | `specs/009-operational-revisions` |
| 3 | Progetti end-to-end e impatto sul Budget | `specs/010-projects` |
| 4 | Allegati delle spese con revisioni e quota | `specs/011-expense-attachments` |
| 5 | BudgetVersion, riferimento e confronto | `specs/012-budget-versions` |
| 6 | Scenari what-if | `specs/013-scenarios` |
| 7 | Export CSV/XLSX e stampa | `specs/014-exports-and-print` |
| 8 | Migrazione legacy e portabilità Tenant | `specs/015-migration-and-portability` |
| 9 | Backup/restore, scheduler, release e deployment | `specs/016-backup-and-operations` |

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
