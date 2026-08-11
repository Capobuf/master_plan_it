# Tasks: Expense Workspace UX

**Input**: `specs/020-expense-workspace-ux/spec.md`, `plan.md` e `contracts/expenses-api.md`

**Tests**: Estendere i test Expense esistenti e aggiungere soltanto test React comportamentali focalizzati; nessun test pixel/className/SVG.

## Phase 1: Contract e fondazione

- [X] T020-001 Formalizzare delta, decisioni Q1/Q2/Q3, piano e contract in `specs/020-expense-workspace-ux/spec.md`, `specs/020-expense-workspace-ux/plan.md` e `specs/020-expense-workspace-ux/contracts/expenses-api.md`

## Phase 2: User Story 1 — Trovare e confrontare Spese rapidamente

**Goal**: Registro year-scoped con ricerca/filtri automatici, parent projection completa e totals sull'intero dataset filtrato.

**Independent Test**: Con più Spese e pagine, ogni filtro restringe data e totals allo stesso subset; un anno/lookup estraneo fallisce closed e il dettaglio fuori anno non è visibile.

- [X] T020-002 [US1] Estendere DTO/query/controller/resource e test Register con year richiesto, q, Cost Center, Project, Contract, Vendor via righe correnti, Stato, vendor summary, pagine 25/50/100 e filtered totals in `app/Domain/Expenses/Data/ExpenseRegisterFilterData.php`, `app/Domain/Expenses/Data/ExpenseRegisterRow.php`, `app/Domain/Expenses/Queries/ExpenseRegisterQuery.php`, `app/Http/Controllers/Api/V1/ExpenseController.php`, `app/Http/Resources/Api/V1/ExpenseRegisterResource.php`, `tests/Feature/Expenses/ExpenseRegisterTest.php` e `tests/Feature/Api/Expenses/ExpenseApiHttpTest.php`
- [X] T020-003 [US1] Rendere il dettaglio year-scoped e aggiungere contract title/vendor name con test fail-closed in `app/Domain/Expenses/Data/ExpenseDetail.php`, `app/Domain/Expenses/Queries/ExpenseDetailQuery.php`, `app/Http/Resources/Api/V1/ExpenseDetailResource.php`, `app/Http/Resources/Api/V1/ExpenseRowResource.php`, `app/Http/Controllers/Api/V1/ExpenseController.php`, `tests/Feature/Expenses/ExpenseRegisterTest.php` e `tests/Feature/Api/Expenses/ExpenseApiHttpTest.php`
- [X] T020-004 [US1] Rimuovere l'anno locale/legacy URL, aggiungere lookup e filter bar automatica con debounce 300 ms e page size 25/50/100, aggiornando tipi e test in `frontend/src/api/expenses.ts`, `frontend/src/pages/Expenses/ExpenseRegister.tsx`, `frontend/src/components/expenses/ExpenseFilters.tsx` e `frontend/src/pages/Expenses/ExpenseRegister.test.tsx`

## Phase 3: User Story 2 — Lavorare su più Spese dal Registro

**Goal**: Selezione page-scoped, colonne sincronizzate, expander lazy, menu distinto e Close/Move/Delete bulk atomici.

**Independent Test**: Selezionando record della pagina compare la toolbar; cambio filtro/pagina azzera la selezione; colonne persistono tra sessioni; expander usa il detail esistente; ogni bulk mutation è all-or-nothing.

- [X] T020-005 [US2] Implementare Action/endpoint bulk Close, Move e Delete con item lock versions, year scope, authorization per record, atomicità, generation choice e test rollback in `app/Domain/Expenses/Actions/BulkExpenseAction.php`, `app/Http/Controllers/Api/V1/ExpenseController.php`, `routes/api/v1/expenses.php`, `tests/Feature/Api/Expenses/ExpenseLifecycleApiTest.php` e `tests/Feature/Expenses/ExpenseActionRollbackTest.php`
- [X] T020-006 [US2] Implementare preferenze colonne server-side user/Tenant-scoped con migration, model, API, normalizzazione e test in `database/migrations/2026_08_11_000001_create_expense_register_preferences_table.php`, `app/Models/ExpenseRegisterPreference.php`, `app/Http/Controllers/Api/V1/ExpenseController.php`, `routes/api/v1/expenses.php` e `tests/Feature/Api/Expenses/ExpenseApiHttpTest.php`
- [X] T020-007 [US2] Implementare tabella densa selezionabile, toolbar/modal bulk, expander lazy cached, menu azioni e configurazione colonne visibilità/ordine/reset in `frontend/src/api/expenses.ts`, `frontend/src/pages/Expenses/ExpenseRegister.tsx`, `frontend/src/components/expenses/ExpenseRegisterTable.tsx`, `frontend/src/components/expenses/ExpenseColumnSettings.tsx`, `frontend/src/components/expenses/ExpenseBulkActions.tsx` e `frontend/src/pages/Expenses/ExpenseRegister.test.tsx`

## Phase 4: User Story 3 — Comprendere e operare su una singola Spesa

**Goal**: Object page compatta, azioni iconiche accessibili, classificazione reale, sintesi economica e righe dominanti.

**Independent Test**: Il dettaglio mostra Contract e Vendor reali, nasconde una Spesa fuori anno e rende ogni azione autorizzata raggiungibile per accessible name con hint hover/focus.

