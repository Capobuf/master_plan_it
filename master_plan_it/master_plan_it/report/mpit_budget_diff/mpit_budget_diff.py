from __future__ import annotations

import frappe
from frappe import _
from frappe.query_builder.functions import Coalesce, Sum
from master_plan_it.master_plan_it.utils.dashboard_utils import normalize_dashboard_filters

# Report: Budget Diff between two budgets grouped by Cost Center (and optionally Vendor).
# Inputs: budget_a, budget_b (required), group_by (CostCenter+Vendor or CostCenter), only_changed flag.
# Outputs: rows with annual/monthly deltas and summary total.


def execute(filters=None):
	filters = normalize_dashboard_filters(filters)
	filters = frappe._dict(filters or {})

	_validate_filters(filters)
	group_by = (filters.get("group_by") or "CostCenter+Vendor").strip()
	only_changed = frappe.utils.cint(filters.get("only_changed", 1))

	# 1. Load data with business filters applied
	budget_a_data = _load_budget_totals(filters.budget_a, group_by, filters)
	budget_b_data = _load_budget_totals(filters.budget_b, group_by, filters)

	# 2. Build Rows & Columns
	data, totals = _build_rows(budget_a_data, budget_b_data, group_by, only_changed, filters)
	columns = _build_columns()
	summary = _build_summary(totals)
	chart = _get_chart_data(budget_a_data, budget_b_data, filters)

	return columns, data, None, chart, summary


def _validate_filters(filters) -> None:
	if not filters.get("budget_a"):
		frappe.throw(_("budget_a is required"))
	if not filters.get("budget_b"):
		frappe.throw(_("budget_b is required"))
	if filters.budget_a == filters.budget_b:
		frappe.throw(_("budget_a and budget_b must be different budgets"))
	if filters.get("group_by") and filters.get("group_by") not in {"CostCenter+Vendor", "CostCenter"}:
		frappe.throw(_("group_by must be either 'CostCenter+Vendor' or 'CostCenter'"))


def _build_columns() -> list[dict]:
	return [
		{"label": _("Cost Center"), "fieldname": "cost_center", "fieldtype": "Link", "options": "MPIT Cost Center", "width": 200},
		{"label": _("Vendor"), "fieldname": "vendor", "fieldtype": "Link", "options": "MPIT Vendor", "width": 180},
		{"label": _("Project"), "fieldname": "project", "fieldtype": "Link", "options": "MPIT Project", "width": 180, "hidden": 1},
		{"label": _("Budget A Annual Net"), "fieldname": "budget_a_annual_net", "fieldtype": "Currency", "width": 140},
		{"label": _("Budget B Annual Net"), "fieldname": "budget_b_annual_net", "fieldtype": "Currency", "width": 140},
		{"label": _("Delta Annual (B - A)"), "fieldname": "delta_annual", "fieldtype": "Currency", "width": 150},
		{"label": _("Budget A Monthly Eq"), "fieldname": "budget_a_monthly_eq", "fieldtype": "Currency", "width": 140},
		{"label": _("Budget B Monthly Eq"), "fieldname": "budget_b_monthly_eq", "fieldtype": "Currency", "width": 140},
		{"label": _("Delta Monthly Eq"), "fieldname": "delta_monthly", "fieldtype": "Currency", "width": 130},
	]


def _load_budget_totals(budget: str, group_by: str, filters: dict) -> dict:
	BudgetLine = frappe.qb.DocType("MPIT Budget Line")

	# Sum with fallback chain matching original COALESCE logic
	planned = Sum(
		Coalesce(
			BudgetLine.annual_net,
			BudgetLine.amount_net,
			BudgetLine.annual_amount,
			BudgetLine.monthly_amount * 12,
			0,
		)
	).as_("planned")

	query = (
		frappe.qb.from_(BudgetLine)
		.select(BudgetLine.cost_center, planned)
		.where(BudgetLine.parent == budget)
		.groupby(BudgetLine.cost_center)
	)

	# Apply optional filters
	if filters.get("project"):
		query = query.where(BudgetLine.project == filters.project)
	if filters.get("cost_center"):
		query = query.where(BudgetLine.cost_center == filters.cost_center)
	if filters.get("vendor"):
		query = query.where(BudgetLine.vendor == filters.vendor)

	if group_by == "CostCenter+Vendor":
		query = query.select(BudgetLine.vendor).groupby(BudgetLine.vendor)

	rows = query.run(as_dict=True)

	result = {}
	for row in rows:
		# key is tuple (cost_center, vendor)
		key = (row.get("cost_center"), row.get("vendor") if group_by == "CostCenter+Vendor" else None)
		result[key] = float(row.get("planned") or 0)
	return result


