# Tasks: Feature 008 — Platform operations

**Input**: `spec.md`, `plan.md`, `research.md`, `data-model.md`, `contracts/platform-operations-api.md`

**Tests**: obbligatori e scritti prima del corrispondente codice. Usare esclusivamente migration
forward-only e `DatabaseTransactions`; sono vietati reset e truncation distruttivi.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: file distinti e nessuna dipendenza incompleta.
- **[USn]**: mapping alla user story `US-008-nn`.

## Phase 1: Setup e contratto

**Purpose**: fissare contratto e stato iniziale senza introdurre dipendenze.

- [X] T001 Verificare e completare il contract di tutte le route/input/output/errori in `specs/008-platform-operations/contracts/platform-operations-api.md`
- [X] T002 Aggiornare lo stato pianificato della feature in `specs/008-platform-operations/spec.md` e `specs/008-platform-operations/plan.md`
- [X] T003 Verificare i pattern ignore PHP/Node/Spec Kit in `.gitignore`, `.specify/.gitignore` e `frontend/eslint.config.js`, aggiungendo solo pattern critici realmente mancanti

---

## Phase 2: Fondazioni RBAC e upgrade permissions

**Purpose**: introdurre in sicurezza il modello tenant-scoped prima di ritirare i riferimenti legacy.

**⚠️ CRITICAL**: blocca Settings, Users, Roles e navigazione.

- [X] T004 Scrivere i test forward-only per introduzione abilities, template invariati e Administrator in `tests/Feature/Authorization/PermissionCatalogueTest.php`
- [X] T005 Introdurre le sei permissions e assegnarle solo all'Administrator nella migration `database/migrations/2026_08_11_000003_add_tenant_operations_abilities.php`, mantenendo i nomi legacy protetti nello stato intermedio
- [X] T006 Aggiungere le nuove abilities a catalogo/template Administrator mantenendo transitoriamente protetti i legacy senza grant amministrativi a Editor/Viewer in `app/Support/Authorization/PermissionCatalogue.php` e `database/seeders/PermissionCatalogueSeeder.php`
- [X] T007 Scrivere test actor/context/team/Administrator per l'authorizer tenant-scoped in `tests/Feature/Authorization/TenantAbilityAuthorizerTest.php`
- [X] T008 Implementare il controllo riusabile fail-closed in `app/Support/Authorization/TenantAbilityAuthorizer.php`
- [X] T009 Aggiornare il calcolo abilities Administrator/tenant user in `app/Http/Middleware/AuthorizeApplicationAbility.php` e `app/Http/Controllers/Api/V1/ContextController.php`
- [X] T010 Eseguire `composer test:prepare` e i test mirati authorization/migration definiti in `tests/Feature/Authorization/`

**Checkpoint**: nuove abilities esistono, Administrator le usa nel Tenant selezionato, nessun ruolo
Tenant ottiene abilities protette o nuovi grant impliciti.

---

## Phase 3: User Story 1 — Cambio password personale (Priority: P1)

**Goal**: completare la superficie personale sopra il backend già presente.

**Independent Test**: actor autenticato cambia password con current password e conferma; secret
assente da response/audit/log e vecchie sessioni invalidate.

- [X] T011 [P] [US1] Verificare i test HTTP e secret-safety del cambio password in `tests/Feature/IdentityAccess/ChangeOwnPasswordTest.php`, completando soltanto eventuali delta
- [X] T012 [US1] Aggiungere client, form, route italiana e link account con loading/error/salvataggio esplicito in `frontend/src/api/auth.ts`, `frontend/src/pages/Profile/Password.tsx`, `frontend/src/components/profile/ChangePasswordForm.tsx`, `frontend/src/navigation/routes.ts`, `frontend/src/components/header/UserDropdown.tsx` e `frontend/src/App.tsx`
- [X] T013 [US1] Coprire il form personale e l'assenza di forgot-password in `frontend/src/components/profile/ChangePasswordForm.test.tsx`

**Checkpoint**: US1 verificabile senza altre operazioni piattaforma.

---

## Phase 4: User Story 2 — Audit retention globale (Priority: P2)

**Goal**: Administrator gestisce il singleton e la retention rinforzata senza toccare record business.

**Independent Test**: range/default/locking/conferma/riduzione sono coperti e solo audit eleggibili
vengono eliminati.

