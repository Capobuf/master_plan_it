# Tasks: Revisioni operative e manutenzione sicura

**Input**: artefatti in `specs/009-operational-revisions/`

**Prerequisiti**: `plan.md`, `spec.md`, `research.md`, `data-model.md`, `contracts/revisions-api.md`, `quickstart.md`

**Tests**: richiesti dal Product Owner; i test focalizzati precedono il comportamento che proteggono. Ogni gate riporta comando ed esito atteso.

## Formato: `[ID] [P?] [Story] Descrizione`

- **[P]**: eseguibile in parallelo soltanto quando non modifica gli stessi file né dipende da task incompleti.
- **[US1]…[US6]**: corrisponde alle user story in `spec.md`.
- Ogni task nomina file e simboli target; le dipendenze non ovvie sono indicate esplicitamente.

## Phase 1: Setup e contratti eseguibili

**Purpose**: fissare schema, invarianti e regressioni prima della modifica runtime.

- [x] T001 Aggiungere i test schema/backfill per `snapshot_contents`, root operativa, `actor_kind`, FK nullable, equivalenza conteggi/JSON e rollback rifiutato dopo detach in `tests/Feature/Revisions/RevisionBatchSchemaTest.php` (simboli: nuovi test Feature 009; expected iniziale: failure prima di T005).
- [x] T002 [P] Aggiungere i test di cattura business/no-op/sole Note/solo lock, no-op con lock stale e actor umano/Sistema in `tests/Feature/Revisions/RevisionBatchIntegrationTest.php` e `tests/Feature/Revisions/ExpenseVersioningIntegrationTest.php` (expected iniziale: failure prima di T009–T012).
- [x] T003 [P] Aggiungere fixture/helper strettamente revisionale riusabile in `tests/Support/` soltanto se riduce duplicazione reale nei test T001–T002; non creare repository o framework di test generico.
- [x] T004 Eseguire `php artisan test tests/Feature/Revisions/RevisionBatchSchemaTest.php tests/Feature/Revisions/RevisionBatchIntegrationTest.php tests/Feature/Revisions/ExpenseVersioningIntegrationTest.php`; expected: i nuovi casi falliscono per capability mancanti e i casi baseline non regrediscono.

---

## Phase 2: Fondazione snapshot self-contained e grouping logico

**Purpose**: rendere `RevisionBatchItem` sufficiente per storia annuale e root operativa prima di esporre nuove API.

**⚠️ CRITICAL**: nessuna user story può essere completata finché schema, dual-write e query annuale non sono coerenti.

