# Stato funzionale

Stato del runtime verificato al completamento della Slice 024 il 2026-08-13.

Questo file è l'unico riepilogo manuale di stato. Non deve contenere task o cronologia.

## Disponibile alla baseline

| Area | Backend/API | Frontend |
|---|---|---|
| Login, logout, sessione, contesto Tenant | Implementato | Implementato |
| Tenant | Creazione bootstrap completa; update globale ristretto a codice, valuta e lingua; lifecycle implementato | Registro globale senza duplicazione dei campi operativi |
| Impostazioni Tenant | API Generali tenant-scoped, permessi delegabili, lock/audit, Base Economica Net/Gross bloccata atomicamente alla prima approvazione e IVA forward-only | Workspace unico con Generali, Utenti, Ruoli e permessi, Anni e Centri filtrati per ability; refresh del contesto dopo il salvataggio |
| Utenti e ruoli | Implementato | Implementato |
| Planning year / Budget annuale | Lifecycle, approvazioni atomiche, chiusura | Implementato |
| Fornitori | CRUD/lifecycle/history/restore | Implementato |
| Centri di costo | CRUD/tree/lifecycle/history/restore | Implementato |
| Spese | Estimate/Quote coexistenti con una Pianificazione Corrente, Actual firmati e datati anche fuori anno, input diretto/calcolato, preview, bulk atomico e history/compare/restore aggregate; nessun lifecycle Expense | Shell superiore senza sidebar permanente; Registro, Documento/editor e `Dettagli | Allegati | Storico` con dirty guard Tenant/Anno |
| Plafond | Uno live per Tenant/Anno/Centro, allocazioni additive firmate, copertura integrale cross-Centro, consumo solo dagli Actual, capienza concorrente bloccante, preview e API dedicate | Registro, Documento e Report Plafond; quattro misure canoniche, impatto insufficiente con input preservati e nessuno Sforamento |
| Contratti | CRUD/term/history/compare/restore aggregate | `Dettagli | Allegati | Storico` implementato |
| Generazione contratti | Una Quote annuale, Project, sync protetto e differenza attesa | Implementato |
| Progetti | CRUD/Deferred/history/compare/restore/delete terminale | `Dettagli | Allegati | Storico` implementato |
| Collegamento Spesa-Progetto/Contract | Tenant-safe e coerente con il Project Contract | Implementato |
| Proiezione economica annuale | Motore unico BCMath/IVA, Base Net/Gross e guardia concorrente Tenant/Anno; Documento, Registro, Budget, Report e Dashboard riconciliati | Tipi/adattatori condivisi, nessun calcolo monetario autorevole nel client |
| Dashboard | Pianificazione Corrente e Actual dalla proiezione canonica | Implementato |
| Budget corrente | Riepilogo/dettaglio annuale dalla proiezione canonica; lifecycle Budget preservato | Implementato |
| Report economico | Filtri tenant-safe, cinque raggruppamenti e drill-down dalla proiezione canonica | KPI, grafici e dettaglio responsive implementati |
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
| — | Proseguimento del programma economico/UX dopo le fondazioni 023–024: Budget/Rettifiche, Progetti, Contratti, Trash/Retention e superfici finali | `specs/022-application-workspace-ux` (`PROPOSED TARGET`, parzialmente consegnato) |

L'ordine è una dipendenza tecnica iniziale, non una promessa di priorità prodotto. Il Product Owner
può cambiare l'ordine purché le dipendenze della slice scelta siano soddisfatte.

Il programma 022 non è una mega-feature da implementare e non possiede `tasks.md`. Le Slice 023 e
024 hanno consegnato schema greenfield, Spesa autorevole, proiezione economica condivisa, shell
annuale, Plafond singolo e copertura integrale. Le Slice successive devono specificare
separatamente Budget/Rettifiche, Progetti, Contratti,
Storico/Retention e infine le superfici UX condivise. Fino alla consegna di ciascuna Slice, la
tabella **Disponibile alla baseline** e il codice corrente restano autorità sul comportamento
implementato.

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
