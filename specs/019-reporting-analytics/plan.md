# Implementation Plan: Reporting Analytics

**Branch**: `laravel-replatform` | **Date**: 2026-08-10 | **Spec**: `specs/019-reporting-analytics/spec.md`

## Summary

Estendere il Report economico annuale esistente affinché carichi sempre il dataset completo del Tenant/Planning Year, riconcili il Plafond prima dei filtri Report e produca dallo stesso subset filtrato summary, cinque raggruppamenti, visualizzazioni limitate non paginate e dettaglio paginato. La pagina React usa filtri discreti automatici, sei KPI, grafici ApexCharts/TailAdmin e tabella responsive, senza pannello `Attenzioni` e senza nuovi endpoint, permission, dipendenze o formule economiche client-side.

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 13.22; TypeScript / React 19

**Primary Dependencies**: Laravel API, BCMath e Money layer esistenti, React, TailAdmin React Free 2.3.0, ApexCharts già installato

**Storage**: MySQL 8.4 esistente; nessuna migration o nuova tabella

**Testing**: PHPUnit/Laravel test runner; Vitest; dark token checker; ESLint; TypeScript/Vite build

**Target Platform**: applicazione web responsive con Laravel API privata e frontend same-origin

**Project Type**: web application backend + frontend

**Performance Goals**: una richiesta Report per modifica di un filtro discreto valido o cambio pagina; nessuna richiesta storica prima di valorizzare il Cutoff; visualizzazione limitata a 10 gruppi e donut a 6 segmenti; nessuna dipendenza dalla dimensione della pagina per i grafici

**Constraints**: stringhe decimali e BCMath per valori autorevoli; tenant isolation fail-closed; riconciliazione Plafond prima dei filtri; paginazione server-side; nessuna nuova dependency, migration, permission o formula economica React

**Scale/Scope**: un endpoint esistente, un Planning Year, cinque dimensioni di grouping, tre filtri lookup opzionali, uno stato, due viste, sei KPI, quattro visualizzazioni e una tabella; il delta post-review è frontend-only

## Constitution Check

- **PASS — Slice verticale**: il risultato attraversa validazione API, query economica, contract TypeScript, lookup condizionali e UI.
- **PASS — Autorità backend**: Laravel filtra, riconcilia, aggrega, calcola summary, ranking, `Altri`, conteggi e paginazione; React converte stringhe in number soltanto per ApexCharts.
- **PASS — Dataset unico**: `AnnualEconomicReportQuery` riusa `AnnualBudgetQuery`/`HistoricalAnnualBudgetQuery` e la riconciliazione corrente; non introduce un secondo motore.
- **PASS — Tenant/RBAC**: endpoint e `report.view` restano invariati; Cost Center, Project e Vendor sono validati tenant-scoped e i lookup UI dipendono dalle abilities esistenti.
- **PASS — Architettura minima**: nessun nuovo layer, model, migration o endpoint; un solo piccolo chart report-specifico è ammesso per evitare branch complessi nel componente Dashboard.
- **PASS — TailAdmin**: riuso di `EcommerceMetrics`, `BreakdownDonutChart`, `StatisticsChart`, `ComponentCard`, `Alert`, `Badge`, `Button`, `Select` e `Table`.
- **PASS — Test proporzionati**: estensione del test HTTP Reporting esistente e al massimo un test React focalizzato sul comportamento.

Il gate resta PASS dopo il design: il contract aggiunge filtri e output derivati a un endpoint read-only esistente, senza mutazioni o persistenza.

## Design

### Backend data flow

```text
EconomicReportController
    ├── valida Planning Year, Cost Center, Project, Vendor e state nel Tenant
    └── EconomicReportFilterData
            ↓
AnnualEconomicReportQuery
    ├── current: AnnualBudgetQuery(actor, tenant, year, null)
    ├── historical: HistoricalAnnualBudgetQuery(actor, tenant, year, cutoff, null)
    ├── riconcilia righe Plafond sul dataset annuale completo
    ├── applica cost_center_id/project_id/vendor_id/state alle righe riconciliate
    ├── calcola filtered summary con BCMath
    ├── raggruppa nelle cinque dimensioni e ordina per label
    ├── deriva visualization dai gruppi completi prima della paginazione
    └── pagina soltanto data
```

