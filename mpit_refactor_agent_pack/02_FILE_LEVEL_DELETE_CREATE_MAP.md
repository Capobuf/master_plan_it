# File-Level Delete / Create / Rewrite Map

Questo file è vincolante.
Se una voce è indicata come `DELETE`, non mantenerla attiva temporaneamente.
Se una voce è indicata come `REWRITE`, non fare patch cosmetiche: riallinearla davvero al nuovo dominio.

## DELETE — Doctypes legacy

Eliminare completamente queste cartelle dal modello attivo:

```text
master_plan_it/master_plan_it/doctype/mpit_budget/
master_plan_it/master_plan_it/doctype/mpit_budget_line/
master_plan_it/master_plan_it/doctype/mpit_planned_item/
master_plan_it/master_plan_it/doctype/mpit_actual_entry/
master_plan_it/master_plan_it/doctype/mpit_budget_addendum/
```

## DELETE — Hooks / support legacy

Eliminare:

```text
master_plan_it/master_plan_it/budget_refresh_hooks.py
```

Ripulire inoltre:

```text
master_plan_it/master_plan_it/hooks.py
```

Rimuovere:

- scheduler `realign_planned_items_horizon`
- doc_events su `MPIT Planned Item`
- doc_events su `MPIT Budget Addendum`
- doc_events su `MPIT Contract` che servono solo a coverage / budget cleanup
- fixture filters che esportano workflow budget o metadata budget-centrici non più vivi

## DELETE / REWRITE — Workflow fixtures

Ripulire:

```text
master_plan_it/master_plan_it/workflow/mpit_budget_workflow.json
master_plan_it/fixtures/workflow.json
master_plan_it/master_plan_it/hooks.py
```

Azioni:

- eliminare `MPIT Budget Workflow`
- rimuovere il relativo export/filter fixtures
- non creare automaticamente un nuovo workflow standard Frappe per `MPIT Expense` in v1 se basta il semplice campo `workflow_state`
- rivalutare `master_plan_it/master_plan_it/workflow/mpit_project_workflow.json`:
  - mantenerlo solo se ancora coerente col prodotto post-refactor
  - altrimenti eliminarlo

## DELETE — Reports legacy

Eliminare in modo netto:

```text
master_plan_it/master_plan_it/report/mpit_budget_diff/
master_plan_it/master_plan_it/report/mpit_actual_entries/
master_plan_it/master_plan_it/report/mpit_projects_planned_vs_exceptions/
```

## REWRITE — Report da mantenere come concetto ma con nuova semantica

```text
master_plan_it/master_plan_it/report/mpit_overview/
master_plan_it/master_plan_it/report/mpit_monthly_plan/
master_plan_it/master_plan_it/report/mpit_renewals_window/
```

Regole:

- `mpit_overview`: leggere solo il nuovo engine
- `mpit_monthly_plan`: leggere solo il nuovo engine
- `mpit_renewals_window`: verificare dipendenze, pulire naming e lasciare solo logica contratti/rinnovi

## CREATE — Nuovi report minimi

Creare:

```text
master_plan_it/master_plan_it/report/mpit_expenses/
master_plan_it/master_plan_it/report/mpit_project_forecast_vs_actual/
```

Non creare in v1 altri report opzionali se non strettamente necessari.

## DELETE — Dashboard chart sources legacy

Eliminare:

```text
master_plan_it/master_plan_it/dashboard_chart_source/mpit_actual_entries_by_kind/
master_plan_it/master_plan_it/dashboard_chart_source/mpit_actual_entries_by_status/
master_plan_it/master_plan_it/dashboard_chart_source/mpit_budget_totals/
master_plan_it/master_plan_it/dashboard_chart_source/mpit_budgets_by_type/
master_plan_it/master_plan_it/dashboard_chart_source/mpit_cap_vs_actual_by_cost_center/
master_plan_it/master_plan_it/dashboard_chart_source/mpit_monthly_plan_vs_actual/
master_plan_it/master_plan_it/dashboard_chart_source/mpit_planned_items_coverage/
```

Valutare e mantenere solo se ancora sane semanticamente:

```text
master_plan_it/master_plan_it/dashboard_chart_source/mpit_projects_by_status/
master_plan_it/master_plan_it/dashboard_chart_source/mpit_contracts_by_status/
```

Se restano, devono essere chiaramente non-economiche.

## DELETE — Dashboard chart definitions legacy

Eliminare:

```text
master_plan_it/master_plan_it/dashboard_chart/mpit_actual_entries_by_kind/
master_plan_it/master_plan_it/dashboard_chart/mpit_actual_entries_by_status/
master_plan_it/master_plan_it/dashboard_chart/mpit_budget_totals/
master_plan_it/master_plan_it/dashboard_chart/mpit_budgets_by_type/
master_plan_it/master_plan_it/dashboard_chart/mpit_cap_vs_actual_by_cost_center/
master_plan_it/master_plan_it/dashboard_chart/mpit_planned_items_coverage/
master_plan_it/master_plan_it/dashboard_chart/mpit_plan_vs_cap_vs_actual/
master_plan_it/master_plan_it/dashboard_chart/mpit_projects_planned_vs_exceptions/
```

