# Implementation Plan: Feature 008 — Platform operations

**Branch**: `feature/008-platform-operations-sdd` | **Date**: 2026-08-11 | **Spec**: [spec.md](spec.md)

**Status**: `IMPLEMENTED AND VERIFIED — all repository gates passed`

**Input**: Feature specification from `specs/008-platform-operations/spec.md`

## Summary

Completare le operazioni piattaforma e Tenant con una slice Laravel/React verticale. La gestione
globale Tenant resta protetta; Settings, Users e Roles diventano tenant-scoped tramite sei nuove
abilities e verificano il permission team del Tenant corrente. Si aggiungono superfici/API per
settings, audit, notifiche, retention e overview, si completa la UI password e si mantiene il
comando di reset già conforme. Una migration forward-only introduce le abilities prima di ritirare
i tre nomi legacy senza attribuire privilegi amministrativi a Editor/Viewer.

## Technical Context

**Language/Version**: PHP 8.3.32, TypeScript 5.7.2, React 19

**Primary Dependencies**: Laravel 13.22, Sanctum 4.3, Spatie Permission 8.3, BCMath, React Router
7.1, Axios 1.19, TailAdmin React Free 2.3

**Storage**: MySQL 8.4/InnoDB strict mode; tabelle correnti `tenants`, `users`, permission tables,
`platform_settings`, `audit_events`, `notifications`, `sessions`

**Testing**: Pest/PHPUnit con `DatabaseTransactions`, Larastan/PHPStan/Pint; Vitest, Testing Library,
ESLint, TypeScript/Vite. Migration test forward-only; nessun reset/truncation database.

**Target Platform**: API Laravel e SPA React same-origin in ambiente Linux/Compose

**Project Type**: Web application API-only backend + SPA frontend

**Performance Goals**: tutte le liste operative paginate (massimo 100 elementi per pagina); overview
con aggregazioni SQL per Tenant e nessuna scansione economica cross-Tenant; nessuna query N+1 per
ruoli/user/audit.

**Constraints**: tenant isolation fail-closed; esattezza decimale; optimistic locking; audit senza
segreti; nessun Redis/WebSocket/worker permanente; nessuna nuova dipendenza; nessun OpenAPI globale
parziale; UI italiana responsive usando componenti esistenti.

**Scale/Scope**: una piattaforma multi-Tenant, un solo Tenant per tenant user, cinque sezioni
Impostazioni, sei nuove abilities, API v1 e test backend/frontend completi.

## Constitution Check

*GATE pre-design: PASS. GATE post-design: PASS.*

- **Documentazione minima**: i requisiti non implementati restano in Feature 008; i documenti
  permanenti saranno aggiornati solo dopo i gate finali.
- **Slice verticale**: ogni story attraversa policy/action/API/UI/test dove necessario.
- **Autorità sul corrente**: il plan riusa Actions, controller, Resource, test e componenti correnti;
  non usa gli Spec Kit 001–007 come fonte.
- **Decisioni prodotto**: tutte le scelte ad alto impatto sono nel prompt/spec; clarify non ha
  rilevato questioni bloccanti.
- **Laravel business owner**: autorizzazione, locking, default IVA, retention e proiezioni restano
  server-side.
- **Tenant isolation**: middleware, `TenantContext`, permission team e query tenant-scoped sono tutti
  richiesti; Administrator usa il Tenant selezionato senza membership/impersonazione.
- **Denaro**: i flussi esistenti di Expense/Contract persistono l'aliquota risolta e usano BCMath;
  il setting non introduce observer o riscritture storiche.
- **Actions esplicite**: mutazioni Settings/Retention restano transazionali e auditabili.
- **Semplicità**: nessun repository generico, CQRS, event bus, service locator o package settings.
- **Test**: include allow/deny/cross-tenant/inactive/rollback, precisione esatta e gate reali.

## Architecture and data flow

### Authorization flow

```text
request authenticated + active user
        |
        +-- platform route --> protected catalogue --> PlatformAdministrator (team 0)
        |
        +-- tenant route --> ResolveTenantContext --> permission team = tenant_id
                                  |
                                  +-- global Administrator: protected identity + selected Tenant
                                  +-- tenant user: persisted tenant_id == context tenant_id
                                  |
                                  +-- application-ability: tenant-scoped ability
                                  +-- Action/Policy repeats authoritative actor/context/ability check
```

Un helper focalizzato `TenantAbilityAuthorizer` centralizza soltanto la validazione ripetuta di
actor persistito attivo, context actor, Tenant persistito, membership o identità Administrator e
ability nel team corretto. Non sostituisce middleware o Policies e non mantiene stato globale.

### Tenant settings flow

`GET /api/v1/tenant-settings` risolve il Tenant dal request context e produce
`TenantSettingsResource`; non legge ID client. `PUT /api/v1/tenant-settings` valida il payload
chiuso e delega a `UpdateTenantSettings`, che rilegge/locka il Tenant, verifica `lock_version`,
mantiene il budget lock tramite `approval_operations`, salva i soli campi approvati, incrementa la
versione e registra `tenant.settings.updated`. La response include `budget_basis_locked` e reason.

Expense e Contract continuano a risolvere un `vat_rate` omesso dal `default_vat_rate` del Tenant al
momento della creazione e persistono l'aliquota effettiva; nessun codice Settings li aggiorna.

### Identity management flow

Le route di lettura Users usano `tenant-users.view`; le mutazioni usano `tenant-users.manage`.
Il nuovo lookup `GET /api/v1/user-role-options` richiede `tenant-users.manage` e restituisce solo id
e nome dei ruoli del Tenant corrente. Le route Roles separano view/manage; il catalogo abilities è
disponibile al role manager e continua a derivare solo da `PermissionCatalogue::tenantAbilities()`.
Policies e Actions usano le nuove abilities e `TenantAbilityAuthorizer`.

