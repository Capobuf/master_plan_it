# How to run multi-year projects

## Rule
`MPIT Project` is operational context, not an approval workflow container.
Economic data is yearly and comes from `MPIT Expense` documents linked to the project.

## Steps
1) Create `MPIT Project` with status `Open`.
2) For each year, create one or more `MPIT Expense` documents linked to the project.
3) Keep each expense inside one year (`MPIT Expense` is annual).
4) Classify each ordinary expense as:
   - `On Plafond` with `Plafond Reference`, or
   - `Extra`
5) Use reports (`MPIT Overview`, `MPIT Monthly Plan`, `MPIT Project Forecast vs Actual`) to read yearly totals.

## Reporting
Project yearly totals are derived from the financial engine:
- Forecast from active `Estimate` + `Quote` rows
- Actual from active `Actual` rows
- Variance = Forecast - Actual
