# Reporting Architecture

## Why The Old Reports Were Removed

The old reporting layer mixed executive summary, detail lines, planning views,
and renewal tracking in broad reports. The replacement uses focused native
Frappe Script Reports so each report answers one operational question and stays
printable.

## New Reports

- `MPIT Economic Position`: current and expected year-end economic position.
- `MPIT Year End Forecast`: period-based actual and forecast landing.
- `MPIT Budget Variance`: budget, actual, forecast, and variance control.
- `MPIT What If Scenario`: read-only inclusion/exclusion scenario.
- `MPIT Economic Detail`: audit/drill-down rows.
- `MPIT Year Comparison`: year-over-year comparison.
- `MPIT Renewals And Commitments`: upcoming renewals and economic impact.

## Replacement Map

- `MPIT Overview` was replaced by `MPIT Economic Position` and
  `MPIT Economic Detail`.
- `MPIT Monthly Plan` was replaced by `MPIT Year End Forecast`.
- `MPIT Renewals Window` was replaced by `MPIT Renewals And Commitments`.

## Calculation Ownership

All substantial calculations live in:

`master_plan_it/master_plan_it/financial_engine.py`

Report Python files are thin adapters that define columns, call one dataset
function, and return native Frappe report output. JavaScript files define
filters and lightweight formatting. HTML files only format precomputed data.

## Print Formats

Each new report has a Report Print Format HTML file in its report folder:

`master_plan_it/master_plan_it/report/<report_slug>/<report_slug>.html`

Templates include filters, generated metadata, KPI cards, tabular rows, and a
legend. They do not recalculate totals, statuses, project stage rules, or
plafond consumption.
