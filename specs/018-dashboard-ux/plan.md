# Implementation Plan: Dashboard UX

**Branch**: `laravel-replatform` | **Date**: 2026-08-10 | **Spec**: `specs/018-dashboard-ux/spec.md`

## Summary

Estendere il dataset economico annuale corrente con il raggruppamento per Project e arricchire i dati ancillary della Dashboard con conteggi e ultime Spese. Comporre quindi la Panoramica con primitive TailAdmin esistenti, un donut riusabile minimale e una timeline contrattuale, preservando selezione globale dell'anno, stati della pagina, RBAC e calcoli monetari server-side.

Il pass UX successivo conserva integralmente backend e contract: rende fluid il wrapper globale, riduce e bilancia le altezze della sezione grafici, aggiunge il totale Centro di Costo usando il summary autorevole e rifinisce KPI, tabella e timeline sui viewport mobile.

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 13.22; TypeScript / React 19

**Primary Dependencies**: Laravel API, BCMath/Money layer esistente, React Router, TailAdmin React Free 2.3.0, ApexCharts già installato

**Storage**: MySQL 8.4 esistente; nessuna migration

**Testing**: PHPUnit/Laravel test runner; Vitest; ESLint; TypeScript/Vite build; dark token checker

**Target Platform**: applicazione web responsive, Laravel API privata e frontend same-origin

**Project Type**: web application backend + frontend

**Performance Goals**: una singola richiesta Dashboard; query aggregate/relazionali limitate al Tenant e anno; massimo otto Spese recenti e cinque eventi visibili nella timeline

**Constraints**: nessun nuovo endpoint, package, permission, entità o workflow; stringhe decimali e base ufficiale server-side; nessun overflow pagina; light/dark mode coerenti; il pass UX non modifica backend o contract

**Scale/Scope**: una pagina Dashboard, quattro KPI, quattro grafici/distribuzioni, una tabella recente e una timeline; modifiche focalizzate ai file elencati nella feature

## Constitution Check

- **PASS — Slice verticale**: il risultato attraversa query, motore economico, Resource/API, tipi client e UI.
- **PASS — Autorità backend**: `by_project`, KPI e importi recenti sono prodotti dal backend con stringhe decimali.
- **PASS — Dataset unico**: `EconomicEngine` estende la stessa riconciliazione usata da `byCostCenter`; nessun secondo motore.
- **PASS — Tenant/RBAC**: endpoint, middleware, Tenant context e ability esistenti restano invariati; tutte le query restano Tenant/year scoped e ignorano record eliminati.
- **PASS — Architettura minima**: nessun nuovo layer, DTO, migration o dipendenza; soltanto due piccoli componenti UI dove manca un pattern equivalente.
- **PASS — TailAdmin**: riuso di `EcommerceMetrics`, `StatisticsChart`, `ComponentCard`, `Badge`, `Table`, icone, token e classi `dark:`.
- **PASS — Test proporzionati**: assertion nei test HTTP e del motore esistenti più un singolo test frontend focalizzato se l'infrastruttura lo supporta.

Il gate resta PASS dopo il design: il contract aggiunge soltanto campi al payload Dashboard e non modifica semantica, persistenza o autorizzazione.

## Design

### Backend data flow

```text
EconomicDatasetQuery
    ↓ project title sulla EconomicLine
EconomicEngine
    ↓ byProject con la stessa riconciliazione Plafond di byCostCenter
ReportingDatasetResource
    ↓ by_project

TenantDashboardQuery
    ├── expenseCounts total/open/closed
    ├── recentExpenses arricchito e limitato a 8
    └── upcomingContractEvents invariato
```

`DashboardController` cambia soltanto per fornire la shape ancillary vuota completa quando non esiste un Planning Year, se necessario.

### Frontend composition

```text
api/dashboard.ts
    ↓ tipi del contract
DashboardView
    ├── EcommerceMetrics × 4
    ├── BreakdownDonutChart — Centro di Costo
    ├── StatisticsChart — area mensile
    ├── StatisticsChart horizontal — Project (top 5 visuale)
    ├── BreakdownDonutChart — Stato Spese
    ├── RecentOrders/Table — Ultime Spese
    └── RenewalTimeline — Rinnovi e Scadenze
```

`AppLayout` usa un wrapper globale `w-full` con padding 3/4/6 e senza max-width. Desktop conserva la griglia grafici 3/6/3 con elementi allineati in alto: Centro di Costo content-driven, mensile circa 310px e colonna Project/Stato complessivamente simile. Mobile usa gap da 12px, KPI 2×2 e chart height ridotte. Il Project chart deriva soltanto la propria altezza visuale dal numero di barre; gli importi restano le stringhe server-side. La timeline usa date box, linea e marker TailAdmin e limita la vista a cinque eventi.

## Project Structure

### Documentation

```text
specs/018-dashboard-ux/
├── spec.md
├── plan.md
├── tasks.md
└── contracts/
    └── dashboard-api.md
```

### Source Code

```text
app/Domain/Economics/Data/EconomicLine.php
app/Domain/Reporting/Queries/EconomicDatasetQuery.php
app/Domain/Economics/Services/EconomicEngine.php
app/Domain/Reporting/Queries/TenantDashboardQuery.php
app/Http/Controllers/Api/V1/DashboardController.php
app/Http/Resources/Api/V1/ReportingDatasetResource.php

frontend/src/api/dashboard.ts
frontend/src/layout/AppLayout.tsx
frontend/src/components/dashboard/DashboardView.tsx
frontend/src/components/dashboard/BreakdownDonutChart.tsx
frontend/src/components/dashboard/RenewalTimeline.tsx
frontend/src/components/ecommerce/EcommerceMetrics.tsx
frontend/src/components/ecommerce/StatisticsChart.tsx
frontend/src/components/ecommerce/RecentOrders.tsx
frontend/src/index.css

tests/Feature/Api/Reporting/ReportingApiHttpTest.php
tests/Accounting/Unit/EconomicEngineTest.php
frontend/src/components/dashboard/DashboardView.test.tsx
```

**Structure Decision**: mantenere l'architettura Laravel + React corrente e collocare i soli componenti specifici nella cartella Dashboard; nessun nuovo layer condiviso.

## Verification

1. Test HTTP Dashboard esistente aggiornato per contract, conteggi e recent expense fields.
2. Test `EconomicEngine` esistente aggiornato per aggregazione Project e riconciliazione overrun Plafond.
3. Un test Dashboard frontend focalizzato, soltanto se compatibile con l'infrastruttura Vitest corrente.
4. `composer test:static`.
5. `npm run test:unit -- Dashboard`, `npm run test:dark-tokens`, `npm run lint`, `npm run build`.
6. Verifica browser a circa 1440px light/dark e circa 390px light/dark, più smoke visuale su Spese e Budget per il wrapper fluid.
