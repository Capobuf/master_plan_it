# Implementation Plan: Workspace Annuale e Spesa Autorevole

**Branch**: `agent/023-annual-expense-workspace` | **Date**: 2026-08-12 | **Spec**: [spec.md](spec.md)

**Planning Status**: `PROPOSED TARGET — Phase 0/1 complete; not implemented`

**Input**: Feature specification from `specs/023-annual-expense-workspace/spec.md`

## Summary

Consegnare la prima Slice verticale del programma 022: una top shell minimale mantiene Tenant e Anno, le Impostazioni espongono la Base Economica ufficiale, la Spesa senza lifecycle Aperta/Chiusa accetta Estimate/Quote e Actual positivi o negativi con Data reale indipendente, e Documento/Registro/Budget/Report/Dashboard riconciliano tramite una sola proiezione Laravel.

L'implementazione riusa la struttura Laravel API-only + React/TailAdmin, le Actions transazionali, il Tenant context, il Money layer e le revisioni aggregate. Il cutover del lifecycle Expense è Greenfield e atomico: non introduce dual-read, backfill o payload `open/closed`; conserva invece le superfici baseline esplicitamente classificate `MIGRATION-ONLY` fino alle Slice proprietarie. Non introduce un secondo motore, repository, CQRS o totali persistiti.

## Technical Context

**Language/Version**: PHP 8.3.32; TypeScript 5.7.2; React 19

**Primary Dependencies**: Laravel 13.22, Sanctum 4.3, Spatie Permission 8.3, Overtrue Laravel Versionable 6, TailAdmin React Free 2.3, React Router 7, Tailwind CSS 4, Axios, BCMath; Xdebug come solo nuovo driver di test nell'immagine PHP

**Storage**: MySQL 8.4/InnoDB strict mode; `DECIMAL(19,2)` per denaro, FK Tenant composite, optimistic `lock_version`, soft delete e revision snapshot esistenti

**Testing**: Pest 4/PHPUnit 12 (`Architecture`, `Accounting`, `Application`), Larastan/PHPStan, Pint, Composer audit, Vitest 3 + Testing Library, ESLint, TypeScript/Vite build, Xdebug line+branch coverage

**Target Platform**: applicazione Web self-hosted; Laravel API privata dietro proxy same-origin Vite; Docker Compose con servizi `laravel.test`, `mysql`, `frontend`

**Project Type**: Web application API-only Laravel + SPA React

**Performance Goals**: un caricamento della proiezione annuale con conteggio query costante rispetto al numero di Righe/Spese; Registro paginato; nessun calcolo economico ripetuto per superficie

**Constraints**: Tenant isolation fail-closed con la sola eccezione amministrativa già protetta per il Platform Administrator; server authorization; stringhe decimali e BCMath; un solo motore/proiezione; guardia transazionale Tenant/Anno condivisa; Actions esplicite; nessun fallback silenzioso; no lifecycle Spesa; Data reale indipendente dall'Anno; schema Greenfield resettabile solo tramite comando protetto; top shell TailAdmin; niente modifiche permanenti ai docs prima della verifica

**Scale/Scope**: Tenant/Anno con fino a 1.000 Spese e 10.000 Righe nel benchmark di Slice; cinque consumer della stessa proiezione; desktop primario con shell responsive

## Constitution Check

*GATE: passed before Phase 0 research and re-checked after Phase 1 design.*

