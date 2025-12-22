from __future__ import annotations

import frappe
from frappe import _


def execute(filters=None):
	if isinstance(filters, str):
		filters = frappe.parse_json(filters)
	filters = frappe._dict(filters or {})
	rows = _get_data(filters)

	columns = [
		_("Project") + ":Link/MPIT Project:200",
		_("Status") + "::120",
		_("Year") + ":Link/MPIT Year:80",
		_("Planned Amount") + ":Currency:140",
		_("Actual Amount") + ":Currency:140",
		_("Variance (Actual - Planned)") + ":Currency:170",
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

	actuals = frappe.db.sql(
		"""
		SELECT project, year, SUM(COALESCE(amount_net, amount)) AS actual_amount
		FROM `tabMPIT Actual Entry`
		WHERE project IS NOT NULL
		GROUP BY project, year
		""",
		as_dict=True,
	)

	actual_map = {(r["project"], r["year"]): r["actual_amount"] for r in actuals}

	rows: list[dict] = []
	for r in allocations:
		actual_amount = float(actual_map.get((r["project"], r["year"]), 0))
		variance = actual_amount - float(r.get("planned_amount") or 0)
		rows.append({
			"project": r["project"],
			"status": r["status"],
			"year": r["year"],
			"planned_amount": r.get("planned_amount") or 0,
			"actual_amount": actual_amount,
			"variance": variance,
		})

	return rows


def _build_chart(rows: list[dict]) -> dict | None:
	if not rows:
		return None

	planned_totals = {}
	actual_totals = {}
	for r in rows:
		planned_totals[r["project"]] = planned_totals.get(r["project"], 0) + float(r.get("planned_amount") or 0)
		actual_totals[r["project"]] = actual_totals.get(r["project"], 0) + float(r.get("actual_amount") or 0)

	labels = sorted(planned_totals.keys())
	return {
		"data": {
			"labels": labels,
			"datasets": [
				{"name": _("Planned"), "values": [planned_totals.get(p, 0) for p in labels]},
				{"name": _("Actual"), "values": [actual_totals.get(p, 0) for p in labels]},
			],
		},
		"type": "bar",
		"axis_options": {"x_axis_mode": "tick", "y_axis_mode": "tick"},
	}