### Permission migration

Due migration forward-only mantengono sicuro anche lo stato intermedio dell'upgrade:

1. la migration di introduzione crea le sei nuove permissions e le assegna al solo ruolo globale
   `Administrator` già protetto, senza grant a `Editor`, `Viewer` o ruoli Tenant;
2. catalogue, Actions, Policies, route, test e frontend passano alle nuove abilities mentre i tre
   nomi legacy restano transitoriamente protetti ma non assegnabili;
3. una migration di cleanup successiva rimuove dai pivot i riferimenti ai tre nomi legacy e poi
   elimina le permissions legacy, che non erano assegnabili ai ruoli Tenant dal catalogo corrente;
4. entrambe lasciano `down()` non distruttivo, perché ripristinare nomi autorizzativi ritirati produrrebbe una
   regressione di sicurezza.

Il seeder autorevole converge il catalogo senza cancellazioni indiscriminate e mantiene i template
Editor/Viewer privi delle nuove abilities amministrative.

### Platform operations flow

- `PlatformSettingsController` + `UpdateAuditRetention` espongono il singleton e applicano range,
  locking e conferma rinforzata prima di eliminare esclusivamente `audit_events` più vecchi.
- `AuditController` usa una query paginata tenant-scoped; la route globale separata usa la protected
  ability e può filtrare per Tenant senza esporre payload sensibili.
- `NotificationController` legge esclusivamente `notifications()` dell'actor autenticato; nessun
  dato dimostrativo o retry implicito.
- `PlatformOverviewController` aggrega esclusivamente stato Tenant, conteggio user, ultima attività
  audit e rinnovi Contract correnti già persistiti, senza query o somme economiche. Gli errori
  operativi sono inclusi solo quando esiste una fonte persistita corrente; non vengono inferiti da
  log o dati inventati.
- `ChangeOwnPassword` e `admin:reset-password` sono mantenuti; si aggiunge soltanto la UI personale e
  il collegamento di navigazione necessario.

### UI flow

`/impostazioni` usa un resolver deterministico delle sezioni accessibili. Un layout/settings nav
riutilizzabile mostra tabs/links autorizzati. Generali usa un form esplicito. Users/Roles ricevono
`canView`/`canManage`; in read-only caricano le liste ma non ruoli lookup/catalogo né affordance di
mutazione. Le pagine esistenti Planning Years e Cost Centers sono montate sotto le nuove route e le
vecchie route italiane/legacy reindirizzano. `/tenant` resta nella sezione Piattaforma.

## API and error behavior

- Tutti gli endpoint mantengono envelope e paginazione correnti.
- 401 per sessione assente; 403 per ability/context/inactive; 404 per risorse cross-Tenant; 409 per
  `STALE_VERSION`, `TENANT_BUDGET_BASIS_LOCKED` e invarianti conflittuali; 422 per input chiuso,
  ability protetta/arbitraria e conferma retention errata.
- Le transazioni non registrano audit di successo su failure; gli errori conservano correlation ID.
- Nessun endpoint Settings accetta `tenant_id`; nessuna response espone password/hash, quota nella
  superficie delegata o campi Tenant non approvati.

## Test strategy

- **Authorization/catalogue/migration**: nuove abilities, template invariati, Administrator completo,
  legacy ritirati in sicurezza, protected assignment denial.
- **Settings HTTP/action**: allow/deny/view-only/cross-Tenant/inactive/stale, budget lock, field
  projection, audit metadata, IVA exact forward-only e deletion reason future-only.
- **Users/Roles HTTP/action**: split view/manage, user-role lookup, role/platform/cross-Tenant deny,
  arbitrary permission deny, safe password reset audit.
- **Platform operations**: retention boundaries/confirmation/non-business preservation; tenant/global
  audit; actor-only notifications; non-economic overview; existing password/console compliance.
- **Frontend**: routing/first accessible, navigation visibility, Generali form states and helpers,
  Users/Roles view/manage/empty/loading/error, separate Tenant platform nav, notification/password,
  responsive class contracts.

## Rollback and recovery

- Application failures roll back mutations and success audit in the same DB transaction.
- Stale updates require a fresh GET; no client-side merge or silent retry.
- Permission migration is forward-only. Rollback applicativo non ricrea automaticamente permissions
  legacy; un eventuale rollback release richiede codice compatibile con le nuove abilities.
- Retention is intentionally irreversible only after reinforced confirmation; it never targets
  business/revision/version tables.
- Settings changes do not trigger mass updates, making operational rollback a normal subsequent
  settings update when the budget basis is not locked.

## Project Structure

### Documentation (this feature)

```text
specs/008-platform-operations/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/platform-operations-api.md
├── checklists/platform-operations.md
└── tasks.md
```

### Source Code (repository root)

```text
app/
├── Domain/{Audit,IdentityAccess,Platform,Tenancy}/
├── Http/Controllers/Api/V1/
├── Http/Resources/Api/V1/
├── Policies/
└── Support/Authorization/
database/
├── migrations/
└── seeders/
routes/api/v1/
tests/{Accounting,Architecture,Feature}/
frontend/src/
├── api/
├── components/{audit,notifications,platform,roles,settings,users}/
├── navigation/
├── pages/{Audit,Notifications,Platform,Profile,Settings}/
└── presentation/
```

**Structure Decision**: mantenere il monolite Laravel API-only e la SPA React esistenti. I nuovi
file sono focalizzati sulle superfici mancanti; Users, Roles, Planning Years e Cost Centers sono
adattati, non riscritti.

## Complexity Tracking

Nessuna violazione costituzionale o complessità aggiuntiva da giustificare.