- [x] T005 Creare `database/migrations/2026_08_11_000003_make_revision_items_self_contained.php` e `database/migrations/2026_08_11_000004_mark_revision_item_changes.php` con `revision_batch_items.snapshot_contents`, root operativa, `is_changed`, indice tenant/root/batch, `version_id` nullable e `revision_batches.actor_kind`; backfill a chunk con equivalenza JSON/conteggi e `down()` fail-safe (dipende da T001).
- [x] T006 [P] Estendere cast/relazioni/guard in `app/Models/RevisionBatch.php` e `app/Models/RevisionBatchItem.php` per `actor_kind`, snapshot e root, senza rendere mutabili gli snapshot dopo la creazione (dipende da T005).
- [x] T007 [P] Creare `app/Domain/Revisions/Data/RevisionActorKind.php` con soli valori `human` e `system`, label visuale italiana risolta server-side (dipende da T005).
- [x] T008 Estendere `app/Domain/Revisions/Actions/BeginRevisionBatch.php` per provenance actor esplicita con default umano e source restore logico `restored_from_batch_id`, mantenendo `actor_user_id` come provenienza tecnica (dipende da T006–T007).
- [x] T009 Estendere `app/Domain/Revisions/Actions/LinkVersionToRevisionBatch.php::execute` per dual-write esatto di `Version.contents`, root mapping whitelist, `is_changed` e rifiuto di item incoerenti; i recorder Expense/Contract includono root e set completo dei figli correnti senza creare Version artificiali (dipende da T006).
- [x] T010 Rimuovere `lock_version` dalle allowlist `$versionable` in `app/Models/Expense.php`, `ExpenseRow.php`, `Contract.php`, `ContractTerm.php`, `Project.php` e `PlanningYear.php`, mantenendo `config/versionable.php::keep_versions` a `0` (dipende da T002).
- [x] T011 Correggere la cattura manuale in `app/Domain/Revisions/Actions/ActivateAnnualHistory.php` affinché gli snapshot nuovi rispettino la whitelist e non reintroducano bookkeeping tramite `createVersion()` (dipende da T009–T010).
- [x] T012 Dopo authorization e verifica dell'expected lock, impedire batch no-op con confronto business normalizzato nelle Actions `app/Domain/Expenses/Actions/UpdateExpense.php`, `app/Domain/Contracts/Actions/UpdateContract.php`, `app/Domain/Projects/Actions/UpdateProject.php`, `app/Domain/MasterData/Actions/UpdateVendor.php` e `UpdateCostCenter.php`; nessun incremento lock/batch/audit revisionale se nulla cambia e stale resta un errore (dipende da T010).
- [x] T013 Marcare come `system` le mutation automatiche reali in `app/Domain/Projects/Actions/PromoteDeferredProjects.php` e ogni altro chiamante automatico individuato con `rg 'BeginRevisionBatch' app/Domain`, lasciando idempotenti i no-op (dipende da T008, T012).
- [x] T014 Sostituire i join a `versions.contents` con `revision_batch_items.snapshot_contents` in `app/Domain/Budget/Queries/HistoricalAnnualBudgetQuery.php::latestItems` e `::referenceSnapshots`, eliminando anche dipendenze correnti non necessarie per ricostruire ContractTerm storici (dipende da T009).
- [x] T015 Aggiungere regressioni di equivalenza cutoff e query-count in `tests/Feature/Budget/HistoricalBudgetQueryTest.php` e `tests/Accounting/Integration/HistoricalBudgetQueryCountTest.php`, incluse reference snapshot soft-deleted (dipende da T014).
- [x] T016 Eseguire `php artisan test tests/Feature/Revisions tests/Feature/Budget/HistoricalBudgetQueryTest.php tests/Accounting/Integration/HistoricalBudgetQueryCountTest.php`; expected: schema/backfill, dual-write, no-op, actor e risultati annuali PASS senza aumento N+1.

**Checkpoint**: ogni item nuovo possiede snapshot immutabile; annual history non legge `versions.contents`; `lock_version` non produce una revisione.

---

## Phase 3: User Story 1 — Consultare lo Storico (Priority: P1) 🎯 MVP

**Goal**: esporre le dieci revisioni logiche più recenti per root con actor e summary semantici.

**Independent Test**: una root con undici batch mostra solo i dieci più recenti; actor umano/Sistema e ability/tenant risultano corretti senza ID tecnici.

### Tests per User Story 1

- [x] T017 [P] [US1] Aggiungere i casi di grouping Expense/Row e Contract/Term, ordinamento deterministico, top-ten, legacy incompleto e cross-Tenant in `tests/Feature/Api/Expenses/ExpenseApiHttpTest.php`, `tests/Feature/Api/Contracts/ContractApiHttpTest.php` e `tests/Feature/Revisions/OperationalRevisionRetentionTest.php` (dipende da T016; expected iniziale: failure).
- [x] T018 [P] [US1] Aggiungere i casi API di list/permission/tenancy per Expense, Contract e Project nei rispettivi `tests/Feature/Api/*/*ApiHttpTest.php`, e per Vendor/Cost Center in `tests/Feature/MasterData/VendorRevisionTest.php` e `CostCenterRevisionTest.php` (dipende da T016; expected iniziale: failure).
- [x] T019 [P] [US1] Creare `frontend/src/components/revisions/RevisionHistoryPanel.test.tsx` ed estendere `frontend/src/pages/Expenses/ExpenseDetail.test.tsx` per tab, actor Sistema, ability e assenza di valori tecnici; verificare l'integrazione Contract/Project e il responsive nel browser reale T067 (expected iniziale: failure).