## CREATE — Nuove chart definitions + sources

Set minimo:

```text
master_plan_it/master_plan_it/dashboard_chart_source/mpit_forecast_vs_actual_by_cost_center/
master_plan_it/master_plan_it/dashboard_chart_source/mpit_monthly_forecast_vs_actual/
master_plan_it/master_plan_it/dashboard_chart_source/mpit_plafond_usage_by_cost_center/
```

Con relative definizioni in:

```text
master_plan_it/master_plan_it/dashboard_chart/...
```

## DELETE — Number cards legacy

Eliminare:

```text
master_plan_it/master_plan_it/number_card/actual_entries_verified/
master_plan_it/master_plan_it/number_card/addendums_approved/
master_plan_it/master_plan_it/number_card/budgets_live/
master_plan_it/master_plan_it/number_card/budgets_snapshot/
master_plan_it/master_plan_it/number_card/mpit_total_actual/
master_plan_it/master_plan_it/number_card/mpit_total_addendums/
master_plan_it/master_plan_it/number_card/mpit_total_plan_live/
master_plan_it/master_plan_it/number_card/mpit_total_snapshot/
master_plan_it/master_plan_it/number_card/planned_items_submitted/
```

## KEEP — Number cards ancora sane

Verificare e mantenere se coerenti:

```text
master_plan_it/master_plan_it/number_card/contracts/
master_plan_it/master_plan_it/number_card/cost_centers/
master_plan_it/master_plan_it/number_card/expired_contracts/
master_plan_it/master_plan_it/number_card/projects/
master_plan_it/master_plan_it/number_card/renewals_30d/
master_plan_it/master_plan_it/number_card/renewals_60d/
master_plan_it/master_plan_it/number_card/renewals_90d/
master_plan_it/master_plan_it/number_card/vendors/
```

## CREATE — Nuove number cards

Creare almeno:

```text
master_plan_it/master_plan_it/number_card/mpit_forecast_total/
master_plan_it/master_plan_it/number_card/mpit_actual_total/
master_plan_it/master_plan_it/number_card/mpit_active_plafonds/
master_plan_it/master_plan_it/number_card/mpit_remaining_plafond/
```

## REWRITE — Workspace

Riscrivere completamente:

```text
master_plan_it/master_plan_it/workspace/master_plan_it/master_plan_it.json
master_plan_it/master_plan_it/dashboard/master_plan_it_overview/master_plan_it_overview.json
master_plan_it/master_plan_it/dashboard/master_plan_it_overview/master_plan_it_overview.js
```

Rimuovere collegamenti a:

- Budget
- Budget Addendums
- Planned Items
- Actual Entries
- Budget Diff
- Projects vs Exceptions
- qualunque shortcut/card/report legacy del budget engine

Inserire collegamenti a:

- Expenses
- Plafonds
- Contracts
- Projects
- Overview
- Monthly Plan
- Expenses Report
- Project Forecast vs Actual
- Renewals Window

Rimuovere dal contenuto workspace corrente almeno:

- number cards `MPIT Total Plan Live`, `MPIT Total Snapshot`, `MPIT Total Addendums`, `MPIT Total Actual`
- chart `MPIT Plan vs Cap vs Actual`
- shortcut `Nuovo Budget`
- shortcut `Nuovo Addendum`
- shortcut `Nuova Variance`
- card/link `Budget & Planning`

## DELETE — Print formats legacy

Eliminare se non più usati:

```text
master_plan_it/master_plan_it/print_format/mpit_budget_professional/
```

Se servono nuove stampe, crearle su `MPIT Expense`, non adattare quelle budget.

## REWRITE — Core doctypes ancora vivi

### `MPIT Contract`

File:

```text
master_plan_it/master_plan_it/doctype/mpit_contract/mpit_contract.py
master_plan_it/master_plan_it/doctype/mpit_contract/mpit_contract.json
master_plan_it/master_plan_it/doctype/mpit_contract/mpit_contract.js
```

Da fare:

- rimuovere ogni riferimento a `MPIT Planned Item`
- rimuovere cleanup budget line su `on_trash`
- rimuovere sync coverage
- mantenere naming, VAT, monthly equivalent, renewals
- mantenere solo logica coerente col nuovo engine
- non generare più dati intermedi persistiti

### `MPIT Contract Term`

File:

```text
master_plan_it/master_plan_it/doctype/mpit_contract_term/mpit_contract_term.py
master_plan_it/master_plan_it/doctype/mpit_contract_term/mpit_contract_term.json
master_plan_it/master_plan_it/doctype/mpit_contract_term/mpit_contract_term.js
```

