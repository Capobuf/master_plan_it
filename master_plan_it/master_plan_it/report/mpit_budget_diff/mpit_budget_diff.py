from __future__ import annotations

import json

import frappe
from frappe import _
from frappe.query_builder.functions import Coalesce, Sum
from master_plan_it.master_plan_it.utils.dashboard_utils import normalize_dashboard_filters

# Report: Budget Diff - Compare two budgets with flexible exclusions.
#
# Exclusion options:
# - Global: exclude by line_kind (Contract, Planned Item, Allowance)
# - Specific: exclude specific vendors, cost centers, or contracts
# - Per-budget: apply exclusions to Budget A only, Budget B only, or both


def execute(filters=None):
	filters = normalize_dashboard_filters(filters)
	filters = frappe._dict(filters or {})

	# Return empty if required filters missing
	if not filters.get("budget_a") or not filters.get("budget_b"):
		return [], [], None, None, []

	_validate_filters(filters)

	# Parse filter options
	only_changed = _parse_checkbox(filters.get("only_changed"))
	exclude_line_kinds = _parse_exclude_line_kinds(filters)
	exclusions = _parse_exclusions(filters)

	# Load data with exclusions applied per-budget
	budget_a_data = _load_budget_totals(
		filters.budget_a,
		exclude_line_kinds,
		exclusions.get("a", {}),
	)
	budget_b_data = _load_budget_totals(
		filters.budget_b,
		exclude_line_kinds,
		exclusions.get("b", {}),
	)

	# Build output
	data, totals = _build_rows(budget_a_data, budget_b_data, only_changed)
	columns = _build_columns()
	summary = _build_summary(totals, filters)
	chart = _get_chart_data(budget_a_data, budget_b_data)

	return columns, data, None, chart, summary


def _validate_filters(filters) -> None:
	if not filters.get("budget_a"):
		frappe.throw(_("Budget A is required"))
	if not filters.get("budget_b"):
		frappe.throw(_("Budget B is required"))
	if filters.budget_a == filters.budget_b:
		frappe.throw(_("Budget A and Budget B must be different"))


def _parse_checkbox(value) -> bool:
	"""Parse checkbox value from various formats."""
	if value in (None, "", "0", 0, False, "false"):
		return False
	return True


def _parse_exclude_line_kinds(filters) -> set:
	"""Parse global line_kind exclusions."""
	excluded = set()
	if _parse_checkbox(filters.get("exclude_contracts")):
		excluded.add("Contract")
	if _parse_checkbox(filters.get("exclude_planned_items")):
		excluded.add("Planned Item")
	if _parse_checkbox(filters.get("exclude_allowances")):
		excluded.add("Allowance")
	return excluded


def _parse_multi_select(value) -> list:
	"""Parse MultiSelectList filter value."""
	if not value:
		return []
	if isinstance(value, list):
		return value
	if isinstance(value, str):
		try:
			parsed = json.loads(value)
			if isinstance(parsed, list):
				return parsed
		except (json.JSONDecodeError, TypeError):
			# Single value as string
			return [value] if value else []
	return []


def _parse_exclusions(filters) -> dict:
	"""
	Parse specific exclusions and determine which budget(s) they apply to.

	Returns dict with keys 'a' and 'b', each containing:
	- vendors: list of vendor names to exclude
	- cost_centers: list of cost center names to exclude
	- contracts: list of contract names to exclude
	- projects: list of project names to exclude
	"""
	# Get exclusion values
	exclude_vendors = _parse_multi_select(filters.get("exclude_vendors"))
	exclude_cost_centers = _parse_multi_select(filters.get("exclude_cost_centers"))
	exclude_contracts = _parse_multi_select(filters.get("exclude_contracts_list"))
	exclude_projects = _parse_multi_select(filters.get("exclude_projects"))

	# Get apply_to setting from Italian labels
	apply_to_raw = filters.get("exclusion_applies_to") or "Entrambi"
	if apply_to_raw in ("Entrambi", "both"):
		apply_to = "both"
	elif apply_to_raw in ("Solo Budget A", "a"):
		apply_to = "a"
	elif apply_to_raw in ("Solo Budget B", "b"):
		apply_to = "b"
	else:
		apply_to = "both"

	# Build exclusion dicts per budget
	exclusions = {
		"a": {"vendors": [], "cost_centers": [], "contracts": [], "projects": []},
		"b": {"vendors": [], "cost_centers": [], "contracts": [], "projects": []},
	}

	if apply_to in ("both", "a"):
		exclusions["a"]["vendors"] = exclude_vendors
		exclusions["a"]["cost_centers"] = exclude_cost_centers
		exclusions["a"]["contracts"] = exclude_contracts
		exclusions["a"]["projects"] = exclude_projects

	if apply_to in ("both", "b"):
		exclusions["b"]["vendors"] = exclude_vendors
		exclusions["b"]["cost_centers"] = exclude_cost_centers
		exclusions["b"]["contracts"] = exclude_contracts
		exclusions["b"]["projects"] = exclude_projects

	return exclusions