def _build_rows(a_map: dict, b_map: dict, group_by: str, only_changed: int, filters: dict) -> tuple[list[dict], dict]:
	keys = set(a_map.keys()) | set(b_map.keys())

	rows: list[dict] = []
	
	# Calculate totals
	# If we have specific line-level filters, we MUST sum the filtered lines (a_map/b_map)
	# If we have NO line-level filters, we should prefer the Document Total from the Budget Header
	# to ensure 100% consistency with the Budget View.
	has_filters = any(filters.get(k) for k in ["project", "cost_center", "vendor"])
	
	if has_filters:
		total_a = sum(a_map.values())
		total_b = sum(b_map.values())
	else:
		# Fetch robust totals from document (safe fallback to 0)
		total_a = frappe.db.get_value("MPIT Budget", filters.budget_a, "total_amount_net") or 0.0
		total_b = frappe.db.get_value("MPIT Budget", filters.budget_b, "total_amount_net") or 0.0

	for key in sorted(keys, key=lambda k: (str(k[0] or ""), str(k[1] or ""))):
		cost_center, vendor = key
		planned_a = float(a_map.get(key, 0) or 0)
		planned_b = float(b_map.get(key, 0) or 0)
		delta_annual = planned_b - planned_a

		if only_changed and abs(delta_annual) < 0.0001:
			continue

		monthly_a = planned_a / 12.0
		monthly_b = planned_b / 12.0
		delta_monthly = monthly_b - monthly_a

		rows.append({
			"cost_center": cost_center,
			"vendor": vendor,
			"budget_a_annual_net": planned_a,
			"budget_b_annual_net": planned_b,
			"delta_annual": delta_annual,
			"budget_a_monthly_eq": monthly_a,
			"budget_b_monthly_eq": monthly_b,
			"delta_monthly": delta_monthly,
		})

	# Total Row
	delta_total = total_b - total_a
	if rows or (not only_changed): # Show total if we have rows OR if we are showing everything
		rows.append({
			"cost_center": _("Total"),
			"vendor": "",
			"budget_a_annual_net": total_a,
			"budget_b_annual_net": total_b,
			"delta_annual": delta_total,
			"budget_a_monthly_eq": total_a / 12.0,
			"budget_b_monthly_eq": total_b / 12.0,
			"delta_monthly": delta_total / 12.0,
			"is_total_row": 1,
		})

	totals = {
		"budget_a_annual_net": total_a,
		"budget_b_annual_net": total_b,
		"delta_annual": delta_total
	}

	return rows, totals


def _build_summary(totals: dict) -> list[dict]:
	return [
		{
			"label": _("Budget A"),
			"value": frappe.utils.fmt_money(totals.get("budget_a_annual_net", 0)),
			"indicator": "blue",
		},
		{
			"label": _("Budget B"),
			"value": frappe.utils.fmt_money(totals.get("budget_b_annual_net", 0)),
			"indicator": "blue",
		},
		{
			"label": _("Delta (B - A)"),
			"value": frappe.utils.fmt_money(totals.get("delta_annual", 0)),
			"indicator": "green" if (totals.get("delta_annual", 0) or 0) >= 0 else "red",
		},
	]


def _get_chart_data(a_map: dict, b_map: dict, filters: dict) -> dict:
	# Prepare data for chart: Top Cost Centers by Total Budget (A+B)
	# Merge keys by Cost Center only
	cc_totals = {}
	
	# Helper to aggregate by cost center
	def aggregate(source_map, target_dict, key_idx):
		for key, val in source_map.items():
			cc = key[0] # Cost Center
			if cc not in target_dict:
				target_dict[cc] = [0.0, 0.0] # [A, B]
			target_dict[cc][key_idx] += val

	aggregate(a_map, cc_totals, 0)
	aggregate(b_map, cc_totals, 1)

	# Sort by max value of either A or B desc, take top 10
	sorted_cc = sorted(cc_totals.items(), key=lambda x: max(x[1][0], x[1][1]), reverse=True)[:10]

	labels = []
	dataset_a = []
	dataset_b = []

	for cc, values in sorted_cc:
		labels.append(cc)
		dataset_a.append(values[0])
		dataset_b.append(values[1])

	return {
		"data": {
			"labels": labels,
			"datasets": [
				{"name": _("Budget A"), "values": dataset_a},
				{"name": _("Budget B"), "values": dataset_b}
			]
		},
		"type": "bar",
		"colors": ["#428bca", "#f5d342"] # Blue, Yellow-ish
	}
