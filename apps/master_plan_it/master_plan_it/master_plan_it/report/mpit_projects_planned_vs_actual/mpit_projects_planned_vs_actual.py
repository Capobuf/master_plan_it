from __future__ import annotations

import frappe
from frappe import _


def execute(filters=None):
	if isinstance(filters, str):
		filters = frappe.parse_json(filters)
	filters = frappe._dict(filters or {})
	rows = _get_data(filters)

	columns = [
		{"label": _("Project"), "fieldname": "project", "fieldtype": "Link", "options": "MPIT Project", "width": 200},
		{"label": _("Status"), "fieldname": "status", "fieldtype": "Data", "width": 120},
		{"label": _("Year"), "fieldname": "year", "fieldtype": "Link", "options": "MPIT Year", "width": 80},
		{"label": _("Planned (Net)"), "fieldname": "planned_amount", "fieldtype": "Currency", "width": 140},
		{"label": _("Quoted (Net)"), "fieldname": "quoted_amount", "fieldtype": "Currency", "width": 140},
		{"label": _("Expected (Net)"), "fieldname": "expected_amount", "fieldtype": "Currency", "width": 140},
		{"label": _("Actual (Net)"), "fieldname": "actual_amount", "fieldtype": "Currency", "width": 140},
		{"label": _("Variance vs Expected"), "fieldname": "variance_expected", "fieldtype": "Currency", "width": 170},
		{"label": _("Variance vs Planned"), "fieldname": "variance_planned", "fieldtype": "Currency", "width": 170},
	]

	chart = _build_chart(rows)

	return columns, rows, None, chart


def _get_data(filters) -> list[dict]:
	params = {}
	conditions = ["1=1"]
	if filters.get("project"):
		conditions.append("p.name = %(project)s")
		params["project"] = filters.project
	if filters.get("year"):
		conditions.append("alloc.year = %(year)s")
		params["year"] = filters.year

	where = " AND ".join(conditions)

	allocations = frappe.db.sql(
		f"""
		SELECT
			p.name AS project,
			p.status AS status,
			alloc.year AS year,
			SUM(COALESCE(alloc.planned_amount_net, alloc.planned_amount)) AS planned_amount
		FROM `tabMPIT Project` p
		JOIN `tabMPIT Project Allocation` alloc ON alloc.parent = p.name
		WHERE {where}
		GROUP BY p.name, p.status, alloc.year
		""",
		params,
		as_dict=True,
	)

	quotes = frappe.db.sql(
		"""
		SELECT parent AS project, SUM(COALESCE(amount_net, amount)) AS quoted_amount
		FROM `tabMPIT Project Quote`
		GROUP BY parent
		""",
		as_dict=True,
	)

	actuals = frappe.db.sql(
		"""
		SELECT project, year, SUM(COALESCE(amount_net, amount)) AS actual_amount
		FROM `tabMPIT Actual Entry`
		WHERE project IS NOT NULL
		GROUP BY project, year
		""",
		as_dict=True,
	)

	quotes_map = {r["project"]: r.get("quoted_amount") or 0 for r in quotes}
	actual_map = {(r["project"], r["year"]): r["actual_amount"] for r in actuals}

	rows: list[dict] = []
	for r in allocations:
		actual_amount = float(actual_map.get((r["project"], r["year"]), 0))
		planned = float(r.get("planned_amount") or 0)
		quoted = float(quotes_map.get(r["project"], 0) or 0)
		expected = quoted if quoted > 0 else planned
		variance_expected = actual_amount - expected
		variance_planned = actual_amount - planned
		rows.append({
			"project": r["project"],
			"status": r["status"],
			"year": r["year"],
			"planned_amount": planned,
			"quoted_amount": quoted,
			"expected_amount": expected,
			"actual_amount": actual_amount,
			"variance_expected": variance_expected,
			"variance_planned": variance_planned,
		})

	return rows


def _build_chart(rows: list[dict]) -> dict | None:
	if not rows:
		return None

	expected_totals = {}
	actual_totals = {}
	for r in rows:
		expected_totals[r["project"]] = expected_totals.get(r["project"], 0) + float(r.get("expected_amount") or 0)
		actual_totals[r["project"]] = actual_totals.get(r["project"], 0) + float(r.get("actual_amount") or 0)

	labels = sorted(expected_totals.keys())
	return {
		"data": {
			"labels": labels,
			"datasets": [
				{"name": _("Expected"), "values": [expected_totals.get(p, 0) for p in labels]},
				{"name": _("Actual"), "values": [actual_totals.get(p, 0) for p in labels]},
			],
		},
		"type": "bar",
		"axis_options": {"x_axis_mode": "tick", "y_axis_mode": "tick"},
	}