- [X] T014 [US2] Scrivere test Action/HTTP per default, range, stale, conferma e preservazione business/revisioni in `tests/Feature/PlatformOperations/TenantSettingsTest.php` e `tests/Feature/PlatformOperations/PlatformOperationsHttpTest.php`
- [X] T015 [US2] Implementare `UpdateAuditRetention` e la projection singleton in `app/Domain/Platform/Actions/UpdateAuditRetention.php` e `app/Http/Resources/Api/V1/PlatformSettingResource.php`
- [X] T016 [US2] Esporre GET/PUT retention protetti in `app/Http/Controllers/Api/V1/PlatformSettingsController.php` e `routes/api/v1/platform-operations.php`
- [X] T017 [US2] Implementare UI, route e navigation Administrator retention in `frontend/src/api/platform.ts`, `frontend/src/pages/Platform/Settings.tsx`, `frontend/src/components/platform/AuditRetentionForm.tsx`, `frontend/src/navigation/routes.ts`, `frontend/src/navigation/applicationNavigation.ts` e `frontend/src/App.tsx`
- [X] T018 [US2] Coprire conferma rinforzata, locked/error/loading in `frontend/src/components/platform/AuditRetentionForm.test.tsx`

---

## Phase 5: User Story 3 — Gestione globale Tenant e quota (Priority: P2)

**Goal**: mantenere la quota Administrator-only nella superficie piattaforma separata.

**Independent Test**: quota intera esatta, zero e massimo persistibile aggiornano il Tenant globale;
un tenant user non vede né invoca la superficie.

- [X] T019 [US3] Estendere test quota e projection globale in `tests/Feature/Tenancy/TenantManagementTest.php` e `tests/Feature/Api/Tenancy/ApiTenancyContractTest.php`
- [X] T020 [US3] Aggiungere quota alla validazione/action/resource globale senza esporla nei Settings delegati in `app/Domain/Tenancy/Actions/UpdateTenant.php`, `app/Http/Controllers/Api/V1/TenantController.php` e `app/Http/Resources/Api/V1/TenantResource.php`
- [X] T021 [US3] Aggiungere il campo quota esatta alla UI globale in `frontend/src/api/tenants.ts` e `frontend/src/components/tenants/TenantsView.tsx`

---

## Phase 6: User Story 4 — Audit tenant e globale (Priority: P2)

**Goal**: consultazione paginata safe, tenant-scoped o globale protetta.

**Independent Test**: stesso Tenant allow, missing permission/cross-Tenant deny, global solo
Administrator, proprietà sensibili escluse e nessun export.

- [X] T022 [US4] Scrivere test query/HTTP tenant e global audit in `tests/Feature/PlatformOperations/PlatformOperationsHttpTest.php`
- [X] T023 [US4] Implementare query filtrata e Resource minimizzato in `app/Domain/Audit/Queries/AuditEventListQuery.php` e `app/Http/Resources/Api/V1/AuditEventResource.php`
- [X] T024 [US4] Esporre controller e route tenant/global separate in `app/Http/Controllers/Api/V1/AuditController.php` e `routes/api/v1/platform-operations.php`
- [X] T025 [US4] Implementare client, pagine, route e navigation Audit tenant/globale riusando stati TailAdmin in `frontend/src/api/audit.ts`, `frontend/src/pages/Audit/Home.tsx`, `frontend/src/components/audit/AuditEventsView.tsx`, `frontend/src/navigation/routes.ts`, `frontend/src/navigation/applicationNavigation.ts` e `frontend/src/App.tsx`
- [X] T026 [US4] Coprire loading/error/empty/paginazione e visibilità global/tenant in `frontend/src/components/audit/AuditEventsView.test.tsx`

---

## Phase 7: User Story 5 — Notifiche actor-scoped (Priority: P2)

**Goal**: sostituire i dati demo con database notifications reali permission-scoped.

**Independent Test**: l'actor vede soltanto le proprie notifiche, incluso empty state e metadata di
fallimento disponibili; nessun retry/realtime viene introdotto.

