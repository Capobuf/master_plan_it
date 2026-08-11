# Tasks: Reporting Analytics

**Input**: `specs/019-reporting-analytics/spec.md`, `plan.md` e `contracts/reporting-api.md`

**Tests**: Aggiornare il test HTTP Reporting esistente e creare al massimo un test React focalizzato.

**Post-review**: T019-001..009 restano chiusi come traccia dell'implementazione approvata iniziale; per il comportamento UI corrente, i task T019-010..012 sostituiscono i riferimenti storici a submit manuale e pannello `Attenzioni` senza riaprire o riscrivere i task completati.

## Phase 1: Contract e fondazione

- [X] T019-001 Formalizzare spec, piano e contratto API del Report generale in `specs/019-reporting-analytics/spec.md`, `specs/019-reporting-analytics/plan.md` e `specs/019-reporting-analytics/contracts/reporting-api.md`
- [X] T019-002 [US1] Estendere i test HTTP fail-first per filtri Project/Vendor/Stato, tenant fail-closed, summary filtrato, visualizzazione non paginata e Plafond in `tests/Feature/Api/Reporting/ReportingApiHttpTest.php`

## Phase 2: User Story 1 — Filtrare il Report

**Goal**: Applicare esplicitamente filtri tenant-safe al dataset annuale già riconciliato.

**Independent Test**: Ogni filtro discreto valido aggiorna automaticamente tutti gli output e riporta la pagina a 1; reset ripristina il default con un solo aggiornamento.

- [X] T019-003 [US1] Estendere DTO, controller e query con Project, Vendor e Stato tenant-safe in `app/Domain/Reporting/Data/EconomicReportFilterData.php`, `app/Http/Controllers/Api/V1/EconomicReportController.php` e `app/Domain/Reporting/Queries/AnnualEconomicReportQuery.php`
- [X] T019-004 [US1] Aggiornare i tipi TypeScript, i lookup condizionali e la filter bar draft/apply/reset in `frontend/src/api/reports.ts`, `frontend/src/pages/Reports/Home.tsx` e `frontend/src/components/reports/ReportsView.tsx`

## Phase 3: User Story 2 — Panoramica analitica

**Goal**: Mostrare summary e visualizzazioni autorevoli dell'intero subset filtrato.

**Independent Test**: KPI, top 10, ripartizione e stati si riconciliano con lo stesso subset, non cambiano navigando la tabella e non richiedono un pannello separato `Attenzioni`.

- [X] T019-005 [US2] Calcolare dopo la riconciliazione filtered summary, ranking top 10, ripartizione top 5 + Altri e conteggi Stato in `app/Domain/Reporting/Queries/AnnualEconomicReportQuery.php`
- [X] T019-006 [US2] Implementare sei KPI e i grafici economici riusando TailAdmin/ApexCharts in `frontend/src/components/reports/ReportsView.tsx` e `frontend/src/components/reports/ReportEconomicChart.tsx`

## Phase 4: User Story 3 — Dettaglio

**Goal**: Conservare un dettaglio professionale e paginato, utilizzabile su desktop e mobile.

**Independent Test**: Tutte le colonne sono disponibili desktop; Gruppo, Actual, Scostamento e Utilizzo restano leggibili a circa 390px.

- [X] T019-007 [US3] Rifinire titolo, colonne, paginazione, responsive e dark mode della tabella in `frontend/src/components/reports/ReportsView.tsx`

## Phase 5: Verifica e documentazione

- [X] T019-008 [P] Aggiungere il singolo test comportamentale React per submit filtri, cutoff storico e sezioni Report in `frontend/src/components/reports/ReportsView.test.tsx`
- [X] T019-009 Eseguire test HTTP, static analysis, test React focalizzato, dark tokens, lint, build e verifica browser 1440px/390px light/dark; aggiornare lo stato completato in `docs/STATUS.md`

## Post-review corrections

- [X] T019-010 Aggiornare spec, plan e tasks per filtri automatici e rimozione `Attenzioni` in `specs/019-reporting-analytics/spec.md`, `specs/019-reporting-analytics/plan.md` e `specs/019-reporting-analytics/tasks.md`
- [X] T019-011 Semplificare `frontend/src/components/reports/ReportsView.tsx` con filtro automatico, gestione Storica/Cutoff, mantenimento della response durante il refresh e rimozione `Attenzioni`
- [X] T019-012 Aggiornare `frontend/src/components/reports/ReportsView.test.tsx` e verificare frontend light/dark/mobile

## Dependencies & Execution Order

- T019-001 precede l'implementazione.
- T019-002 precede T019-003 e T019-005.
- T019-003 precede T019-004 e T019-005.
- T019-005 precede T019-006.
- T019-004 e T019-005 precedono T019-007 e T019-008.
- T019-009 segue tutti i task di implementazione e test.
- T019-010 precede T019-011 e T019-012.
- T019-011 precede T019-012.

## Implementation Strategy

Procedere in un unico giro verticale: contract e test backend, filtro/query, contract client e filter bar, visualizzazioni, dettaglio responsive, test frontend e gate finali. Nessun task introduce nuova persistenza, dipendenza o capability fuori scope.