def _build_columns() -> list[dict]:
	return [
		{"label": _("Cost Center"), "fieldname": "cost_center", "fieldtype": "Link", "options": "MPIT Cost Center", "width": 200},
		{"label": _("Budget A"), "fieldname": "budget_a_annual_net", "fieldtype": "Currency", "width": 130},
		{"label": _("Budget B"), "fieldname": "budget_b_annual_net", "fieldtype": "Currency", "width": 130},
		{"label": _("Delta (B-A)"), "fieldname": "delta_annual", "fieldtype": "Currency", "width": 130},
		{"label": _("Delta %"), "fieldname": "delta_percent", "fieldtype": "Percent", "width": 100},
	]


def _load_budget_totals(budget: str, exclude_line_kinds: set, exclusions: dict) -> dict:
	"""
	Load budget line totals grouped by cost center.

	Args:
		budget: Budget document name
		exclude_line_kinds: Set of line_kind values to exclude globally
		exclusions: Dict with 'vendors', 'cost_centers', 'contracts' lists
	"""
	BudgetLine = frappe.qb.DocType("MPIT Budget Line")

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

	# Exclude line kinds (global)
	for lk in exclude_line_kinds:
		query = query.where(BudgetLine.line_kind != lk)

	# Exclude specific vendors
	for vendor in exclusions.get("vendors", []):
		if vendor:
			query = query.where(BudgetLine.vendor != vendor)

	# Exclude specific cost centers
	for cc in exclusions.get("cost_centers", []):
		if cc:
			query = query.where(BudgetLine.cost_center != cc)

	# Exclude specific contracts
	for contract in exclusions.get("contracts", []):
		if contract:
			query = query.where(BudgetLine.contract != contract)

	# Exclude specific projects
	for project in exclusions.get("projects", []):
		if project:
			query = query.where(BudgetLine.project != project)

	rows = query.run(as_dict=True)

	result = {}
	for row in rows:
		cc = row.get("cost_center")
		if cc:
			result[cc] = float(row.get("planned") or 0)
	return result


def _build_rows(a_map: dict, b_map: dict, only_changed: bool) -> tuple[list[dict], dict]:
	"""Build report rows comparing two budgets."""
	all_cost_centers = set(a_map.keys()) | set(b_map.keys())

	rows: list[dict] = []
	total_a = 0.0
	total_b = 0.0

	for cc in sorted(all_cost_centers):
		val_a = a_map.get(cc, 0.0)
		val_b = b_map.get(cc, 0.0)
		delta = val_b - val_a

		total_a += val_a
		total_b += val_b

		# Skip unchanged rows if only_changed is True
		if only_changed and abs(delta) < 0.01:
			continue

		# Calculate percentage change
		if val_a > 0:
			delta_pct = (delta / val_a) * 100
		elif val_b > 0:
			delta_pct = 100.0  # New item
		else:
			delta_pct = 0.0

		rows.append({
			"cost_center": cc,
			"budget_a_annual_net": val_a,
			"budget_b_annual_net": val_b,
			"delta_annual": delta,
			"delta_percent": delta_pct,
		})

	# Add total row
	delta_total = total_b - total_a
	if total_a > 0:
		delta_total_pct = (delta_total / total_a) * 100
	elif total_b > 0:
		delta_total_pct = 100.0
	else:
		delta_total_pct = 0.0

	rows.append({
		"cost_center": _("TOTAL"),
		"budget_a_annual_net": total_a,
		"budget_b_annual_net": total_b,
		"delta_annual": delta_total,
		"delta_percent": delta_total_pct,
		"is_total_row": 1,
	})

	totals = {
		"budget_a": total_a,
		"budget_b": total_b,
		"delta": delta_total,
	}

	return rows, totals


def _build_summary(totals: dict, filters: dict) -> list[dict]:
	"""Build report summary cards."""
	budget_a_name = filters.get("budget_a", "A")
	budget_b_name = filters.get("budget_b", "B")

	# Get budget titles for display
	title_a = frappe.db.get_value("MPIT Budget", budget_a_name, "title") or budget_a_name
	title_b = frappe.db.get_value("MPIT Budget", budget_b_name, "title") or budget_b_name

	delta = totals.get("delta", 0)

	return [
		{
			"label": title_a,
			"value": totals.get("budget_a", 0),
			"datatype": "Currency",
			"indicator": "blue",
		},
		{
			"label": title_b,
			"value": totals.get("budget_b", 0),
			"datatype": "Currency",
			"indicator": "blue",
		},
		{
			"label": _("Delta"),
			"value": delta,
			"datatype": "Currency",
			"indicator": "green" if delta <= 0 else "red",
		},
	]


def _get_chart_data(a_map: dict, b_map: dict) -> dict | None:
	"""Build chart comparing budgets by cost center."""
	if not a_map and not b_map:
		return None

	all_cc = set(a_map.keys()) | set(b_map.keys())
	cc_data = []
	for cc in all_cc:
		val_a = a_map.get(cc, 0)
		val_b = b_map.get(cc, 0)
		cc_data.append((cc, val_a, val_b, max(val_a, val_b)))

	cc_data.sort(key=lambda x: x[3], reverse=True)
	cc_data = cc_data[:10]

	if not cc_data:
		return None

	labels = [x[0] for x in cc_data]
	values_a = [x[1] for x in cc_data]
	values_b = [x[2] for x in cc_data]

	return {
		"data": {
			"labels": labels,
			"datasets": [
				{"name": _("Budget A"), "values": values_a},
				{"name": _("Budget B"), "values": values_b},
			],
		},
		"type": "bar",
		"colors": ["#318AD8", "#F5A623"],
	}