### Implementazione per User Story 1

- [x] T020 [US1] Creare `app/Domain/Revisions/Queries/OperationalRevisionQuery.php` con `visibleForRoot`, `findVisibleBatch` e ricostruzione snapshot completa, tenant/root fail-closed e limite totale 10 (dipende da T017).
- [x] T021 [P] [US1] Creare `app/Domain/Revisions/Data/RevisionDiff.php` e `app/Http/Resources/Api/V1/OperationalRevisionResource.php` per contract actor/operation/summary/changed fields/can abilities senza campi tecnici (dipende da T020).
- [x] T022 [US1] Estendere `app/Domain/Expenses/Queries/ExpenseDetailQuery.php` con history root Expense e creare `app/Domain/Expenses/Queries/ExpenseRevisionQuery.php` per entry semantiche aggregate (dipende da T020–T021).
- [x] T023 [P] [US1] Creare `app/Domain/Contracts/Queries/ContractRevisionQuery.php` e uniformare `app/Domain/Projects/Queries/ProjectRevisionQuery.php` alla source batch logica e top-ten (dipende da T020–T021).
- [x] T024 [US1] Aggiungere endpoint list in `app/Http/Controllers/Api/V1/ExpenseController.php`, uniformare `ContractController.php`, `ProjectController.php`, `VendorController.php`, `CostCenterController.php` e route parent-scoped in `routes/api/v1/expenses.php`, `contracts.php`, `projects.php`, `master-data.php` (dipende da T022–T023).
- [x] T025 [US1] Aggiungere `ContractPolicy::restoreRevision` in `app/Policies/ContractPolicy.php` e verificare che tutte le policy Expense/Contract/Project/Vendor/CostCenter governino server-side view/restore (dipende da T024).
- [x] T026 [P] [US1] Creare i tipi/transport condivisi in `frontend/src/api/revisions.ts` e aggiungere funzioni list in `frontend/src/api/expenses.ts`, `contracts.ts`, `projects.ts` senza duplicare business rules (dipende dal contract T024).
- [x] T027 [P] [US1] Creare `frontend/src/components/common/ObjectTabs.tsx` riusando il pattern TailAdmin `ChartTab` e creare `frontend/src/components/revisions/RevisionHistoryPanel.tsx` con Table/card responsive, Badge e Button esistenti (dipende da T019, T026).
- [x] T028 [US1] Integrare `Dettagli | Storico` in `frontend/src/pages/Expenses/ExpenseDetail.tsx`, `Contracts/ContractDetail.tsx`, `Projects/ProjectDetail.tsx`, rimuovendo le presentazioni history duplicate ma non le API backward-compatible (dipende da T027).
- [x] T029 [US1] Eseguire `php artisan test tests/Feature/Revisions/OperationalRevisionRetentionTest.php tests/Feature/Api/Expenses/ExpenseApiHttpTest.php tests/Feature/Api/Contracts/ContractApiHttpTest.php tests/Feature/Api/Projects/ProjectApiHttpTest.php && cd frontend && npm test`; expected: top-ten/grouping/tenancy e tab responsive PASS, nessun ID tecnico nel DOM/payload.

---

## Phase 4: User Story 2 — Confrontare con lo stato corrente (Priority: P2)

**Goal**: mostrare solo differenze business revision-vs-current con label umane, incluse row e term.

**Independent Test**: FK con label differenti, row/term aggiunte-rimosse-modificate e snapshot uguale producono rispettivamente diff semantico e lista vuota.

### Tests per User Story 2