Da fare:

- mantenerlo economicamente attivo
- chiarire con help text e naming il suo ruolo nel forecast del contratto
- evitare semantiche duplicate o logiche legacy di budget

### `MPIT Project`

File:

```text
master_plan_it/master_plan_it/doctype/mpit_project/mpit_project.py
master_plan_it/master_plan_it/doctype/mpit_project/mpit_project.json
master_plan_it/master_plan_it/doctype/mpit_project/mpit_project.js
```

Da fare:

- rimuovere calcoli da `Planned Item` / `Actual Entry`
- rimuovere `has_submitted_planned_items`
- rimuovere o sostituire i KPI legacy (`planned_total_net`, `quoted_total_net`, `expected_total_net`, `utilization_pct`)
- lasciare il progetto come contenitore operativo, non come motore contabile autonomo
- eventuali KPI a form devono essere solo derived/read-only

## CREATE — Nuovi doctypes

Creare:

```text
master_plan_it/master_plan_it/doctype/mpit_expense/
master_plan_it/master_plan_it/doctype/mpit_expense_row/
```

File minimi:

```text
master_plan_it/master_plan_it/doctype/mpit_expense/mpit_expense.json
master_plan_it/master_plan_it/doctype/mpit_expense/mpit_expense.py
master_plan_it/master_plan_it/doctype/mpit_expense/mpit_expense.js
master_plan_it/master_plan_it/doctype/mpit_expense/test_mpit_expense.py

master_plan_it/master_plan_it/doctype/mpit_expense_row/mpit_expense_row.json
master_plan_it/master_plan_it/doctype/mpit_expense_row/mpit_expense_row.py
```

`MPIT Expense Row` può non avere JS separato se non serve.

## CREATE — Motore unico

Creare un modulo unico, semplice:

```text
master_plan_it/master_plan_it/financial_engine.py
```

oppure:

```text
master_plan_it/master_plan_it/services/financial_engine.py
```

Non introdurre più di un modulo business per questo dominio senza necessità reale.

## REWRITE — Test suite

### DELETE / REWRITE

Aggiornare o eliminare:

```text
master_plan_it/tests/acceptance_seed.py
master_plan_it/tests/test_budget_engine_v2.py
master_plan_it/tests/test_budget_engine_v3_acceptance.py
master_plan_it/tests/test_reports.py
master_plan_it/tests/test_smoke.py
```

### KEEP

Tenere se ancora validi:

```text
master_plan_it/tests/test_no_forbidden_metadata_paths.py
master_plan_it/tests/test_translation_sync.py
master_plan_it/tests/test_translations.py
master_plan_it/tests/test_vat_flag.py
```

### CREATE

Nuovi test minimi:

```text
master_plan_it/master_plan_it/doctype/mpit_expense/test_mpit_expense.py
master_plan_it/tests/test_financial_engine.py
master_plan_it/tests/test_expense_reports.py
master_plan_it/tests/test_workspace_smoke.py
```

## REWRITE — Translations

Aggiornare:

```text
master_plan_it/master_plan_it/translations/it.csv
```

Rimuovere tutte le stringhe legacy non più usate.
Aggiungere le nuove stringhe di `Expense`, `Plafond`, report, workspace e help text.

## DOCS — Active docs da aggiornare o archiviare

I seguenti file non devono restare come documentazione attiva del prodotto se descrivono ancora il vecchio budget engine:

```text
docs/mpit_budget_engine_v3_decisions (3).md
docs/questions-mpit_budget_engine_v3_decisions.md
docs/reference/04-reports-dashboards.md
docs/reference/08-data-sources-for-charts.md
docs/states_and_workflows_comprehensive_analysis.md
docs/ux/mpit_budget_engine_v2_ui.md
docs/workflow_desired_state.md
docs/workflow_refactoring_questions.md
docs/reference/10-money-vat-annualization.md
```

Azione:

- aggiornare se ancora utili
- altrimenti spostare in `docs/_archive/legacy-budget-engine/`

Non lasciare documentazione attiva che descrive un prodotto non più esistente.

## Verifica finale obbligatoria

Prima di chiudere la PR, eseguire almeno questi check:

```bash
rg -n "MPIT Budget|MPIT Budget Line|MPIT Planned Item|MPIT Actual Entry|MPIT Budget Addendum" master_plan_it
rg -n "total_active_net|Snapshot|Addendum|Planned Item|Actual Entry|Budget Diff|Planned vs Exceptions|coverage" master_plan_it
rg -n "planned_total_net|quoted_total_net|expected_total_net|utilization_pct|has_submitted_planned_items" master_plan_it
```

I risultati devono essere puliti o limitati a:

- archivio documentale esplicito
- note storiche non attive

Non devono restare riferimenti nel codice attivo, nei test attivi, nel workspace attivo o nella documentazione attiva del prodotto.
