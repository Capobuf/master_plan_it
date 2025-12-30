from __future__ import annotations

import frappe
from frappe import _


def execute(filters=None):
	if isinstance(filters, str):
		filters = frappe.parse_json(filters)
	filters = frappe._dict(filters or {})
	rows = _get_data(filters)

	columns = [
		{"label": _("Budget"), "fieldname": "budget", "fieldtype": "Link", "options": "MPIT Budget", "width": 180},
		{"label": _("Year"), "fieldname": "year", "fieldtype": "Link", "options": "MPIT Year", "width": 80},
		{"label": _("Category"), "fieldname": "category", "fieldtype": "Link", "options": "MPIT Category", "width": 180},
		{"label": _("Vendor"), "fieldname": "vendor", "fieldtype": "Link", "options": "MPIT Vendor", "width": 150},
		{"label": _("Baseline Amount"), "fieldname": "baseline_amount", "fieldtype": "Currency", "width": 140},
		{"label": _("Amendments Delta"), "fieldname": "amendment_delta", "fieldtype": "Currency", "width": 140},
		{"label": _("Current Budget"), "fieldname": "current_budget", "fieldtype": "Currency", "width": 140},
		{"label": _("Actual Amount"), "fieldname": "actual_amount", "fieldtype": "Currency", "width": 140},
		{"label": _("Variance (Actual - Current)"), "fieldname": "variance", "fieldtype": "Currency", "width": 170},
	]

	chart = _build_chart(rows)

	return columns, rows, None, chart


def _get_data(filters) -> list[dict]:
	params = {}
	conditions = ["b.docstatus = 1"]
	if filters.get("year"):
		conditions.append("b.year = %(year)s")
		params["year"] = filters.year
	if filters.get("budget"):
		conditions.append("b.name = %(budget)s")
		params["budget"] = filters.budget
	if filters.get("category"):
		conditions.append("bl.category = %(category)s")
		params["category"] = filters.category
	if filters.get("vendor"):
		conditions.append("bl.vendor = %(vendor)s")
		params["vendor"] = filters.vendor

	where = " AND ".join(conditions)

	base_rows = frappe.db.sql(
		f"""
		SELECT
			b.name AS budget,
			b.year AS year,
			bl.category AS category,
			bl.vendor AS vendor,
			SUM(COALESCE(bl.annual_net, bl.amount_net, bl.amount)) AS baseline_amount
		FROM `tabMPIT Budget` b
		JOIN `tabMPIT Budget Line` bl ON bl.parent = b.name
		WHERE {where}
		GROUP BY b.name, b.year, bl.category, bl.vendor
		""",
		params,
		as_dict=True,
	)

	actual_conditions = ["1=1"]
	if filters.get("year"):
		actual_conditions.append("year = %(year)s")
	if filters.get("category"):
		actual_conditions.append("category = %(category)s")
	if filters.get("vendor"):
		actual_conditions.append("vendor = %(vendor)s")

	actual_where = " AND ".join(actual_conditions)

	actual_rows = frappe.db.sql(
		f"""
		SELECT year, category, vendor, SUM(COALESCE(amount_net, amount)) AS actual_amount
		FROM `tabMPIT Actual Entry`
		WHERE {actual_where}
		GROUP BY year, category, vendor
		""",
		params,
		as_dict=True,
	)

	base_map = {(r["budget"], r["year"], r["category"], r.get("vendor")): r for r in base_rows}
	actual_map = {(r["year"], r["category"], r.get("vendor")): r["actual_amount"] for r in actual_rows}

	keys = set(base_map.keys())
	result: list[dict] = []
	for key in sorted(
		keys,
		key=lambda k: (str(k[1] or ""), str(k[2] or ""), str(k[3] or ""), str(k[0] or "")),
	):
		budget, year, category, vendor = key
		base = base_map.get(key, {})
		baseline_amount = float(base.get("baseline_amount") or 0)
		amendment_delta = 0.0
		current_budget = baseline_amount
		actual_amount = float(actual_map.get((year, category, vendor), 0) or 0)
		variance = actual_amount - current_budget
		result.append({
			"budget": budget,
			"year": year,
			"category": category,
			"vendor": vendor,
			"baseline_amount": baseline_amount,
			"amendment_delta": amendment_delta,
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
				{"name": _("Current Budget"), "values": [current_totals.get(b, 0) for b in labels]},
				{"name": _("Actual"), "values": [actual_totals.get(b, 0) for b in labels]},
			],
		},
		"type": "bar",
		"axis_options": {"x_axis_mode": "tick", "y_axis_mode": "tick"},
	}