| Principle | Pre-design | Post-design evidence |
|---|---|---|
| Documentazione permanente minima | PASS | Tutto il target resta in `specs/023`; i docs permanenti sono task shared-owner soltanto dopo implementazione verificata |
| Spec Kit verticale | PASS | Ogni storia attraversa schema/dominio/API/React/test e produce un percorso utente dimostrabile |
| Autorità sul corrente | PASS | Research distingue baseline e delta; nessun target è dichiarato corrente |
| Decisioni del Product Owner | PASS | Greenfield, Base, pianificazione unica, Actual negativi, no lifecycle e Date separate sono applicati senza nuove decisioni |
| Laravel unico business owner | PASS | Preview, save e tutte le superfici consumano proiezione Laravel; React presenta soltanto |
| Un solo Motore Economico | PASS BY TARGET | `EconomicEngine` produce `AnnualEconomicProjection`; le query consumer non ridefiniscono formule |
| Tenant isolation/authorization | PASS BY TARGET | FK composite, query scoped, relazioni body non enumerabili, eccezione Platform Admin esplicita e test allow/deny/foreign/inactive |
| Denaro esatto | PASS BY TARGET | grammatica decimale esplicita, `DECIMAL(19,2)`, BCMath scale 12, arrotondamento half-away-from-zero e test segno/IVA/overflow |
| Actions esplicite | PASS | Create/update/settings restano transazionali; nessun observer o hook economico |
| Minima complessità | PASS | DTO mirati, nessun repository/CQRS/event bus/service locator/framework universale |
| Test e sicurezza | PASS BY PLAN | tests-first, concorrenza MySQL reale, rollback, stale, redazione audit, Frontend e Xdebug 100% line+branch su allowlist obbligatoria |

## Project Structure

### Documentation (this feature)

```text
specs/023-annual-expense-workspace/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── tasks.md
├── lifecycle-inventory.md
├── contracts/
│   └── api-contract.md
└── checklists/
    └── requirements.md
```

### Source Code (repository root)

```text
app/
├── Domain/
│   ├── Economics/{Data,Services}/
│   ├── Expenses/{Actions,Data,Enums,Queries,Services}/
│   ├── Reporting/Queries/
│   └── Tenancy/{Actions,Data,Enums}/
├── Http/{Controllers,Resources}/Api/V1/
├── Models/{Tenant,Expense,ExpenseRow}.php
├── Policies/ExpensePolicy.php
└── Support/{Api,Authorization,Diagnostics}/

database/
├── factories/{Tenant,PlanningYear,Expense,ExpenseRow}Factory.php
├── migrations/
└── seeders/{DatabaseSeeder,DemoDataSeeder}.php

routes/api/v1/{expenses,reporting,tenant-settings}.php

frontend/src/
├── api/{client,tenantSettings,expenses,budget,reports,dashboard}.ts
├── components/{expenses,header,settings}/
├── context/{ApplicationContext,PlanningYearContext}.tsx
├── layout/{AppLayout,AppHeader}.tsx
├── navigation/
└── pages/{Expenses,Budget,Reports,Settings}/

docker/8.3/Dockerfile
phpunit.economic-coverage.xml
app/Console/Commands/TestResetGreenfield.php
tests/{Accounting,Architecture,Feature,Support}/
frontend/src/**/*.test.tsx
```

**Structure Decision**: mantenere i confini esistenti. `EconomicDatasetQuery` carica il dataset Tenant/Anno, `EconomicEngine` produce la proiezione pura, Query/Resource espongono viste specifiche e Actions possiedono le mutazioni. La top shell riusa componenti TailAdmin e route italiane correnti.

## Shared Ownership and Write Boundaries

| Surface | Owner | Rule |
|---|---|---|
| Consolidated migrations, modelli ed enum condivisi | Primary integration owner | La Slice fornisce modello, contract e test schema; soltanto l'owner modifica migration, `Tenant`, `Expense`, `ExpenseRow` e relativi enum condivisi |
| `app/Domain/Economics/Services/EconomicEngine.php` e DTO/proiezione core | Primary integration owner | Un solo writer; i consumer della Slice dipendono dal contratto stabilizzato |
| `app/Support/Api/ApiErrorResponse.php`, `app/Support/Diagnostics/CorrelationId.php` | Primary integration owner | Riusa i path verificati; nessun catalogo errori o correlation helper parallelo |
| Guardia annuale, `routes/api.php`, `routes/api/v1/*.php` | Primary integration owner | La guardia e la rimozione/addizione route sono integrate una volta; nessuna Action economica resta fuori dal protocollo di lock |
| Permission catalogue/seeder/tests | Primary integration owner | La Slice riusa abilities esistenti e chiede solo verifica/aggiornamento condiviso |
| `docs/*.md`, `specs/README.md`, programma 022 | Primary integration owner | Aggiornare soltanto dopo comportamento implementato e verificato |

