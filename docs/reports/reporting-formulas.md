# Reporting Formulas

## Actual

Actual is the sum of active `MPIT Expense Row` records where:

- `row_state = "Active"`
- `row_phase = "Actual"`

## Forecast

Forecast is the sum of active rows where:

- `row_state = "Active"`
- `row_phase in ("Estimate", "Quote")`

Forecast inclusion also depends on the report basis and project stage.

## Plafond

Plafond total is the sum of active rows inside `MPIT Expense` records where:

- `expense_kind = "Plafond"`

## Plafond Consumption

Plafond consumption is the sum of actual active ordinary expense rows where:

- `expense_kind = "Ordinary"`
- `uses_plafond = 1`
- `plafond_expense` is set

## Extra

Extra is the sum of actual active ordinary expense rows where:

- `expense_kind = "Ordinary"`
- `is_extra = 1`

## Year-End Forecast

Year-end forecast is:

`actual_total + forecast_remaining`

For `Forecast only`, actual rows are excluded. For `Actual only`, forecast rows
are excluded.

## Available Budget

Available budget is implemented as:

`operating_budget + plafond_total`

Operating budget is derived from active standard forecast rows for approved
projects and rows without a project. Approved project forecast is kept as a
planning bucket named `approved_projects_forecast`, not as an official approved
budget limit.

## Remaining Or Over

Remaining or over is:

`available_budget - year_end_forecast`

Negative values represent overrun.

## Variance

Variance amount is:

`forecast_amount - budget_amount`

Variance percent is:

`variance_amount / budget_amount * 100`

When budget is zero and forecast exists, variance percent is reported as 100.

## What-If Additions

What-if additions are active estimate and quote rows included by scenario flags:

- approved projects;
- proposed projects;
- ideas;
- extra;
- plafond-funded rows.

The report is read-only and does not write scenario records.

## Project Stage Interpretation

Project stages are the exact local values:

- `Idea`
- `Proposed`
- `Approved`
- `Deferred`
- `Rejected`

Rows without an effective project are bucketed as `No Project`.