`EconomicReportFilterData` contiene lo scope validato della feature, evitando una firma query con molti argomenti opzionali. `global_plafond_overrun` viene copiato dal summary annuale completo e resta esterno al summary filtrato.

I gruppi principali sono ordinati server-side per la massima magnitudine assoluta fra Proposto, Approvato e Actual, poi case-insensitive per label e infine per key. La ripartizione ordina per Proposto decrescente, poi label/key; mantiene cinque segmenti e aggrega tutti i rimanenti in `Altri` con BCMath. La tabella conserva l'ordinamento alfabetico autorevole corrente.

### Frontend composition

```text
ReportsHome
    └── ReportsView
        ├── filter bar (selezione discreta → query automatica, pagina 1)
        ├── EcommerceMetrics × 6
        ├── ReportEconomicChart — grouped Proposto/Approvato/Actual
        ├── BreakdownDonutChart — Ripartizione del Proposto
        ├── StatisticsChart — Scostamento orizzontale
        ├── BreakdownDonutChart — Stato Spese
        └── Table — dettaglio server-paginated
```

`ReportsHome` passa separatamente `cost-center.view`, `project.view` e `vendor.view`. Ogni lookup viene caricato e gestito indipendentemente, così un errore locale non blocca gli altri filtri o la richiesta Report.

`ReportsView` mantiene un solo stato dei filtri UI. Ogni Select discreto aggiorna il valore interessato e riporta `page` a 1; la query derivata avvia automaticamente il refresh. La Vista Storica mostra subito il Cutoff ma non invia richieste finché è vuoto; un Cutoff valido aggiunge `as_of`, mentre il ritorno alla Vista Corrente lo rimuove e ricarica la pagina 1. Durante i refresh successivi la response valida corrente resta visibile con un feedback discreto vicino ai filtri. La paginazione modifica esclusivamente `page` senza cambiare gli altri filtri.

Il layout usa KPI 2×3 sui viewport stretti e 6 colonne desktop, due griglie analitiche 2/3 + 1/3 da `xl`, grafici compatti e colonne tabella responsive. Non sono richieste modifiche globali a `index.css`: gli override ApexCharts esistenti coprono light/dark.

## API Contract

Il contratto implementabile è `specs/019-reporting-analytics/contracts/reporting-api.md`. Non viene creato un OpenAPI globale parziale.

## Project Structure

### Documentation

```text
specs/019-reporting-analytics/
├── spec.md
├── plan.md
├── tasks.md
└── contracts/
    └── reporting-api.md
```

### Source Code

```text
app/Domain/Reporting/Data/EconomicReportFilterData.php
app/Domain/Reporting/Queries/AnnualEconomicReportQuery.php
app/Http/Controllers/Api/V1/EconomicReportController.php
routes/api/v1/reporting.php
tests/Feature/Api/Reporting/ReportingApiHttpTest.php

frontend/src/api/reports.ts
frontend/src/pages/Reports/Home.tsx
frontend/src/components/reports/ReportsView.tsx
frontend/src/components/reports/ReportEconomicChart.tsx
frontend/src/components/ecommerce/StatisticsChart.tsx
frontend/src/components/dashboard/BreakdownDonutChart.tsx
frontend/src/components/reports/ReportsView.test.tsx
```

**Structure Decision**: mantenere la query e l'endpoint Reporting esistenti; creare soltanto un componente report-specifico per il grouped chart multi-serie ed estendere `StatisticsChart` in modo retrocompatibile con colori distribuiti opzionali per lo Scostamento.

## Verification

1. `php artisan test tests/Feature/Api/Reporting/ReportingApiHttpTest.php`.
2. `composer test:static`.
3. Test Vitest focalizzato `ReportsView.test.tsx` per filtri automatici, storico/Cutoff e assenza del pannello `Attenzioni`.
4. Da `frontend`: `npm run test:dark-tokens`, `npm run lint`, `npm run build`.
5. Verifica browser a circa 1440px light/dark e 390px, controllando filtri automatici, storico/Cutoff, refresh senza scomparsa del Report, assenza di `Attenzioni`, KPI, grafici, valori negativi e tabella.