- [X] T027 [US5] Scrivere test HTTP actor-only, permission, inactive e safe payload in `tests/Feature/PlatformOperations/PlatformOperationsHttpTest.php`
- [X] T028 [US5] Implementare Resource/controller/route actor-scoped in `app/Http/Resources/Api/V1/NotificationResource.php`, `app/Http/Controllers/Api/V1/NotificationController.php` e `routes/api/v1/platform-operations.php`
- [X] T029 [US5] Sostituire i placeholder con client e dropdown reale in `frontend/src/api/notifications.ts` e `frontend/src/components/header/NotificationDropdown.tsx`
- [X] T030 [US5] Coprire permission/loading/error/empty e assenza dati demo in `frontend/src/components/header/NotificationDropdown.test.tsx`

---

## Phase 8: User Story 6 — Overview operativa globale (Priority: P2)

**Goal**: Administrator vede stato operativo senza economia o telemetria.

**Independent Test**: projection contiene solo stato/count/attività/alert supportati e route è
protetta; nessuna query economica o behavioral data è presente.

- [X] T031 [US6] Scrivere test authorization e shape non economica in `tests/Feature/PlatformOperations/PlatformOperationsHttpTest.php`
- [X] T032 [US6] Implementare query aggregata e Resource in `app/Domain/Platform/Queries/PlatformOverviewQuery.php` e `app/Http/Resources/Api/V1/PlatformOverviewTenantResource.php`
- [X] T033 [US6] Esporre endpoint protetto in `app/Http/Controllers/Api/V1/PlatformOverviewController.php` e `routes/api/v1/platform-operations.php`
- [X] T034 [US6] Implementare pagina, route e navigation operativa senza KPI economici in `frontend/src/pages/Platform/Overview.tsx`, `frontend/src/components/platform/PlatformOverviewView.tsx`, `frontend/src/navigation/routes.ts`, `frontend/src/navigation/applicationNavigation.ts` e `frontend/src/App.tsx`
- [X] T035 [US6] Coprire stati UI e assenza di campi economici in `frontend/src/components/platform/PlatformOverviewView.test.tsx`

---

## Phase 9: User Story 7 — Reset emergenza Administrator (Priority: P1)

**Goal**: conservare il comando conforme senza riscrittura inutile.

**Independent Test**: input hidden, nessuna credential di default, sessioni invalidate e audit safe.

- [X] T036 [US7] Rieseguire e completare solo i delta del comando in `tests/Feature/Console/ResetAdministratorPasswordCommandTest.php` e `app/Console/Commands/ResetAdministratorPasswordCommand.php`

---

## Phase 10: User Story 8 — Impostazioni Tenant correnti (Priority: P1)

**Goal**: projection e mutazione focalizzate per Generali, con IVA forward-only e budget lock.

**Independent Test**: matrice authorization completa, payload chiuso, stale atomico, esattezza IVA
Expense/Contract e deletion reason future-only.

- [X] T037 [US8] Scrivere test Action/HTTP Settings per allow/deny/view-only/cross-Tenant/inactive/stale/projection/audit in `tests/Feature/PlatformOperations/TenantSettingsTest.php`
- [X] T038 [US8] Scrivere test end-to-end IVA 22→20 per Expense row, Contract term e revisioni in `tests/Feature/PlatformOperations/TenantVatDefaultTest.php`
- [X] T039 [US8] Scrivere test deletion reason false→true e isolamento in `tests/Feature/PlatformOperations/TenantSettingsTest.php`
- [X] T040 [US8] Implementare Action e budget-lock riusando l'authorizer in `app/Domain/Tenancy/Actions/UpdateTenantSettings.php`
- [X] T041 [US8] Implementare projection focalizzata e controller senza tenant id in `app/Http/Resources/Api/V1/TenantSettingsResource.php`, `app/Http/Controllers/Api/V1/TenantSettingsController.php` e `routes/api/v1/platform-operations.php`
- [X] T042 [US8] Implementare API/form Generali read-only/update con helper e lock reason in `frontend/src/api/tenantSettings.ts`, `frontend/src/pages/Settings/General.tsx` e `frontend/src/components/settings/TenantGeneralSettingsForm.tsx`
- [X] T043 [US8] Coprire loading/error, read-only/update, Annulla/Salva, helper IVA e budget lock in `frontend/src/components/settings/TenantGeneralSettingsForm.test.tsx`

---

## Phase 11: User Story 9 — Utenti tenant-scoped view/manage (Priority: P1)

**Goal**: delegare letture e mutazioni correnti, con lookup ruoli indipendente dal role management.