- [x] T030 [P] [US2] Aggiungere casi compare semantico Expense/Contract/Project e source fuori top-ten/cross-Tenant in `tests/Feature/Api/Expenses/ExpenseApiHttpTest.php`, `ContractApiHttpTest.php`, `ProjectApiHttpTest.php` e `tests/Feature/Revisions/OperationalRevisionRetentionTest.php` (dipende da T029; expected iniziale: failure).
- [x] T031 [P] [US2] Estendere `frontend/src/components/revisions/RevisionHistoryPanel.test.tsx` per compare diff-only, label umane, formattazione row/term, assenza valori tecnici e gating restore (expected iniziale: failure).

### Implementazione per User Story 2

- [x] T032 [US2] Implementare `ExpenseRevisionQuery::comparison` in `app/Domain/Expenses/Queries/ExpenseRevisionQuery.php` con snapshot cumulativo completo, label Cost Center/Vendor/Project e row identity comprensibile (dipende da T030).
- [x] T033 [P] [US2] Implementare `ContractRevisionQuery::comparison` e uniformare `ProjectRevisionQuery::comparison` in `app/Domain/Contracts/Queries/ContractRevisionQuery.php` e `app/Domain/Projects/Queries/ProjectRevisionQuery.php`, correggendo label Project tramite `title` (dipende da T030).
- [x] T034 [US2] Aggiungere endpoint compare e Resource diff-only in `app/Http/Controllers/Api/V1/ExpenseController.php`, `ContractController.php`, `ProjectController.php`, `app/Http/Resources/Api/V1/OperationalRevisionResource.php` e route correlate (dipende da T032–T033).
- [x] T035 [P] [US2] Aggiungere funzioni compare in `frontend/src/api/expenses.ts`, `contracts.ts`, `projects.ts` e creare `frontend/src/components/revisions/RevisionCompareModal.tsx` usando il Modal TailAdmin esistente (dipende da T031, T034).
- [x] T036 [US2] Collegare `Confronta con attuale` da `RevisionHistoryPanel.tsx` nelle tre pagine detail, con loading/error visibili e nessun confronto revision-to-revision (dipende da T035).
- [x] T037 [US2] Eseguire le suite API Expense/Contract/Project e `cd frontend && npm test`; expected: diff aggregate semantico PASS, zero valori tecnici esposti.

---

## Phase 5: User Story 3 — Ripristinare Expense (Priority: P3)

**Goal**: restore atomico di Expense e set esatto di ExpenseRow, con validazione corrente, optimistic lock e nuova revisione.

**Independent Test**: restore valido ricrea/modifica/rimuove row e crea una sola nuova entry; stale lock/riferimento invalido/tenant/permission causano rollback totale.

- [x] T038 [P] [US3] Aggiungere in `tests/Feature/Api/Expenses/ExpenseApiHttpTest.php`, `tests/Feature/Revisions/ExpenseVersioningIntegrationTest.php` e `tests/Feature/Expenses/ExpenseActionRollbackTest.php` i casi restore aggregate, sole Note, stale lock, reference invalid, terminal delete, permission/tenant, nuova revisione e rollback (dipende da T037; expected iniziale: failure).
- [x] T039 [US3] Creare `app/Domain/Expenses/Actions/RestoreExpenseRevision.php::execute` riusando `ExpenseAggregateValidator`, whitelist esplicite e transazione/lock current; ricreare soltanto dati business row senza bookkeeping (dipende da T038).
- [x] T040 [US3] Aggiungere endpoint restore in `app/Http/Controllers/Api/V1/ExpenseController.php` e `routes/api/v1/expenses.php`, validando `lock_version` e risolvendo nuovamente source top-ten al momento della conferma (dipende da T039).
- [x] T041 [P] [US3] Aggiungere transport restore in `frontend/src/api/expenses.ts` e conferma/preview restore in `frontend/src/components/revisions/RevisionHistoryPanel.tsx` e `RevisionCompareModal.tsx`, mostrata solo con ability server-side (dipende da T040).
- [x] T042 [US3] Eseguire `php artisan test tests/Feature/Api/Expenses/ExpenseApiHttpTest.php tests/Feature/Revisions/ExpenseVersioningIntegrationTest.php tests/Feature/Expenses/ExpenseActionRollbackTest.php && cd frontend && npm test`; expected: restore Expense e rollback/gating PASS.