- [X] T020-008 [US3] Ridisegnare object header, IconButton tooltip accessibile, classificazione, sintesi, righe dense, note e revisioni subordinate con test in `frontend/src/pages/Expenses/ExpenseDetail.tsx`, `frontend/src/components/common/IconButton.tsx`, `frontend/src/components/expenses/ExpenseRowsTable.tsx` e `frontend/src/pages/Expenses/ExpenseDetail.test.tsx`

## Phase 5: User Story 4 — Inserire e modificare rapidamente una Spesa multi-riga

**Goal**: Editor ordinario senza anno modificabile e griglia ERP multi-riga responsive con dettagli secondari espandibili.

**Independent Test**: Tre righe sono simultaneamente visibili desktop; aggiunta/rimozione/riordino e dettagli funzionano; mobile usa card; solo il credito conserva Anno destinazione futuro.

- [X] T020-009 [US4] Separare Dati generali/Classificazione, applicare anno globale/read-only/credito e trasformare le righe in griglia ERP desktop + card mobile preservando DnD, controlli keyboard e invarianti in `frontend/src/components/expenses/ExpenseEditor.tsx`, `frontend/src/components/expenses/ExpenseEditorRows.tsx`, `frontend/src/components/expenses/expenseEditorTypes.ts`, `frontend/src/components/expenses/ExpenseEditor.test.tsx` e `frontend/src/components/expenses/ExpenseEditorRows.test.tsx`

## Phase 6: Verifica, responsive e documentazione

- [X] T020-010 Eseguire test Expense backend focalizzati, `composer test:static`, test frontend focalizzati, `npm run test:dark-tokens`, lint/build, verifica browser 1440px dark/light e 390px; correggere un solo pass di polish e aggiornare `specs/020-expense-workspace-ux/contracts/expenses-api.md`, `docs/STATUS.md` e `specs/README.md`

## Phase 7: Post-review UI polish

- [X] T020-011 Compattare filter bar e spostare page-size nella paginazione in `frontend/src/components/expenses/ExpenseFilters.tsx`, `frontend/src/components/expenses/ExpensePagination.tsx` e `frontend/src/pages/Expenses/ExpenseRegister.tsx`
- [X] T020-012 Rifinire toolbar tabella / controllo Colonne e rimuovere Base Ufficiale in `frontend/src/components/expenses/ExpenseRegisterTable.tsx`, `frontend/src/components/expenses/ExpenseColumnSettings.tsx`, `frontend/src/components/expenses/ExpenseBulkActions.tsx` e `frontend/src/components/expenses/ExpenseTotals.tsx`
- [X] T020-013 Aggiornare i test frontend coinvolti ed eseguire verifica responsive light/dark, test focalizzati, dark tokens, lint e build

## Phase 8: Post-review Expense row grid

- [X] T020-014 Preservare `ExpenseRow.description` in dominio, API, schema, contract generation e read-only, documentando la sua collocazione secondaria nell'editor in `specs/020-expense-workspace-ux/spec.md`, `specs/020-expense-workspace-ux/plan.md` e `specs/020-expense-workspace-ux/contracts/expenses-api.md`
- [X] T020-015 Ricomporre `ExpenseEditorRows` come vera griglia ERP responsive con intestazione unica, campi frequenti inline, Descrizione e opzioni secondarie nei Dettagli, reorder accessibile e Add Row nell'header in `frontend/src/components/common/ComponentCard.tsx`, `frontend/src/components/expenses/ExpenseEditor.tsx` e `frontend/src/components/expenses/ExpenseEditorRows.tsx`
- [X] T020-016 Allineare test frontend e verifica visuale desktop/mobile preservando la tabella read-only e il payload `description` in `frontend/src/components/expenses/ExpenseEditor.test.tsx`, `frontend/src/components/expenses/ExpenseEditorRows.test.tsx`, `frontend/src/components/expenses/ExpenseRowsTable.tsx` e `specs/020-expense-workspace-ux/contracts/expenses-api.md`

## Dependencies & Execution Order

- T020-001 precede ogni implementazione.
- T020-002 precede T020-003 perché entrambi modificano controller e test Register; completarli prima di T020-004 e T020-008.
- T020-005 e T020-006 dipendono dal contract T020-001 e precedono T020-007.
- T020-004 precede T020-007 perché stabilisce stato filtri, year scope e request identity.
- T020-003 precede T020-008 e T020-009 per il contract detail/editor year-scoped.
- T020-007 e T020-008 possono procedere in parallelo dopo i rispettivi backend.
- T020-009 riusa `IconButton`/pattern definiti senza dipendere dalla toolbar bulk.
- T020-010 segue tutti i task di implementazione e test.
- T020-011 e T020-012 sono correzioni post-review frontend-only e possono essere implementate in sequenza; T020-013 segue entrambe.
- T020-014 registra la decisione prodotto aggiornata e precede T020-015; T020-016 segue T020-015 senza riaprire i task precedenti.

## Implementation Strategy

Procedere in un unico giro verticale: backend Register/detail e test, backend bulk/preferenze e test, tipi/client e filter bar, tabella workspace, object detail, editor ERP, poi gate e verifica visuale. L'MVP indipendente è US1; Q1/Q2 rendono US2 parte obbligatoria della Definition of Done. Nessun task introduce allegati, data grid, calcoli money React o refactor fuori area Spese.