**Independent Test**: view consente list/show; manage consente operazioni e lookup; cross-Tenant,
platform identity/role e permission mancante falliscono senza disclosure o secrets.

- [X] T044 [US9] Aggiornare test policy/action/HTTP alla matrice view/manage e lookup ruoli in `tests/Feature/Authorization/ProtectedPermissionTest.php`, `tests/Feature/IdentityAccess/TenantUserMembershipTest.php` e `tests/Feature/Api/Users/ApiUsersHttpTest.php`
- [X] T045 [US9] Migrare `UserPolicy` e tutte le Actions User alle nuove abilities in `app/Policies/UserPolicy.php`, `app/Domain/IdentityAccess/Actions/CreateTenantUser.php`, `app/Domain/IdentityAccess/Actions/UpdateTenantUser.php`, `app/Domain/IdentityAccess/Actions/AssignTenantRoles.php`, `app/Domain/IdentityAccess/Actions/DeactivateTenantUser.php` e `app/Domain/IdentityAccess/Actions/ResetTenantUserPassword.php`
- [X] T046 [US9] Separare route view/manage e aggiungere lookup minimo in `routes/api/v1/identity-master.php`, `app/Http/Controllers/Api/V1/TenantUserController.php` e `app/Http/Resources/Api/V1/TenantRoleOptionResource.php`
- [X] T047 [US9] Adattare pagina/client/component a `canView` e `canManage` in `frontend/src/api/users.ts`, `frontend/src/pages/Users/Home.tsx` e `frontend/src/components/users/UsersView.tsx`
- [X] T048 [US9] Coprire ricerca/empty/loading/error e assenza mutazioni view-only in `frontend/src/components/users/UsersView.test.tsx`

---

## Phase 12: User Story 10 — Ruoli e Permessi tenant-scoped (Priority: P1)

**Goal**: lettura delegata e gestione sicura del catalogo chiuso.

**Independent Test**: view è read-only; manage muta; protected/arbitrary/cross-Tenant denials sono
atomici e le sei nuove abilities sono assegnabili.

- [X] T049 [US10] Aggiornare test policy/action/HTTP per view/manage e escalation denial in `tests/Feature/Authorization/TenantRoleManagementTest.php` e `tests/Feature/Api/Roles/ApiRolesHttpTest.php`
- [X] T050 [US10] Migrare `RolePolicy` e Actions Role alle nuove abilities e authorizer in `app/Policies/RolePolicy.php`, `app/Domain/IdentityAccess/Actions/CreateTenantRole.php`, `app/Domain/IdentityAccess/Actions/UpdateTenantRole.php` e `app/Domain/IdentityAccess/Actions/DeleteTenantRole.php`
- [X] T051 [US10] Separare route view/manage e mantenere catalogo solo manage in `routes/api/v1/identity-master.php` e `app/Http/Controllers/Api/V1/TenantRoleController.php`
- [X] T052 [US10] Adattare pagina/component/client a view-only/manage e dettaglio leggibile in `frontend/src/api/roles.ts`, `frontend/src/pages/Roles/Home.tsx` e `frontend/src/components/roles/RolesView.tsx`
- [X] T053 [US10] Coprire dettaglio abilities, gruppi, empty/loading/error e mutazioni nascoste in `frontend/src/components/roles/RolesView.test.tsx`

---

## Phase 13: User Story 11 — Area Impostazioni e route italiane (Priority: P1)

**Goal**: un'unica area con prima sezione accessibile e pagine esistenti integrate.

**Independent Test**: ogni combinazione abilities vede soltanto tabs consentite; `/impostazioni`
risolve nell'ordine stabile e vecchie route non si rompono.

- [X] T054 [US11] Implementare route constants, resolver e redirect Settings in `frontend/src/navigation/routes.ts`, `frontend/src/navigation/settingsNavigation.ts` e `frontend/src/App.tsx`
- [X] T055 [US11] Implementare layout/tab accessibile e integrare Planning Years/Cost Centers esistenti in `frontend/src/pages/Settings/Layout.tsx` e `frontend/src/pages/Settings/Index.tsx`
- [X] T056 [US11] Aggiornare sidebar con un entrypoint Impostazioni ability-any mantenendo Piattaforma separata in `frontend/src/navigation/applicationNavigation.ts` e `frontend/src/layout/AppSidebar.tsx`
- [X] T057 [US11] Aggiungere label italiane e gruppi per le sei abilities in `frontend/src/presentation/labels.ts`
- [X] T058 [US11] Coprire visibilità, redirect prima sezione, route legacy e layout responsive in `frontend/src/navigation/settingsNavigation.test.ts` e `frontend/src/pages/Settings/SettingsRouting.test.tsx`

