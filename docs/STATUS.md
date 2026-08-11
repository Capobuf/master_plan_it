# Stato funzionale

Stato verificato dopo l'implementazione delle Feature 009, 011 e 021.

Questo file è l'unico riepilogo manuale di stato. Non deve contenere task o cronologia.

## Disponibile alla baseline

| Area | Backend/API | Frontend |
|---|---|---|
| Login, logout, sessione, contesto Tenant | Implementato | Implementato |
| Tenant | Creazione bootstrap completa; update globale ristretto a codice, valuta e lingua; lifecycle implementato | Registro globale senza duplicazione dei campi operativi |
| Impostazioni Tenant | API Generali tenant-scoped, permessi delegabili, lock/audit, ownership esclusiva dei campi operativi e IVA forward-only | Workspace unico con Generali, Utenti, Ruoli e permessi, Anni e Centri filtrati per ability; refresh del contesto dopo il salvataggio |
| Utenti e ruoli | Implementato | Implementato |
| Planning year / Budget annuale | Lifecycle, approvazioni atomiche, chiusura | Implementato |
| Fornitori | CRUD/lifecycle/history/restore | Implementato |
| Centri di costo | CRUD/tree/lifecycle/history/restore | Implementato |
| Spese | Planning selezionato, filtri Register e totali riconciliati, detail year-scoped, bulk atomico, Actual immediato, close/reopen, move, crediti e history/compare/restore aggregate | Workspace con `Dettagli | Allegati | Storico`; allegati separati per Spesa e Righe |
| Contratti | CRUD/term/history/compare/restore aggregate | `Dettagli | Allegati | Storico` implementato |
| Generazione contratti | Una Quote annuale, Project, sync protetto e differenza attesa | Implementato |
| Progetti | CRUD/Deferred/history/compare/restore/delete terminale | `Dettagli | Allegati | Storico` implementato |
| Collegamento Spesa-Progetto/Contract | Tenant-safe e coerente con il Project Contract | Implementato |
| Dashboard | Planning selezionato e Actual immediati | Implementato |
| Budget corrente | Riepilogo/dettaglio annuale e warning closed | Implementato |
| Report economico | Filtri tenant-safe, summary riconciliato, cinque raggruppamenti e dataset analitici non paginati | Filtri automatici, KPI, grafici e dettaglio responsive implementati |
| Budget/Report storico | Cutoff Tenant, batch atomici, tombstone, read-only | Implementato |
| Revisioni operative | Top-10 logiche, actor umano/Sistema, compare semantico, restore con lock | Expense, Contract e Project uniformati |
| Manutenzione revisioni | Scheduler nativo, detach sicuro e hard prune delle sole Version ridondanti | Comandi Artisan documentati |
| Allegati privati | Storage privato, quota Tenant concorrente, upload/download/delete autorizzati, purge terminale e audit minimizzato | Lista/uploader responsive, conferma delete e raggruppamento ExpenseRow |

Il file globale `docs/api/openapi-v1.yaml` non è presente nel repository corrente. Fino alla sua
introduzione completa, i contratti verificabili sono i contract feature-local in `specs/*/contracts/`,
le route e i Resource testati. Non va dichiarato aggiornato un OpenAPI globale inesistente.

## Da implementare

| Priorità logica | Slice verticale | Spec Kit |
|---:|---|---|
| 1 | Operazioni piattaforma residue, quota allegati protetta, audit e notifiche | `specs/008-platform-operations` |
| 2 | BudgetVersion, riferimento e confronto | `specs/012-budget-versions` |
| 3 | Scenari what-if | `specs/013-scenarios` |
| 4 | Export CSV/XLSX e stampa | `specs/014-exports-and-print` |
| 5 | Migrazione legacy e portabilità Tenant | `specs/015-migration-and-portability` |
| 6 | Backup/restore, scheduler generale, release e deployment | `specs/016-backup-and-operations` |

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
