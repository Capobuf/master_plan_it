# Tasks: Impostazioni generali del Tenant

**Input**: Design documents from `specs/021-tenant-general-settings/`

**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`, `contracts/tenant-settings-api.md`, `quickstart.md`

**Tests**: Obbligatori per costituzione e specifica. Ogni fase scrive prima i test e verifica il fallimento atteso prima del codice corrispondente.

**Organization**: Task raggruppati per user story e ordinati per dipendenza. I task `[P]` operano su file distinti e possono essere eseguiti da agenti paralleli.

## Phase 1: Setup e baseline

**Purpose**: Congelare il comportamento corrente e predisporre i target senza introdurre dipendenze.

- [X] T001 Eseguire i test baseline focalizzati su tenancy, autorizzazione, Spese e Contratti indicati in `specs/021-tenant-general-settings/quickstart.md` e registrare in sessione gli eventuali fallimenti preesistenti
- [X] T002 Verificare che `.gitignore`, `.dockerignore` e gli ignore di `frontend/eslint.config.js` coprano già vendor, node_modules, dist, build, coverage, log ed env; aggiungere soltanto pattern critici mancanti nei rispettivi file

---

## Phase 2: Foundational — abilities e autorizzazione

**Purpose**: Rendere disponibili i permessi tenant-scoped e il controllo autorevole usato dalla slice.

**⚠️ CRITICAL**: Blocca tutte le user story.

- [X] T003 [P] Aggiungere test di catalogo, seeding, ruolo Administrator, non assegnazione automatica a Editor/Viewer e implicazione update→view in `tests/Feature/Authorization/PermissionCatalogueTest.php` e `tests/Feature/Authorization/ProtectedPermissionTest.php`
- [X] T004 [P] Aggiungere test diretti same-Tenant, cross-Tenant, actor/context spoof, actor/Tenant inattivo e ripristino permission team in `tests/Feature/Authorization/TenantAbilityAuthorizerTest.php`
- [X] T005 Implementare `tenant-settings.view`, `tenant-settings.update` e i candidati view/update in `app/Support/Authorization/PermissionCatalogue.php` e `app/Http/Middleware/AuthorizeApplicationAbility.php`
- [X] T006 Implementare il controllo Action fail-closed in `app/Support/Authorization/TenantAbilityAuthorizer.php`
- [X] T007 Aggiungere l'upgrade forward-only delle due permissions e del ruolo Administrator globale in `database/migrations/2026_08_11_000006_add_tenant_settings_abilities.php`
- [X] T008 Eseguire i test T003–T004 e i test architetturali/autorizzativi correlati

**Checkpoint**: Le due abilities esistono, restano tenant-scoped e sono verificabili sia da middleware sia da Action.

---

## Phase 3: User Story 1 — Gestire le impostazioni del Tenant corrente (Priority: P1) 🎯 MVP

**Goal**: Leggere e salvare la proiezione Generali del Tenant corrente con autorizzazione, lock, audit e pagina React.

**Independent Test**: Un actor autorizzato modifica un Tenant dalla pagina e vede il valore aggiornato; viewer, altro Tenant, actor inattivo e lock stale non producono mutazioni.

### Tests for User Story 1

- [X] T009 [P] [US1] Scrivere i test HTTP/Action per proiezione chiusa, view/update indipendenti, payload inattesi, validazione, stale lock, cross-Tenant, inattivi e rollback audit in `tests/Feature/PlatformOperations/TenantSettingsTest.php`
- [X] T010 [P] [US1] Scrivere i test del form per loading/error, sola lettura, normalizzazione, Annulla/Salva, errore stale e refresh context in `frontend/src/components/settings/TenantGeneralSettingsForm.test.tsx`
- [X] T011 [P] [US1] Scrivere i test di route e visibilità con view oppure update in `frontend/src/navigation/applicationNavigation.test.ts` e `frontend/src/pages/Settings/General.test.tsx`

### Implementation for User Story 1

- [X] T012 [US1] Implementare mutazione transazionale, validazione, optimistic lock, budget lock e audit minimizzato in `app/Domain/Tenancy/Actions/UpdateTenantSettings.php`
- [X] T013 [US1] Implementare proiezione focalizzata e stato Base Budget in `app/Http/Resources/Api/V1/TenantSettingsResource.php`
- [X] T014 [US1] Implementare controller chiuso e route GET/PUT tenant-context in `app/Http/Controllers/Api/V1/TenantSettingsController.php` e `routes/api/v1/tenant-settings.php`
- [X] T015 [P] [US1] Implementare client tipizzato in `frontend/src/api/tenantSettings.ts`
- [X] T016 [US1] Implementare il form Generali con campi approvati, valuta read-only, salvataggio esplicito e `refreshContext()` in `frontend/src/components/settings/TenantGeneralSettingsForm.tsx`
- [X] T017 [US1] Integrare pagina, route e voce Generali autorizzata in `frontend/src/pages/Settings/General.tsx`, `frontend/src/navigation/routes.ts`, `frontend/src/navigation/applicationNavigation.ts` e `frontend/src/App.tsx`
- [X] T018 [US1] Eseguire i test T009–T011 e verificare manualmente il contratto `specs/021-tenant-general-settings/contracts/tenant-settings-api.md`

**Checkpoint**: La pagina Generali è completa e indipendentemente utilizzabile.

---

## Phase 4: User Story 2 — IVA Tenant forward-only (Priority: P1)

**Goal**: Applicare il default soltanto ai figli nuovi privi di override, preservando record/revisioni esistenti e flussi generati.

**Independent Test**: Con cambio 22→20, vecchi figli e revisioni restano a 22, nuovi figli omessi usano 20, override 0/10 prevalgono e un occurrence conserva i valori del termine sorgente.

### Tests for User Story 2

- [X] T019 [P] [US2] Scrivere il test end-to-end su Spesa, Contratto, figli aggiunti, omissione su update esistente, override esplicito, revision snapshot e occurrence in `tests/Feature/PlatformOperations/TenantVatDefaultTest.php`
- [X] T020 [P] [US2] Estendere i test UI Contratti per primo termine, termine aggiunto, omissione reale, override esplicito ed edit preservato in `frontend/src/components/contracts/ContractForm.test.tsx` e `frontend/src/components/contracts/ContractTermsEditor.test.tsx`
- [X] T021 [P] [US2] Estendere il test UI Spese per omissione su nuova riga, riga aggiunta, override ed edit preservato in `frontend/src/components/expenses/ExpenseEditor.test.tsx`

### Implementation for User Story 2

- [X] T022 [US2] Preservare `vat_rate` persistito quando l'update omette l'aliquota su una Expense row esistente in `app/Domain/Expenses/Services/ExpenseAggregateValidator.php`
- [X] T023 [US2] Preservare `vat_rate` persistito quando l'update omette l'aliquota su un Contract term esistente in `app/Domain/Contracts/Actions/Concerns/ManagesContracts.php`
- [X] T024 [US2] Rimuovere lo zero implicito dai nuovi termini Contract e mantenere null/omissione fino al payload in `frontend/src/components/contracts/contractTermFactory.ts` e `frontend/src/components/contracts/ContractForm.tsx`
- [X] T025 [US2] Eseguire i test T019–T021 più `tests/Accounting/Unit/VatCalculatorTest.php`, API Spese/Contratti e test di generation/sync

**Checkpoint**: Il default IVA è autorevole, forward-only e coperto in tutti i flussi della slice.

---

## Phase 5: User Story 3 — Comprendere limiti e blocchi (Priority: P2)

**Goal**: Rendere espliciti Base Budget bloccata, valuta read-only ed effetto forward-only dell'IVA.

**Independent Test**: Con approvazioni esistenti la Base Budget è disabilitata e motivata; valuta e campi fuori scope non sono modificabili; l'helper IVA è visibile.

### Tests for User Story 3

- [X] T026 [P] [US3] Estendere il test backend con Base Budget bloccata, deletion reason solo futura e assenza di campi globali nella proiezione in `tests/Feature/PlatformOperations/TenantSettingsTest.php`
- [X] T027 [P] [US3] Estendere il test frontend con budget lock/reason, valuta disabled, helper IVA e assenza di quota/anagrafica in `frontend/src/components/settings/TenantGeneralSettingsForm.test.tsx`

### Implementation for User Story 3

- [X] T028 [US3] Rifinire copy italiano, stato locked e affordance read-only in `frontend/src/components/settings/TenantGeneralSettingsForm.tsx`
- [X] T029 [US3] Eseguire i test T026–T027 e il test freeze Base Budget `tests/Feature/Budget/TenantBudgetBasisFreezeTest.php`

**Checkpoint**: Limiti e impatto delle impostazioni sono comprensibili senza tentativi falliti o aspettative retroattive.

---

## Phase 6: Polish, documentazione e gate

**Purpose**: Convergere artefatti, comportamento permanente e suite complete.

- [X] T030 [P] Documentare regole permanenti settings/IVA in `docs/DOMAIN.md` e stato della sola slice in `docs/STATUS.md`
- [X] T031 [P] Aggiornare `specs/README.md` e rimuovere la sovrapposizione settings da `specs/008-platform-operations/spec.md` senza dichiarare completata l'intera Feature 008
- [X] T032 [P] Aggiornare label italiane delle nuove abilities in `frontend/src/presentation/labels.ts`
- [X] T033 Eseguire `composer test:static`, `composer test:prepare`, `composer test:accounting`, `composer test:application` e correggere ogni regressione di feature
- [X] T034 Eseguire `npm run verify` in `frontend/` e correggere ogni regressione di feature
- [X] T035 Eseguire tutti gli scenari di `specs/021-tenant-general-settings/quickstart.md`, verificare `git diff --check` e marcare completati soltanto i task realmente verificati

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1**: nessuna dipendenza.
- **Phase 2**: dipende dalla baseline e blocca tutte le story.
- **US1 (Phase 3)**: dipende dalla Phase 2.
- **US2 (Phase 4)**: dipende dalla Phase 2; il cambio default via API usa US1 per l'E2E completo, ma i resolver IVA sono testabili indipendentemente.
- **US3 (Phase 5)**: dipende da US1.
- **Phase 6**: dipende da tutte le story.

### Parallel Opportunities

- T003 e T004 possono essere scritti in parallelo.
- T009, T010 e T011 possono essere scritti in parallelo.
- T015 può procedere in parallelo con il backend T012–T014.
- T019, T020 e T021 possono essere scritti in parallelo; T022–T024 toccano file distinti ma seguono i rispettivi test.
- T026 e T027 possono essere scritti in parallelo.
- T030, T031 e T032 possono procedere in parallelo dopo l'implementazione.

## Parallel Example: User Story 2

```text
Agent A: T019 → T022/T023 → test backend IVA
Agent B: T020 → T024 → test frontend Contratti
Agent C: T021 → verifica frontend Spese e regressioni
```

## Implementation Strategy

1. Completare baseline e foundation autorizzativa.
2. Consegnare US1 come MVP: pagina Generali completa.
3. Chiudere US2 prima di considerare la feature economicamente corretta.
4. Rifinire US3 e aggiornare documentazione soltanto dopo i test.
5. Eseguire gate completi, poi `speckit-converge`; se emergono task, rieseguire `speckit-implement` e convergere di nuovo.

## Format Validation

Tutti i task usano checkbox, ID sequenziale, marker `[P]` solo quando sicuro, label `[USn]` nelle fasi story e path esatti.

## Phase 7: Convergence

- [X] T036 Estendere `tests/Feature/PlatformOperations/TenantVatDefaultTest.php` con un flusso HTTP reale che aggiorna il default tramite `/api/v1/tenant-settings`, crea Spesa e Contratto tramite API, verifica importi esatti e conferma l'invarianza dei record preesistenti per T019, SC-003 e plan decision 10 (partial)

---

## Phase 8: Product correction — workspace Impostazioni (Priority: P1)

**Goal**: Sostituire le destinazioni amministrative disperse con un unico workspace permission-aware, mantenendo compatibili i link esistenti.

**Independent Test**: Con combinazioni diverse di abilities, `/impostazioni` sceglie la prima sezione accessibile, i tab mostrano soltanto le sezioni consentite nell'ordine canonico, un deep link non autorizzato non monta il child e tutti gli URL precedenti raggiungono la nuova destinazione.

### Tests for User Story 4

- [X] T037 [P] [US4] Scrivere i test del catalogo sezioni, ordine, filtro OR e prima route accessibile in `frontend/src/navigation/settingsNavigation.test.ts`
- [X] T038 [P] [US4] Scrivere i test di layout, tab attivo, loading, redirect indice, assenza sezioni e deep-link fail-closed in `frontend/src/pages/Settings/SettingsRouting.test.tsx`
- [X] T039 [P] [US4] Aggiornare i test della sidebar logica per una sola voce Impostazioni visibile con qualunque ability di sezione in `frontend/src/navigation/applicationNavigation.test.ts`

### Implementation for User Story 4

- [X] T040 [US4] Definire route canoniche nidificate, route flat compatibili e resolver permission-aware in `frontend/src/navigation/routes.ts` e `frontend/src/navigation/settingsNavigation.ts`
- [X] T041 [US4] Implementare indice, layout responsive, tab autorizzati e guard dei child in `frontend/src/pages/Settings/Index.tsx` e `frontend/src/pages/Settings/Layout.tsx`
- [X] T042 [US4] Nidificare Generali, Utenti, Ruoli, Anni e Centri nel workspace e aggiungere redirect diretti italiani/inglesi in `frontend/src/App.tsx`
- [X] T043 [US4] Sostituire le cinque voci laterali con una sola voce composita Impostazioni in `frontend/src/navigation/applicationNavigation.ts`
- [X] T044 [US4] Eseguire i test T037–T039, `npm run verify`, gli scenari workspace di `specs/021-tenant-general-settings/quickstart.md`, aggiornare `docs/STATUS.md` e verificare `git diff --check`

### Phase 8 Dependencies

- T037–T039 precedono T040–T043 secondo TDD; T044 segue tutta l'implementazione.
- T040 precede T041–T043 perché definisce il contratto di navigazione condiviso.

---

## Phase 9: Product correction — ownership Generali/registro Tenant (Priority: P1)

**Goal**: Rendere Generali l'unica autorità dei campi operativi dopo il bootstrap e garantire che l'Administrator la veda subito dopo l'upgrade.

**Independent Test**: La creazione globale conserva i sei valori bootstrap; l'edit globale mostra e invia soltanto codice, valuta e lingua; payload manuali con campi operativi vengono rifiutati senza side effect; dopo upgrade e ingresso nel Tenant l'Administrator vede Generali.

### Tests for User Story 5

- [X] T045 [P] [US5] Restringere e ampliare i test Action/HTTP del write contract globale, audit e rollback in `tests/Feature/Tenancy/TenantManagementTest.php` e `tests/Feature/Api/Tenancy/ApiTenancyHttpTest.php`
- [X] T046 [P] [US5] Scrivere i test del registro per create bootstrap, edit dei soli identificativi, payload chiuso e tabella senza colonne operative in `frontend/src/components/tenants/TenantsView.test.tsx`
- [X] T047 [P] [US5] Aggiungere test production-like per upgrade cache-safe/idempotente e abilities Administrator dopo enter Tenant in `tests/Feature/Authorization/PermissionCatalogueTest.php` e `tests/Feature/Api/Context/ApiContextHttpTest.php`

### Implementation for User Story 5

- [X] T048 [US5] Restringere l'update globale a codice, valuta e lingua in `app/Domain/Tenancy/Actions/UpdateTenant.php` e `app/Http/Controllers/Api/V1/TenantController.php`
- [X] T049 [US5] Separare input create/update e rimuovere campi/colonne operativi dall'edit globale in `frontend/src/api/tenants.ts` e `frontend/src/components/tenants/TenantsView.tsx`
- [X] T050 [US5] Invalidare esplicitamente la cache autorizzativa nell'upgrade forward-only in `database/migrations/2026_08_11_000006_add_tenant_settings_abilities.php`

---

## Phase 10: Verifica e consegna

- [X] T051 Eseguire test backend focalizzati, `composer test:static`, `composer test:accounting`, `composer test:application` e correggere regressioni nei file della Feature 021
- [X] T052 Eseguire `npm run verify`, aggiornare `docs/STATUS.md`, `specs/README.md` e `specs/021-tenant-general-settings/spec.md`, quindi verificare `git diff --check`
- [X] T053 Eseguire `speckit-converge` e confermare tutti i task della Feature 021 completi in `specs/021-tenant-general-settings/tasks.md`

### Phase 9–10 Dependencies

- T045–T047 precedono T048–T050 secondo TDD; i gruppi backend, frontend e upgrade operano su file distinti.
- T051–T052 seguono l'implementazione; T053 segue tutti i gate e la convergenza finale.
