# Data model freeze (GATE 0.B)

Source paths inspected on 2025-xx-xx (no edits performed).

## MPIT Budget — master_plan_it/master_plan_it/doctype/mpit_budget/mpit_budget.json
- Meta: submittable=1, editable_grid=1.
- Fields: naming_series (hidden, reqd), year (Link MPIT Year, reqd), title (Data), workflow_state (Select Draft/Proposed/In Review/Approved), lines (Table MPIT Budget Line), totals (monthly/annual/net/vat/gross), amended_from.
- Missing for V2: budget_kind, is_active_forecast, baseline_ref, cost_center dimension, refresh hooks.

## MPIT Budget Line — master_plan_it/master_plan_it/doctype/mpit_budget_line/mpit_budget_line.json
- Meta: child table.
- Fields: category (reqd), vendor, description, qty, unit_price, monthly_amount, annual_amount, amount_includes_vat, vat_rate, amount_net/vat/gross, recurrence_rule (Monthly/Quarterly/Annual/None), period_start/end_date, annual_net/vat/gross, contract, project, baseline_expense, cost_type (CAPEX/OPEX), is_active.
- Duplicated annual_net/vat/gross entries in JSON (appears twice).
- Missing for V2: line_kind, source_key (read-only), is_generated, cost_center, fetch rules from contract/project, removal of baseline_expense link, removal of portfolio remnants, allowance semantics.

## MPIT Contract — master_plan_it/master_plan_it/doctype/mpit_contract/mpit_contract.json
- Meta: editable_grid=1.
- Fields: title (reqd), vendor (reqd), category (reqd), contract_kind (Contract/Subscription/Annual Renewal/Maintenance, reqd), status (Draft/Active/Pending Renewal/Renewed/Cancelled/Expired), auto_renew, owner_user; billing_cycle (Monthly/Quarterly/Annual/Other), current_amount(+vat flags/split), start/end/next_renewal/notice_days, notes/attachment.
- Missing for V2: cost_center, spread_months/spread_start_date/spread_end_date, rate schedule child table, mutual exclusion validation, removal of source_baseline_expense and current_amount-centric model.

## MPIT Project — master_plan_it/master_plan_it/doctype/mpit_project/mpit_project.json
- Fields: title (reqd), status (Draft/Proposed/Approved/In Progress/On Hold/Completed/Cancelled), owner_user, start_date, end_date, description; financial summary (HTML), planned_total_net, quoted_total_net, expected_total_net; child tables allocations (MPIT Project Allocation), quotes (MPIT Project Quote), milestones.
- Missing for V2: planned_start_date/planned_end_date distinction (currently start/end), cost_center, category-level allocations/quotes, delta/exception link, quote status values (Approved/Informational), distributions by months touched.

## MPIT Project Allocation — master_plan_it/master_plan_it/doctype/mpit_project_allocation/mpit_project_allocation.json
- Child table: year (reqd Link MPIT Year), planned_amount (reqd), includes_vat flag, vat_rate, net/vat/gross splits.
- Missing: category link (reqd in V2), cost_center (if needed), distribution fields.

## MPIT Project Quote — master_plan_it/master_plan_it/doctype/mpit_project_quote/mpit_project_quote.json
- Child table: vendor, amount (+includes_vat/vat_rate splits), quote_date, attachment, status (Received/Accepted/Rejected).
- Missing: category link, status choices need to be Informational/Approved (default Informational, approval restricted), cost_center if needed.

## MPIT Settings — master_plan_it/master_plan_it/doctype/mpit_settings/mpit_settings.json
- Fields: currency (reqd), renewal_window_days, budget_naming_series.
- Missing: V1-only portfolio threshold to remove; any V2 refresh settings not present (none specified).

## MPIT Actual Entry — master_plan_it/master_plan_it/doctype/mpit_actual_entry/mpit_actual_entry.json
- Fields: posting_date (reqd), year (reqd), status (Recorded/Verified), description; amount (+includes_vat/vat_rate split); category (reqd), vendor, contract, project, budget, budget_line_ref.
- Missing for V2: entry_kind (Delta/Allowance Spend), cost_center, read-only after Verified, XOR validation, allow negative for allowance with description, fetch rules for cost_center from links, relabeling to Exceptions/Allowance in UI.***
