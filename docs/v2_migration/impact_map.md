# GATE 0.A — Repo grep mapping (V2 cleanup)

| match_string | file_path | action (delete/update) | step_owner |
| --- | --- | --- | --- |
| MPIT Baseline Expense | master_plan_it/master_plan_it/doctype/mpit_baseline_expense/* | delete (DocType, controllers, fixtures) | agent |
| MPIT Baseline Expense | master_plan_it/master_plan_it/devtools/*baseline* | delete (devtools import/test scripts) | agent |
| MPIT Baseline Expense | master_plan_it/master_plan_it/workspace/master_plan_it/master_plan_it.json | delete link from workspace | agent |
| MPIT Baseline Expense | docs/how-to/03-import-baseline-excel.md, docs/tutorials/00-first-budget-cycle.md, docs/reference/01-data-model.md | delete/replace docs/templates | agent |
| MPIT Baseline Expense | master_plan_it/master_plan_it/patches/v0_1_0/backfill_vat_fields.py, patches/v1_0/migrate_amounts_to_monthly_annual.py | delete/retire patches | agent |
| MPIT Baseline Expense | master_plan_it/master_plan_it/tests/test_smoke.py | update smoke tests to remove dependency | agent |
| MPIT Budget Amendment | master_plan_it/master_plan_it/doctype/mpit_budget_amendment/* | delete | agent |
| MPIT Budget Amendment | master_plan_it/master_plan_it/dashboard_chart_source/mpit_amendments_delta_net/* | delete chart source | agent |
| MPIT Budget Amendment | master_plan_it/master_plan_it/devtools/bootstrap.py, devtools/verify.py | delete/update devtools menus | agent |
| MPIT Budget Amendment | docs/how-to/05-amend-budget.md, docs/tutorials/00-first-budget-cycle.md, docs/reference/03-workflows.md | delete/replace docs/workflows | agent |
| MPIT Amendment Line | master_plan_it/master_plan_it/doctype/mpit_amendment_line/* | delete child DocType | agent |
| MPIT Amendment Line | master_plan_it/master_plan_it/report/*(current|monthly)_budget_vs_actual*.py | rewrite reports to remove amendments | agent |
| is_portfolio_bucket | master_plan_it/master_plan_it/doctype/mpit_budget_line/mpit_budget_line.json | remove field and logic | agent |
| is_portfolio_bucket | master_plan_it/master_plan_it/report/mpit_monthly_budget_vs_actual/mpit_monthly_budget_vs_actual.py | rewrite to drop portfolio filters | agent |
| source_baseline_expense | master_plan_it/master_plan_it/doctype/mpit_contract/mpit_contract.json | remove field and references | agent |
| custom_period_months | master_plan_it/master_plan_it/doctype/mpit_budget_line/mpit_budget_line.json, mpit_baseline_expense.json | remove field and dependencies | agent |
| custom_period_months | master_plan_it/master_plan_it/amounts.py, annualization.py | rewrite logic to remove Custom recurrence | agent |
| recurrence_rule "Custom" | docs/how-to/import/*, docs/reference/10-money-vat-annualization.md | delete/replace docs and validation text | agent |

Binary/log surfaces (data/ and data/sites/logs, .pyc) also contain the matches; they will be removed/ignored when cleaning workspace artifacts.

## Impact map (Step 0 deliverable)
- To Delete: Baseline Expense DocType + patches + devtools + docs/templates; Budget Amendment + Amendment Line + dashboard chart + workflow docs; portfolio bucket fields/filters; Custom recurrence logic/docs.
- To Rewrite: Reports (`mpit_current_budget_vs_actual`, `mpit_monthly_budget_vs_actual`, `mpit_projects_planned_vs_actual`), Budget controller for V2 refresh, Actual Entry semantics, Project totals logic, workspace shortcuts, ADRs.
- To Keep (with adjustments): MPIT Actual Entry (rename semantics + new fields), MPIT Contract/Project (extend with cost_center, spread, rate schedule), MPIT Budget/Budget Line (simplify fields, add line_kind/source_key), devtools/tests scaffold (aligned to V2).
- Needs decision: none pending (EPIC locked clarifications supplied).***