---

## Phase 6: User Story 4 — Ripristinare Contract (Priority: P4)

**Goal**: restore atomico Contract/ContractTerm senza toccare generation history o generated Expense.

**Independent Test**: restore valido modifica header/term e crea una nuova entry; term terminale, overlap, source key/generation e stale lock restano protetti con rollback.

- [x] T043 [P] [US4] Aggiungere in `tests/Feature/Api/Contracts/ContractApiHttpTest.php`, `tests/Feature/Contracts/ContractActionRollbackTest.php` e `tests/Feature/Contracts/ContractAnnualPlanningTest.php` i casi aggregate restore, term/reference/stale lock, tenant/permission, generated Expense, suppression e rollback (dipende da T042; expected iniziale: failure).
- [x] T044 [US4] Creare `app/Domain/Contracts/Actions/RestoreContractRevision.php::execute` riusando le invarianti in `app/Domain/Contracts/Actions/Concerns/ManagesContracts.php`, whitelist e lock transazionale; non scrivere campi generation/suppression (dipende da T043).
- [x] T045 [US4] Aggiungere compare/restore routes e metodi in `app/Http/Controllers/Api/V1/ContractController.php`, `routes/api/v1/contracts.php` e `app/Policies/ContractPolicy.php`, con source top-ten rivalidata (dipende da T044).
- [x] T046 [P] [US4] Aggiungere transport restore in `frontend/src/api/contracts.ts` e integrare conferma/refresh in `frontend/src/pages/Contracts/ContractDetail.tsx` (dipende da T045).
- [x] T047 [US4] Eseguire `php artisan test tests/Feature/Api/Contracts/ContractApiHttpTest.php tests/Feature/Contracts/ContractActionRollbackTest.php tests/Feature/Contracts/ContractAnnualPlanningTest.php && cd frontend && npm test`; expected: restore Contract PASS e generation/suppression immutate.

---

## Phase 7: User Story 5 — Ripristinare Project (Priority: P5)

**Goal**: preservare il restore Project esistente usando batch logica, diff semantico e UX uniforme.

**Independent Test**: restore Project valido crea una nuova batch con source logica; delete terminale, stale lock, tenant, permission e source fuori top-ten sono negati.

- [x] T048 [P] [US5] Estendere `tests/Feature/Projects/ProjectRevisionTest.php` e `tests/Feature/Api/Projects/ProjectApiHttpTest.php` per source batch, nuova revisione, actor, top-ten, semantic diff e deny (dipende da T047; expected iniziale: failure sui contratti legacy).
- [x] T049 [US5] Rifattorizzare `app/Domain/Projects/Actions/RestoreProjectRevision.php` e `app/Domain/Projects/Queries/ProjectRevisionQuery.php` per logical batch, whitelist restore e `restored_from_batch_id`, senza applicare lock storico (dipende da T048).
- [x] T050 [US5] Uniformare `app/Http/Controllers/Api/V1/ProjectController.php`, `app/Http/Resources/Api/V1/ProjectRevisionResource.php` e `routes/api/v1/projects.php` al contract comune, eliminando raw FK/column names (dipende da T049).
- [x] T051 [P] [US5] Aggiornare `frontend/src/api/projects.ts` e `frontend/src/pages/Projects/ProjectDetail.tsx` al contract condiviso, rimuovendo la history table duplicata (dipende da T050).
- [x] T052 [US5] Eseguire `php artisan test tests/Feature/Projects/ProjectRevisionTest.php tests/Feature/Api/Projects/ProjectApiHttpTest.php && cd frontend && npm test -- --run ProjectDetail RevisionHistoryPanel RevisionCompareModal`; expected: capability Project uniformata PASS.

