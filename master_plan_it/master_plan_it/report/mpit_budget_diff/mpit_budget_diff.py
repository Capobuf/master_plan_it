from __future__ import annotations

import json

import frappe
from frappe import _
from frappe.query_builder.functions import Coalesce, Sum
from frappe.query_builder import Case
from master_plan_it.master_plan_it.utils.dashboard_utils import normalize_dashboard_filters

# Report: Budget Diff - Compare two budgets with detailed breakdown by line_kind.
#
# Shows for each Cost Center:
# - Budget A: Total, Contracts, Projects, Allowance
# - Budget B: Total, Contracts, Projects, Allowance
# - Delta between totals


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

	# Load detailed data with exclusions applied per-budget
	budget_a_data = _load_budget_details(
		filters.budget_a,
		exclude_line_kinds,
		exclusions.get("a", {}),
	)
	budget_b_data = _load_budget_details(
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
			return [value] if value else []
	return []


def _parse_exclusions(filters) -> dict:
	"""Parse specific exclusions and determine which budget(s) they apply to."""
	exclude_vendors = _parse_multi_select(filters.get("exclude_vendors"))
	exclude_cost_centers = _parse_multi_select(filters.get("exclude_cost_centers"))
	exclude_contracts = _parse_multi_select(filters.get("exclude_contracts_list"))
	exclude_projects = _parse_multi_select(filters.get("exclude_projects"))

	apply_to_raw = filters.get("exclusion_applies_to") or "Entrambi"
	if apply_to_raw in ("Entrambi", "both"):
		apply_to = "both"
	elif apply_to_raw in ("Solo Budget A", "a"):
		apply_to = "a"
	elif apply_to_raw in ("Solo Budget B", "b"):
		apply_to = "b"
	else:
		apply_to = "both"

	exclusions = {
		"a": {"vendors": [], "cost_centers": [], "contracts": [], "projects": []},
		"b": {"vendors": [], "cost_centers": [], "contracts": [], "projects": []},
	}

	if apply_to in ("both", "a"):
		exclusions["a"] = {
			"vendors": exclude_vendors,
			"cost_centers": exclude_cost_centers,
			"contracts": exclude_contracts,
			"projects": exclude_projects,
		}

	if apply_to in ("both", "b"):
		exclusions["b"] = {
			"vendors": exclude_vendors,
			"cost_centers": exclude_cost_centers,
			"contracts": exclude_contracts,
			"projects": exclude_projects,
		}

	return exclusions


def _build_columns() -> list[dict]:
	"""Build report columns with detailed breakdown."""
	return [
		# Cost Center
		{"label": _("Cost Center"), "fieldname": "cost_center", "fieldtype": "Link", "options": "MPIT Cost Center", "width": 180},
		# Budget A breakdown
		{"label": _("A Totale"), "fieldname": "a_total", "fieldtype": "Currency", "width": 110},
		{"label": _("A Contratti"), "fieldname": "a_contracts", "fieldtype": "Currency", "width": 100},
		{"label": _("A Progetti"), "fieldname": "a_projects", "fieldtype": "Currency", "width": 100},
		{"label": _("A Allowance"), "fieldname": "a_allowance", "fieldtype": "Currency", "width": 100},
		# Budget B breakdown
		{"label": _("B Totale"), "fieldname": "b_total", "fieldtype": "Currency", "width": 110},
		{"label": _("B Contratti"), "fieldname": "b_contracts", "fieldtype": "Currency", "width": 100},
		{"label": _("B Progetti"), "fieldname": "b_projects", "fieldtype": "Currency", "width": 100},
		{"label": _("B Allowance"), "fieldname": "b_allowance", "fieldtype": "Currency", "width": 100},
		# Delta
		{"label": _("Delta"), "fieldname": "delta", "fieldtype": "Currency", "width": 110},
		{"label": _("Delta %"), "fieldname": "delta_pct", "fieldtype": "Percent", "width": 80},
	]


def _load_budget_details(budget: str, exclude_line_kinds: set, exclusions: dict) -> dict:
	"""
	Load budget line totals grouped by cost center with breakdown by line_kind.

	Returns dict: {cost_center: {"total": x, "Contract": y, "Planned Item": z, "Allowance": w}}
	"""
	BudgetLine = frappe.qb.DocType("MPIT Budget Line")

	# Amount calculation
	amount_expr = Coalesce(
		BudgetLine.annual_net,
		BudgetLine.amount_net,
		BudgetLine.annual_amount,
		BudgetLine.monthly_amount * 12,
		0,
	)

	# Conditional sums per line_kind
	total_sum = Sum(amount_expr).as_("total")
	contract_sum = Sum(
		Case().when(BudgetLine.line_kind == "Contract", amount_expr).else_(0)
	).as_("contracts")
	project_sum = Sum(
		Case().when(BudgetLine.line_kind == "Planned Item", amount_expr).else_(0)
	).as_("projects")
	allowance_sum = Sum(
		Case().when(BudgetLine.line_kind == "Allowance", amount_expr).else_(0)
	).as_("allowance")

	query = (
		frappe.qb.from_(BudgetLine)
		.select(
			BudgetLine.cost_center,
			total_sum,
			contract_sum,
			project_sum,
			allowance_sum,
		)
		.where(BudgetLine.parent == budget)
		.groupby(BudgetLine.cost_center)
	)

	# Apply exclusions
	for lk in exclude_line_kinds:
		query = query.where(BudgetLine.line_kind != lk)

	for vendor in exclusions.get("vendors", []):
		if vendor:
			query = query.where(BudgetLine.vendor != vendor)

	for cc in exclusions.get("cost_centers", []):
		if cc:
			query = query.where(BudgetLine.cost_center != cc)

	for contract in exclusions.get("contracts", []):
		if contract:
			query = query.where(BudgetLine.contract != contract)

	for project in exclusions.get("projects", []):
		if project:
			query = query.where(BudgetLine.project != project)

	rows = query.run(as_dict=True)

	result = {}
	for row in rows:
		cc = row.get("cost_center")
		if cc:
			result[cc] = {
				"total": float(row.get("total") or 0),
				"contracts": float(row.get("contracts") or 0),
				"projects": float(row.get("projects") or 0),
				"allowance": float(row.get("allowance") or 0),
			}
	return result


def _build_rows(a_map: dict, b_map: dict, only_changed: bool) -> tuple[list[dict], dict]:
	"""Build report rows comparing two budgets with detailed breakdown."""
	all_cost_centers = set(a_map.keys()) | set(b_map.keys())

	rows: list[dict] = []
	totals_a = {"total": 0.0, "contracts": 0.0, "projects": 0.0, "allowance": 0.0}
	totals_b = {"total": 0.0, "contracts": 0.0, "projects": 0.0, "allowance": 0.0}

	for cc in sorted(all_cost_centers):
		a = a_map.get(cc, {"total": 0, "contracts": 0, "projects": 0, "allowance": 0})
		b = b_map.get(cc, {"total": 0, "contracts": 0, "projects": 0, "allowance": 0})

		delta = b["total"] - a["total"]

		# Accumulate totals
		for key in totals_a:
			totals_a[key] += a.get(key, 0)
			totals_b[key] += b.get(key, 0)

		# Skip unchanged rows if only_changed is True
		if only_changed and abs(delta) < 0.01:
			continue

		# Calculate percentage change
		if a["total"] > 0:
			delta_pct = (delta / a["total"]) * 100
		elif b["total"] > 0:
			delta_pct = 100.0
		else:
			delta_pct = 0.0

		rows.append({
			"cost_center": cc,
			"a_total": a["total"],
			"a_contracts": a["contracts"],
			"a_projects": a["projects"],
			"a_allowance": a["allowance"],
			"b_total": b["total"],
			"b_contracts": b["contracts"],
			"b_projects": b["projects"],
			"b_allowance": b["allowance"],
			"delta": delta,
			"delta_pct": delta_pct,
		})

	# Add total row
	delta_total = totals_b["total"] - totals_a["total"]
	if totals_a["total"] > 0:
		delta_total_pct = (delta_total / totals_a["total"]) * 100
	elif totals_b["total"] > 0:
		delta_total_pct = 100.0
	else:
		delta_total_pct = 0.0

	rows.append({
		"cost_center": _("TOTALE"),
		"a_total": totals_a["total"],
		"a_contracts": totals_a["contracts"],
		"a_projects": totals_a["projects"],
		"a_allowance": totals_a["allowance"],
		"b_total": totals_b["total"],
		"b_contracts": totals_b["contracts"],
		"b_projects": totals_b["projects"],
		"b_allowance": totals_b["allowance"],
		"delta": delta_total,
		"delta_pct": delta_total_pct,
		"is_total_row": 1,
	})

	totals = {
		"budget_a": totals_a["total"],
		"budget_b": totals_b["total"],
		"delta": delta_total,
	}

	return rows, totals


def _build_summary(totals: dict, filters: dict) -> list[dict]:
	"""Build report summary cards."""
	budget_a_name = filters.get("budget_a", "A")
	budget_b_name = filters.get("budget_b", "B")

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
		val_a = a_map.get(cc, {}).get("total", 0)
		val_b = b_map.get(cc, {}).get("total", 0)
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
		"axisOptions": {
			"xIsSeries": False,
		},
		"tooltipOptions": {
			"formatTooltipY": None,  # Disable auto currency formatting
		},
	}
