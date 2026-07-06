# Report Refactor Inventory

Date: 2026-07-06

## Branch

Local branch found during implementation: `refactor/reports`.

The plan referenced `develop`; no branch switch was performed.

## Report Folders Found Before Refactor

- `mpit_expenses`
- `mpit_monthly_plan`
- `mpit_overview`
- `mpit_project_forecast_vs_actual`
- `mpit_renewals_window`

No folders were found for `mpit_budget_diff`, `mpit_actual_entries`, or
`mpit_projects_planned_vs_exceptions`.

## Local DocTypes Read

- `MPIT Expense`
- `MPIT Expense Row`
- `MPIT Cost Center`
- `MPIT Project`
- `MPIT Contract`
- `MPIT Vendor`
- `MPIT Year`

Verified fields used by the reporting layer include expense kind, year, cost
center, project, contract, plafond flags, extra flag, row state, row phase,
vendor, net/VAT/gross amounts, spend/start/end dates, distribution, project
workflow state, and contract renewal/annual amount fields.

## Legacy References Found

Legacy references existed in:

- workspace fixtures;
- workspace sidebar fixtures;
- dashboard chart fixtures;
- number card fixtures;
- dashboard defaults and verification devtools;
- locale template/catalog comments;
- report tests;
- audit documentation.

## Reports Removed

- `master_plan_it/master_plan_it/report/mpit_overview/`
- `master_plan_it/master_plan_it/report/mpit_monthly_plan/`
- `master_plan_it/master_plan_it/report/mpit_renewals_window/`

## Unknowns And Risks

- Runtime behavior was not verified in a live Frappe bench.
- Existing database-heavy tests require a Frappe test site and were not treated
  as safe static checks.
- Some legacy helper functions remain in `financial_engine.py` for compatibility
  with non-report call sites.
