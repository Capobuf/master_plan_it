# Logic freeze (GATE 0.C)

## Annualization / overlap (to be rewritten)
- apps/master_plan_it/master_plan_it/annualization.py: `overlap_months` now counts calendar months touched; recurrence supports Monthly/Quarterly/Annual/None (Custom removed). `annualize` multiplies by overlap months; `validate_recurrence_rule` blocks unsupported values.
- apps/master_plan_it/master_plan_it/amounts.py: `compute_line_amounts` scales net/vat/gross by `overlap_months/12`. Relies on `compute_amounts` with recurrence Monthly/Quarterly/Annual/None and defaults annualization if no period dates. Uses annual_amount as master for VAT split.
- apps/master_plan_it/master_plan_it/master_plan_it/doctype/mpit_budget/mpit_budget.py: validates recurrence per line, computes overlap via `annualization.overlap_months` (months touched), blocks save if zero overlap, defaults to 12 months when no period dates, uses `amounts.compute_line_amounts` to populate monthly/annual/net/vat/gross and totals. No concept of Forecast/Baseline split or refresh from sources.

## MPIT Actual Entry
- apps/master_plan_it/master_plan_it/master_plan_it/doctype/mpit_actual_entry/mpit_actual_entry.py: on validate sets year from posting_date (lookup MPIT Year), computes VAT split via tax helpers, no entry_kind, no cost_center, no status-based read-only, no XOR contract/project enforcement. Reports currently treat entries as “actuals”.

## Projects totals
- apps/master_plan_it/master_plan_it/master_plan_it/doctype/mpit_project/mpit_project.py: allocations/quotes VAT split; totals: planned_total_net=sum allocs, quoted_total_net=sum quotes, expected_total_net=quoted if any else planned. No deltas/savings, no category links on allocations/quotes, no monthly distribution, status gating only ensures allocations exist for advanced statuses. Whitelist `get_project_actuals_totals` returns SUM of Actual Entry amount_net/amount (no status filter).

## Reports (V1 semantics to rewrite)
- apps/master_plan_it/master_plan_it/master_plan_it/report/mpit_current_budget_vs_actual/mpit_current_budget_vs_actual.py: Baseline = budget lines; “current” = baseline + Budget Amendments delta; Actual = SUM Actual Entry (no status filter); variance = actual − current. Heavily amendment-centric.
- apps/master_plan_it/master_plan_it/master_plan_it/report/mpit_monthly_budget_vs_actual/mpit_monthly_budget_vs_actual.py: Planned = (Baseline + Amendments) annual net divided by 12 (not segment-aware); optional include_portfolio flag using `is_portfolio_bucket`; Actual by month = Actual Entry sums (no status filter). Monthly view does not respect rate changes, spread, or project distributions.
- apps/master_plan_it/master_plan_it/master_plan_it/report/mpit_projects_planned_vs_actual/mpit_projects_planned_vs_actual.py: Planned/Quoted/Expected from allocations/quotes, Actual from Actual Entry grouped by project/year (no status filter), variance uses actual − expected/planned. No deltas, no category granularity, no distribution.
- Dashboard chart source: apps/master_plan_it/master_plan_it/master_plan_it/dashboard_chart_source/mpit_amendments_delta_net/mpit_amendments_delta_net.py uses Budget Amendments + Amendment Lines to show deltas by category/vendor; to be replaced.

## Other V1 hooks
- Workspace: apps/master_plan_it/master_plan_it/master_plan_it/workspace/master_plan_it/master_plan_it.json links to Baseline Expense and Budget Amendment.
- Patches: apps/master_plan_it/master_plan_it/patches/v0_1_0/backfill_vat_fields.py and v1_0/migrate_amounts_to_monthly_annual.py reference Baseline Expense/Budget Line/Amendment Line fields (annual/monthly/custom_period_months).
- Devtools/tests: devtools/bootstrap.py, devtools/verify.py, devtools/import_baseline.py, devtools/test_amounts.py rely on Baseline Expense and Custom recurrence; tests/test_smoke.py asserts Baseline Expense existence.***