Il writer della Slice possiede Actions/validator/query/Resource/controller non shared, Factory/Seeder di dominio, frontend API/component/page/layout e test focalizzati, salvo diversa assegnazione del pacchetto di implementazione. I modelli e gli enum condivisi restano sempre del primary integration owner.

## Phase 0: Research Result

[research.md](research.md) risolve Greenfield protetto, Base/blocco, selezione/Data, rimozione lifecycle, guardia annuale, forma della proiezione, API preview/errori, top shell, revision/audit, coverage e shared ownership. Non restano chiarimenti di prodotto della Slice 023; le due domande Plafond/Extra restano confinate alla Slice 024.

## Phase 1: Design Result

- [data-model.md](data-model.md): target schema logico, invarianti, relazioni e transizioni;
- [contracts/api-contract.md](contracts/api-contract.md): endpoint, payload, errori e parità delle cinque superfici;
- [quickstart.md](quickstart.md): scenari end-to-end e comandi Docker/MySQL/Frontend/coverage;
- [tasks.md](tasks.md): implementazione tests-first, dependency-ordered e per User Story;
- [lifecycle-inventory.md](lifecycle-inventory.md): mappa esaustiva remove/rewrite/retain del cutover lifecycle Expense, verificata da test Architecture.

## Implementation Sequence

1. **Contract and failing tests**: fissare schema, errori, proiezione, API e adapter Frontend con test rossi.
2. **Shared foundation integration**: primary integration owner consolida migration, comando Greenfield protetto, guardia annuale, proiezione core, route/errori/permission e Xdebug Gate.
3. **US1 Context and basis**: settings model/Action/API/UI e top shell con invalidazione/dirty guard.
4. **US2 Authoritative Expense**: validator/Actions/DTO/Resources/editor con selezione, Date e decimali.
5. **US3 Lifecycle removal**: rimozione completa Backend/Frontend e regressioni route/schema.
6. **US4 Surface reconciliation**: consumer Documento/Registro/Budget/Report/Dashboard e test Accounting/parity.
7. **Cross-cutting gate**: fresh schema/seed, static, Accounting, Application, Frontend, build, coverage e acceptance.

## Test Strategy

### Tests first

Ogni fase di User Story inizia da test che devono fallire per il comportamento mancante. I test schema/contract precedono i file shared-owner; test Unit/Accounting precedono core e consumer; test component/adapter precedono React.

### Numeric canonical matrix

Per Base Netto e Lordo coprire almeno:

- Estimate `100.00`, Quote corrente `110.00`, altra Quote non corrente `120.00`;
- Actual `40.00`, `65.00`, `-5.00`, `0.00`;
- IVA 22% esclusa e inclusa, inclusi importi negativi;
- actual-only, una planning, più alternative, nessuna/doppia selezione invalida;
- Date stesso anno, precedente e successivo all'Anno Economico;
- deleted/non-current rows escluse.

Ogni campo decimale di richiesta usa `^-?(0|[1-9]\d*)(\.\d{1,2})?$`: niente JSON number, `+`, spazi, virgole, esponenti o zeri iniziali; lo zero negativo viene normalizzato. La modalità diretta invia soltanto `entered_amount`; la modalità calcolata invia soltanto `quantity` e `unit_price`. Il server moltiplica/divide con BCMath a scala intermedia 12, arrotonda half-away-from-zero a due decimali, applica la stessa regola per IVA inclusa/esclusa e rifiuta overflow prima della persistenza.

### Transaction and locking matrix

