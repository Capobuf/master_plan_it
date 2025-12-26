from __future__ import annotations

import frappe
from frappe import _


def execute(filters=None):
	if isinstance(filters, str):
		filters = frappe.parse_json(filters)
	filters = frappe._dict(filters or {})
	rows = _get_data(filters)

	columns = [
		_("Budget") + ":Link/MPIT Budget:180",
		_("Year") + ":Link/MPIT Year:80",
		_("Category") + ":Link/MPIT Category:180",
		_("Vendor") + ":Link/MPIT Vendor:150",
		_("Budget Amount") + ":Currency:140",
		_("Actual Amount") + ":Currency:140",
		_("Variance (Actual - Budget)") + ":Currency:160",
	]

	chart = _build_chart(rows)

	return columns, rows, None, chart


def _get_data(filters) -> list[dict]:
	conditions = ["b.docstatus = 1"]
	params = {}
	if filters.get("year"):
		conditions.append("b.year = %(year)s")
		params["year"] = filters.year
	if filters.get("category"):
		conditions.append("bl.category = %(category)s")
		params["category"] = filters.category
	if filters.get("vendor"):
		conditions.append("bl.vendor = %(vendor)s")
		params["vendor"] = filters.vendor
	if filters.get("budget"):
		conditions.append("b.name = %(budget)s")
		params["budget"] = filters.budget

	where = " AND ".join(conditions)

	return frappe.db.sql(
		f"""
		WITH actuals AS (
			SELECT category, year, vendor, SUM(COALESCE(amount_net, amount)) AS actual_amount
			FROM `tabMPIT Actual Entry`
			GROUP BY category, year, vendor
		)
		SELECT
			b.name AS budget,
			b.year AS year,
			bl.category AS category,
			bl.vendor AS vendor,
			SUM(COALESCE(bl.annual_net, bl.amount_net, bl.amount)) AS budget_amount,
			COALESCE(a.actual_amount, 0) AS actual_amount,
			COALESCE(a.actual_amount, 0) - SUM(COALESCE(bl.annual_net, bl.amount_net, bl.amount)) AS variance
		FROM `tabMPIT Budget` b
		JOIN `tabMPIT Budget Line` bl ON bl.parent = b.name
		LEFT JOIN actuals a ON a.category = bl.category AND a.year = b.year AND a.vendor <=> bl.vendor
		WHERE {where}
		GROUP BY b.name, b.year, bl.category, bl.vendor, a.actual_amount
		ORDER BY b.year, b.name, bl.category, bl.vendor
		""",
		params,
		as_dict=True,
	)


def _build_chart(rows: list[dict]) -> dict | None:
	if not rows:
		return None

	budget_totals = {}
	actual_totals = {}
	for r in rows:
		budget_totals[r["budget"]] = budget_totals.get(r["budget"], 0) + float(r.get("budget_amount") or 0)
		actual_totals[r["budget"]] = actual_totals.get(r["budget"], 0) + float(r.get("actual_amount") or 0)

	labels = sorted(budget_totals.keys())
	return {
		"data": {
			"labels": labels,
			"datasets": [
				{"name": _("Budget"), "values": [budget_totals.get(b, 0) for b in labels]},
				{"name": _("Actual"), "values": [actual_totals.get(b, 0) for b in labels]},
			],
		},
		"type": "bar",
		"axis_options": {"x_axis_mode": "tick", "y_axis_mode": "tick"},
	}
