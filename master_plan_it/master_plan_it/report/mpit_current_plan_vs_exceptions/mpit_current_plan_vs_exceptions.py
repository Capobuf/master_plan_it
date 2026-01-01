from __future__ import annotations

import frappe
from frappe import _

# Report: Current Plan vs Exceptions by cost center and vendor.
# Inputs: filters (year, budget optional, cost_center, vendor, entry_kind).
# Output: columns/rows aggregated by cost center/vendor, chart totals per budget.


def execute(filters=None):
	if isinstance(filters, str):
		filters = frappe.parse_json(filters)
	filters = frappe._dict(filters or {})
	rows = _get_data(filters)

	columns = [
		{"label": _("Budget"), "fieldname": "budget", "fieldtype": "Link", "options": "MPIT Budget", "width": 160},
		{"label": _("Year"), "fieldname": "year", "fieldtype": "Link", "options": "MPIT Year", "width": 70},
		{"label": _("Cost Center"), "fieldname": "cost_center", "fieldtype": "Link", "options": "MPIT Cost Center", "width": 180},
		{"label": _("Vendor"), "fieldname": "vendor", "fieldtype": "Link", "options": "MPIT Vendor", "width": 140},
		{"label": _("Current Plan"), "fieldname": "current_budget", "fieldtype": "Currency", "width": 140},
		{"label": _("Exceptions / Allowance"), "fieldname": "actual_amount", "fieldtype": "Currency", "width": 150},
		{"label": _("Variance (Exceptions - Plan)"), "fieldname": "variance", "fieldtype": "Currency", "width": 180},
	]

	chart = _build_chart(rows)

	return columns, rows, None, chart


def _get_data(filters) -> list[dict]:
	params = {}
	budget = _resolve_current_budget(filters)
	if not budget:
		return []

	conditions = ["bl.parent = %(budget)s", "COALESCE(bl.is_active,1)=1"]
	params["budget"] = budget
	if filters.get("cost_center"):
		conditions.append("bl.cost_center = %(cost_center)s")
		params["cost_center"] = filters.cost_center
	if filters.get("vendor"):
		conditions.append("bl.vendor = %(vendor)s")
		params["vendor"] = filters.vendor

	where = " AND ".join(conditions)

	base_rows = frappe.db.sql(
		f"""
		SELECT
			%(budget)s AS budget,
			b.year AS year,
			bl.cost_center AS cost_center,
			bl.vendor AS vendor,
			SUM(COALESCE(bl.annual_net, bl.amount_net, bl.annual_amount, bl.amount)) AS current_budget
		FROM `tabMPIT Budget Line` bl
		JOIN `tabMPIT Budget` b ON b.name = bl.parent
		WHERE {where}
		GROUP BY bl.cost_center, bl.vendor, b.year
		""",
		params,
		as_dict=True,
	)

	actual_conditions = ["status = 'Verified'"]
	if filters.get("year"):
		actual_conditions.append("year = %(year)s")
		params["year"] = filters.year
	if filters.get("cost_center"):
		actual_conditions.append("cost_center = %(cost_center)s")
	if filters.get("vendor"):
		actual_conditions.append("vendor = %(vendor)s")
	if filters.get("entry_kind"):
		actual_conditions.append("entry_kind = %(entry_kind)s")
		params["entry_kind"] = filters.entry_kind
	else:
		actual_conditions.append("entry_kind in ('Delta','Allowance Spend')")

	actual_where = " AND ".join(actual_conditions)

	actual_rows = frappe.db.sql(
		f"""
		SELECT year, cost_center, vendor, SUM(COALESCE(amount_net, amount)) AS actual_amount
		FROM `tabMPIT Actual Entry`
		WHERE {actual_where}
		GROUP BY year, cost_center, vendor
		""",
		params,
		as_dict=True,
	)

	base_map = {(r["cost_center"], r.get("vendor")): r for r in base_rows}
	actual_map = {(r["cost_center"], r.get("vendor")): r["actual_amount"] for r in actual_rows}

	keys = set(base_map.keys()) | set(actual_map.keys())
	result: list[dict] = []
	for key in sorted(
		keys,
		key=lambda k: (str(k[0] or ""), str(k[1] or "")),
	):
		cost_center, vendor = key
		base = base_map.get(key, {})
		year = base.get("year") or filters.get("year")
		current_budget = float(base.get("current_budget") or 0)
		actual_amount = float(actual_map.get((cost_center, vendor), 0) or 0)
		variance = actual_amount - current_budget
		result.append({
			"budget": budget,
			"year": year,
			"cost_center": cost_center,
			"vendor": vendor,
			"current_budget": current_budget,
			"actual_amount": actual_amount,
			"variance": variance,
		})

	return result


def _build_chart(rows: list[dict]) -> dict | None:
	if not rows:
		return None

	current_totals = {}
	actual_totals = {}
	for r in rows:
		current_totals[r["budget"]] = current_totals.get(r["budget"], 0) + float(r.get("current_budget") or 0)
		actual_totals[r["budget"]] = actual_totals.get(r["budget"], 0) + float(r.get("actual_amount") or 0)

	labels = sorted(current_totals.keys())
	return {
		"data": {
			"labels": labels,
			"datasets": [
				{"name": _("Current Plan"), "values": [current_totals.get(b, 0) for b in labels]},
				{"name": _("Exceptions / Allowance"), "values": [actual_totals.get(b, 0) for b in labels]},
			],
		},
		"type": "bar",
		"axis_options": {"x_axis_mode": "tick", "y_axis_mode": "tick"},
	}


def _resolve_current_budget(filters) -> str | None:
	"""Active Forecast for year (if any), else Baseline. Budget filter overrides."""
	if filters.get("budget"):
		return filters.budget

	year = filters.get("year")
	if not year:
		return None

	active = frappe.db.get_value(
		"MPIT Budget",
		{"year": year, "budget_kind": "Forecast", "is_active_forecast": 1},
		"name",
	)
	if active:
		return active

	return frappe.db.get_value(
		"MPIT Budget",
		{"year": year, "budget_kind": "Baseline"},
		"name",
	)
