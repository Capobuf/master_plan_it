# Tasks: Dashboard UX

**Input**: `specs/018-dashboard-ux/spec.md`, `plan.md`, `contracts/dashboard-api.md`

## User Story 1 — Situazione economica immediata (P1)

**Independent Test**: La Dashboard dell'anno selezionato mostra quattro KPI e quattro distribuzioni riconciliabili con il dataset server-side, inclusi Project senza associazione e overrun Plafond.

- [X] T018-001 [US1] Aggiornare il contract in `specs/018-dashboard-ux/contracts/dashboard-api.md` e i tipi in `frontend/src/api/dashboard.ts` con `by_project`, `expenseCounts` e recent expenses arricchite.
- [X] T018-002 [US1] Estendere l'aggregazione economica Project senza doppio conteggio Plafond in `app/Domain/Economics/Data/EconomicLine.php`, `app/Domain/Reporting/Queries/EconomicDatasetQuery.php`, `app/Domain/Economics/Services/EconomicEngine.php` e `app/Http/Resources/Api/V1/ReportingDatasetResource.php`.
- [X] T018-003 [US1] Aggiungere conteggi `open`/`closed`/`total` e recent expenses arricchite in `app/Domain/Reporting/Queries/TenantDashboardQuery.php`, completando la shape vuota in `app/Http/Controllers/Api/V1/DashboardController.php` se necessaria.
- [X] T018-004 [US1] Riorganizzare il layout target e i quattro KPI in `frontend/src/components/dashboard/DashboardView.tsx` riusando `frontend/src/components/ecommerce/EcommerceMetrics.tsx`.
- [X] T018-005 [US1] Implementare il donut riusabile in `frontend/src/components/dashboard/BreakdownDonutChart.tsx` ed estendere `frontend/src/components/ecommerce/StatisticsChart.tsx` con barre orizzontali, tooltip/assi e dark mode coerenti.

## User Story 2 — Operatività recente e prossime scadenze (P2)

**Independent Test**: La Dashboard mostra fino a otto Spese recenti con campi economici reali e una timeline dei primi cinque eventi contrattuali, tutti navigabili verso le pagine esistenti, con link ai Contratti quando esistono altri eventi.

- [X] T018-006 [P] [US2] Implementare `Rinnovi e Scadenze` con primitive TailAdmin in `frontend/src/components/dashboard/RenewalTimeline.tsx` e integrarlo in `frontend/src/components/dashboard/DashboardView.tsx`.
- [X] T018-007 [US2] Estendere la tabella compatta `Ultime Spese` con `Table` e `Badge` in `frontend/src/components/ecommerce/RecentOrders.tsx` e integrarla responsive in `frontend/src/components/dashboard/DashboardView.tsx`.

## Verification

- [X] T018-008 Aggiornare soltanto le assertion mirate in `tests/Feature/Api/Reporting/ReportingApiHttpTest.php` e `tests/Accounting/Unit/EconomicEngineTest.php`, aggiungere `frontend/src/components/dashboard/DashboardView.test.tsx` solo se supportato dall'infrastruttura, quindi eseguire static check, test frontend, dark tokens, lint, build e verifica browser desktop light/dark + mobile.

## UX Polish

- [X] T018-UX01 Rendere fluid il wrapper globale e il padding responsive in `frontend/src/layout/AppLayout.tsx`.
- [X] T018-UX02 Bilanciare la griglia grafici 3/6/3 e rimuovere stretching/altezze e gap eccessivi in `frontend/src/components/dashboard/DashboardView.tsx`.
- [X] T018-UX03 Rendere compatta la card Centro di Costo e aggiungere il Totale autorevole in `frontend/src/components/dashboard/BreakdownDonutChart.tsx` e `frontend/src/components/dashboard/DashboardView.tsx`.
- [X] T018-UX04 Applicare KPI 2×2 e chart height responsive/Project data-driven in `frontend/src/components/ecommerce/EcommerceMetrics.tsx`, `frontend/src/components/ecommerce/StatisticsChart.tsx` e `frontend/src/components/dashboard/DashboardView.tsx`.
- [X] T018-UX05 Ridisegnare la timeline con date box, linea, marker, massimo cinque eventi e link Contratti in `frontend/src/components/dashboard/RenewalTimeline.tsx`.
- [X] T018-UX06 Compattare `Ultime Spese` mobile mostrando titolo/data, Actual e Stato in `frontend/src/components/ecommerce/RecentOrders.tsx`.
- [X] T018-UX07 Eseguire test Dashboard focalizzato, dark tokens, lint, build e verifica visuale 1440px/390px light-dark più smoke Spese/Budget.

## Dependencies

- T018-001 definisce il contract usato dai task successivi.
- T018-002 e T018-003 alimentano T018-004/T018-005/T018-007.
- T018-006 può procedere in parallelo dopo T018-001 perché usa eventi già esistenti.
- T018-008 chiude entrambe le user story dopo T018-002–T018-007.

## Implementation Strategy

Implementare l'intera slice in ordine contract → backend → layout/chart → timeline/tabella → verifica, mantenendo gli otto task come unità coarse-grained e senza aggiungere infrastruttura.