---

## Phase 14: User Story 12 — Administrator senza impersonazione (Priority: P1)

**Goal**: nessuna regressione nelle superfici globali o tenant-scoped dell'Administrator.

**Independent Test**: Administrator conserva platform Tenant, entra/esce dal contesto e usa
Settings/Users/Roles senza membership; tenant user non ottiene platform access.

- [X] T059 [US12] Aggiungere regressioni HTTP/action Administrator e tenant user in `tests/Feature/Tenancy/AdministratorContextAuditTest.php` e `tests/Feature/PlatformOperations/PlatformOperationsHttpTest.php`
- [X] T060 [US12] Coprire navigation Tenant piattaforma vs Impostazioni in `frontend/src/navigation/applicationNavigation.test.ts`

---

## Phase 15: Polish, documentazione e gate finali

**Purpose**: coerenza cross-story, contract e verifiche reali.

- [X] T061 Scrivere le asserzioni finali di ritiro in `tests/Feature/Authorization/PermissionCatalogueTest.php`, aggiungere la migration cleanup `database/migrations/2026_08_11_000004_retire_legacy_platform_operation_abilities.php`, rimuovere i nomi legacy da `app/Support/Authorization/PermissionCatalogue.php` e verificare con `rg` che non restino consumer in `app/`, `routes/`, `tests/` e `frontend/src/`
- [X] T062 Aggiornare le sole regole durevoli implementate in `docs/DOMAIN.md` e, solo se necessario, i vincoli in `docs/ARCHITECTURE.md`
- [X] T063 Aggiornare stato Feature 008 e comandi realmente verificati in `docs/STATUS.md`, `docs/OPERATIONS.md` e `specs/README.md`
- [X] T064 Verificare contract, route, controller, Resource e HTTP tests contro `specs/008-platform-operations/contracts/platform-operations-api.md`
- [X] T065 Eseguire i gate backend `composer test:static`, `composer test:prepare`, `composer test:accounting`, `composer test:application` e `composer verify`
- [X] T066 Eseguire dalla directory `frontend` i gate `npm ci` e `npm run verify`
- [X] T067 Eseguire gli scenari finali di `specs/008-platform-operations/quickstart.md` e marcare tutti i task completati solo con evidenza reale

---

## Dependencies & Execution Order

### Phase dependencies

- Phase 1 non ha dipendenze.
- Phase 2 dipende da Phase 1 e blocca US8/US9/US10/US11/US12.
- US1 e US7 riusano il backend corrente e possono iniziare dopo Phase 1.
- US2–US6 dipendono dalle fondazioni comuni ma sono indipendenti tra loro.
- US8, US9 e US10 dipendono da Phase 2; US11 dipende dalle loro route/abilities UI; US12 segue
  l'integrazione delle superfici.
- Phase 15 dipende da tutte le stories.

### Within each story

- Test prima dell'implementazione corrispondente.
- Action/Query prima di Controller/Resource/route.
- Contract backend prima del client/pagina.
- Test mirati prima di marcare la story completa.

### Parallel opportunities

- Dopo Phase 2, US2 retention, US4 audit, US5 notifications e US6 overview toccano moduli distinti.
- I test frontend di una story possono essere scritti mentre il relativo backend contract è stabile.
- US1 e US7 sono indipendenti dagli altri moduli.
- Task che condividono `routes/api/v1/platform-operations.php`, `App.tsx`, navigation, catalogue o
  Policies restano sequenziali.

## Implementation Strategy

### MVP tecnico sicuro

1. Contract/setup.
2. Permission migration + authorizer.
3. Settings/Users/Roles backend con test authorization.
4. Area Impostazioni e modalità view/manage.
5. Operazioni platform/audit/notifications/account.
6. Gate, documentazione e acceptance.

### Completion rule

Ogni task viene marcato `[X]` soltanto dopo modifica e test applicabile. Nessun commit, push, merge
o PR fa parte dei task.