---

## Phase 8: User Story 6 — Retention e manutenzione (Priority: P6)

**Goal**: nascondere oltre dieci immediatamente e hard-delete solo `Version` davvero ridondanti, preservando cutoff annuali e legacy ambiguo.

**Independent Test**: l'undicesima espelle la prima dalle operazioni; detach/prune elimina una Version non referenziata, conserva quelle protette e non cambia Budget/Report storico.

- [x] T053 [P] [US6] Creare `tests/Feature/Revisions/OperationalRevisionRetentionTest.php` per top-ten multi-item, restore count, detach idempotente, snapshot incompleto/legacy ambiguo protetto e hard delete reale; creare `tests/Feature/Revisions/RevisionMaintenanceScheduleTest.php` per ordine Scheduler e assenza di infrastrutture permanenti (dipende da T052; expected iniziale: failure).
- [x] T054 [P] [US6] Aggiungere in `tests/Feature/Budget/HistoricalBudgetQueryTest.php` il confronto prima/dopo detach+prune e la conservazione della Version ancora referenziata (dipende da T052; expected iniziale: failure).
- [x] T055 [US6] Creare `app/Domain/Revisions/Actions/ApplyOperationalRevisionRetention.php::execute` che calcola batch oltre top-ten per root, verifica snapshot/provenance e rende nullable soltanto riferimenti sicuri; nessuna delete diretta (dipende da T053–T054).
- [x] T056 [US6] Implementare `Illuminate\Database\Eloquent\Prunable` in `app/Models/Version.php::prunable` includendo soft-deleted e selezionando solo Version prive di riferimenti item/batch restore; usare `forceDelete` per-record e non `MassPrunable` (dipende da T055).
- [x] T057 [US6] Creare comando sottile `app/Console/Commands/ApplyOperationalRevisionRetentionCommand.php` che delega alla Action e registrare in `routes/console.php` schedule giornaliero retention prima di `model:prune --model=App\\Models\\Version`, con `withoutOverlapping` solo se supportato dal cache driver esistente (dipende da T055–T056).
- [x] T058 [US6] Eseguire `php artisan test tests/Feature/Revisions/OperationalRevisionRetentionTest.php tests/Feature/Revisions/RevisionMaintenanceScheduleTest.php tests/Feature/Budget/HistoricalBudgetQueryTest.php tests/Accounting/Integration/HistoricalBudgetQueryCountTest.php`; expected: retention/prune, schedule e storia annuale PASS, esecuzione ripetuta invariata.
- [x] T059 [US6] Eseguire `php artisan model:prune --pretend --model='App\Models\Version'`; expected: conteggio non distruttivo coerente con fixture/DB corrente e zero errori, senza dichiarare righe eliminate.

---

## Phase 9: Polish, documentazione e gate cross-cutting

