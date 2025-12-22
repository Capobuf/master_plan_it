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
		_("Baseline Amount") + ":Currency:140",
		_("Amendments Delta") + ":Currency:140",
		_("Current Budget") + ":Currency:140",
		_("Actual Amount") + ":Currency:140",
		_("Variance (Actual - Current)") + ":Currency:170",
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
			SUM(bl.amount) AS baseline_amount
		FROM `tabMPIT Budget` b
		JOIN `tabMPIT Budget Line` bl ON bl.parent = b.name
		WHERE {where}
		GROUP BY b.name, b.year, bl.category, bl.vendor
		""",
		params,
		as_dict=True,
	)

	amend_rows = frappe.db.sql(
		"""
		SELECT
			ba.budget AS budget,
			b.year AS year,
			al.category AS category,
			al.vendor AS vendor,
			SUM(al.delta_amount) AS amendment_delta
		FROM `tabMPIT Budget Amendment` ba
		JOIN `tabMPIT Budget` b ON b.name = ba.budget
		JOIN `tabMPIT Amendment Line` al ON al.parent = ba.name
		WHERE ba.docstatus = 1
		GROUP BY ba.budget, b.year, al.category, al.vendor
		""",
		as_dict=True,
	)

	actual_rows = frappe.db.sql(
		"""
		SELECT year, category, SUM(amount) AS actual_amount
		FROM `tabMPIT Actual Entry`
		GROUP BY year, category
		""",
		as_dict=True,
	)

	base_map = {(r["budget"], r["category"], r.get("vendor")): r for r in base_rows}
	amend_map = {(r["budget"], r["category"], r.get("vendor")): r for r in amend_rows}
	actual_map = {(r["year"], r["category"]): r["actual_amount"] for r in actual_rows}

	keys = set(base_map.keys()) | set(amend_map.keys())
	result: list[dict] = []
	for key in sorted(keys):
		budget, category, vendor = key
		base = base_map.get(key, {})
		amend = amend_map.get(key, {})
		year = base.get("year") or amend.get("year")
		baseline_amount = float(base.get("baseline_amount") or 0)
		amendment_delta = float(amend.get("amendment_delta") or 0)
		current_budget = baseline_amount + amendment_delta
		actual_amount = float(actual_map.get((year, category), 0))
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