- Guardia ordinaria: lock del `PlanningYear` Tenant-scoped, poi Expense e Righe in ordine stabile; per operazioni multi-Anno i PlanningYear sono lockati per ID crescente. Se una mutazione tocca anche stato Tenant, locka prima il Tenant.
- Ogni mutazione che può cambiare il dataset economico usa la stessa guardia: create/update/delete/restore/bulk Expense, generazione/sincronizzazione Contract, azioni Project e gli endpoint Budget correnti o futuri.
- Preview verifica subito versioni root/riga ma non riserva lock; il salvataggio ripete guardia, letture e validazione nella stessa transazione.
- La Slice 023 adatta l'Approvazione esistente soltanto come bridge: il primo successo imposta `economic_basis_locked_at` atomicamente. La Slice 025 possiede il nuovo lifecycle di Approvazione.
- I test Accounting usano transazioni MySQL concorrenti reali e provano che una sola composizione può vincere senza write skew o side effect parziali.

### Required gates

1. Unit: Money/IVA/proiezione e tabella decisionale.
2. Accounting MySQL: dataset/proiezione, aggregazione, query count, exact reconciliation e rollback.
3. Feature/API: schema contract, settings, Expense CRUD/preview, error envelope, tenant/ability/inactive/stale e redazione audit.
4. Frontend: adapter, top shell, settings, editor, register/detail/Budget/Report/Dashboard, loading/empty/error/dirty guard.
5. Xdebug: 100% line + 100% branch sull'allowlist versionata delle classi economiche pure; il Gate fallisce se una classe/metric report manca o è sotto soglia.
6. Static/build: Composer validation/audit, Architecture, Pint, PHPStan, ESLint, TypeScript/Vite.
7. Parity: payload e comportamento identici tra API adapter e le cinque superfici, nessun campo legacy o formula React.

## Performance and Observability

- Un test Accounting misura un tetto costante di query per la proiezione annuale a 1 e 1.000 Spese; nessun loop emette query per record.
- Gli errori inattesi e di riconciliazione conservano `X-Correlation-ID` in header/envelope/log senza dati sensibili.
- Nessun requisito introduce metriche, cache o servizi esterni; eventuali ottimizzazioni devono preservare lo stesso output puro.

## Rollout and Greenfield Validation

- Ricostruire soltanto il database MySQL di test autorizzato con `php artisan app:test-reset-greenfield --seed`; il comando rifiuta ambiente diverso da `local|testing`, driver/host/database fuori allowlist o strict mode disattivato. Il comando Artisan distruttivo sottostante non è mai invocato direttamente da sviluppatori, documentazione o CI.
- Backend e Frontend vengono integrati atomicamente; non esiste finestra di compatibilità `state/open/closed`.
- Il cutover non esegue import o backfill e non tocca storage Allegati o policy di Cestino.

## Definition of Done

La Slice è completa soltanto quando:

1. le quattro User Story e tutte le acceptance sono dimostrabili;
2. schema/Factory/Seeder partono da MySQL vuoto;
3. API e Frontend non espongono lifecycle o payload legacy;
4. Laravel applica permission, Tenant isolation, lock e rollback senza affidarsi al client;
5. Documento, Registro, Budget, Report e Dashboard riconciliano al centesimo dallo stesso projection object;
6. Xdebug riporta 100% line e branch su ogni classe dell'allowlist obbligatoria e il Gate automatico passa;
7. static, Accounting, Application, Frontend, lint e build passano nei container previsti;
8. review dominio, security/tenancy e surface parity non hanno CRITICAL/HIGH aperti;
9. il primary integration owner aggiorna route/permission/docs condivisi soltanto dopo verifica;
10. nessun test non eseguito viene dichiarato verde.

## Complexity Tracking

Nessuna violazione costituzionale o complessità eccezionale richiesta. Xdebug è una dipendenza di solo test già predisposta dall'ambiente tramite `XDEBUG_MODE` e necessaria al Gate esplicito.