- [x] T060 Uniformare Vendor/Cost Center al batch ID logico nei controller/actions/resource `app/Http/Controllers/Api/V1/VendorController.php`, `CostCenterController.php`, `app/Domain/MasterData/Actions/RestoreVendorRevision.php`, `RestoreCostCenterRevision.php`, `app/Http/Resources/Api/V1/RevisionResource.php` e `routes/api/v1/master-data.php`; aggiungere regressioni in `tests/Feature/MasterData/VendorRevisionTest.php` e `CostCenterRevisionTest.php`.
- [x] T061 [P] Aggiornare il contract feature-local `specs/009-operational-revisions/contracts/revisions-api.md` soltanto se l'implementazione finale richiede nomi/path diversi, mantenendo invarianti e senza creare OpenAPI globale parziale.
- [x] T062 [P] Aggiornare comportamento permanente in `docs/ARCHITECTURE.md`, `docs/DOMAIN.md`, `docs/STATUS.md`, `docs/OPERATIONS.md` e indice `specs/README.md`; documentare `schedule:run`, comando diretto hosting limitato e `model:prune --pretend`, senza report analyze/readiness/changelog paralleli.
- [x] T063 Eseguire `composer test:static`; expected: PHPStan/Pint/architecture gate PASS senza nuove baseline o ignore.
- [x] T064 Eseguire `composer test:accounting` e `composer test:application`; expected: suite pertinenti PASS con numero test/failure registrato separatamente.
- [x] T065 Eseguire `composer verify`; expected: gate Composer completo PASS se MySQL/runtime sono disponibili, altrimenti registrare l'impedimento reale e non dichiarare PASS.
- [x] T066 Eseguire in `frontend/` `npm test`, `npm run lint`, `npm run build`; expected: Vitest/ESLint/TypeScript/Vite PASS senza warning/errori introdotti.
- [x] T067 Verificare browser reale secondo `quickstart.md` su Expense/Contract/Project a 1440px e circa 390px, light/dark, compare/restore e console; expected: nessun overflow/errore console, oppure registrare come non eseguito se browser/servizi non disponibili.
- [x] T068 Eseguire `git diff --check`, rileggere `git diff --stat`, confermare branch/HEAD/status e completare la checklist `checklists/integrity.md`; expected: nessun whitespace error, nessun file fuori scope, nessuna voce CRITICAL/HIGH irrisolta.

---

## Dipendenze e ordine di esecuzione

### Phase dependencies

- **Setup (T001–T004)**: nessuna dipendenza; stabilisce failure attese.
- **Fondazione (T005–T016)**: dipende dal Setup e blocca tutte le story.
- **US1 (T017–T029)**: dipende dalla fondazione; fornisce il resolver top-ten usato da tutte le story.
- **US2 (T030–T037)**: dipende da US1 per list/source e componenti comuni.
- **US3 (T038–T042)**: dipende da US2 per preview e source resolver.
- **US4 (T043–T047)**: dipende dalle convenzioni restore consolidate in US3, pur mantenendo Action specifica.
- **US5 (T048–T052)**: dipende dal contract/UI comuni e migra la capability esistente.
- **US6 (T053–T059)**: dipende da tutte le root che partecipano al conteggio.
- **Polish (T060–T068)**: dipende dalle sei story complete.

### Parallel opportunities

- T001/T002 e i test frontend T019/T031 possono essere preparati su file distinti.
- Dopo T020, i mapper Expense (T022) e Contract/Project (T023) sono indipendenti.
- I test aggregate US3/US4/US5 sono in file separati, ma le story restano sequenziali per ridurre rischio sul contract condiviso.
- T053/T054 sono fixture diverse della stessa regola e precedono insieme retention/prune.
- T061/T062 possono procedere in parallelo soltanto dopo stabilizzazione API/runtime.

## Strategia incrementale

1. Completare fondazione e dimostrare che il dataset annuale non dipende più da `versions`.
2. Consegnare US1 come MVP consultabile con top-ten server-side.
3. Aggiungere compare semantico, quindi restore Expense, Contract e Project uno alla volta.
4. Attivare detach/prune soltanto dopo i test equivalenza annuale.
5. Chiudere con master data, documentazione, gate completi e verifica browser.

## Lavoro vietato durante Feature 009

- Non impostare `keep_versions=10`, non sostituire `overtrue/laravel-versionable` e non creare una seconda timeline/projection engine.
- Non eliminare batch/item annuali, Version ancora referenziate, audit event o record business soft-deleted non dimostrati inutili.
- Non includere allegati, BudgetVersion, Scenario, export, ricerca Contract `q`, Filament, Redis, queue worker o manutenzione MySQL aggressiva.
- Non introdurre repository generici, CQRS/event bus/service locator, router/tab framework o design system paralleli.
- Non esporre Version ID, database ID, `lock_version`, column name o FK numeriche come copy utente.
