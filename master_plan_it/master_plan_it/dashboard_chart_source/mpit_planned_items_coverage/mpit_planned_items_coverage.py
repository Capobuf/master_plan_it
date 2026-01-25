"""
Dashboard Chart Source: Planned Items Coverage

Counts MPIT Planned Items by coverage state (Covered, Uncovered, Out of Horizon)
for a given year based on date-range overlap.
"""

from __future__ import annotations

import datetime

import frappe
from frappe import _

from master_plan_it import annualization


def get_config():
	return {
		"fieldname": "year",
		"method": "master_plan_it.master_plan_it.dashboard_chart_source.mpit_planned_items_coverage.mpit_planned_items_coverage.get",
		"filters": [{"fieldname": "year", "fieldtype": "Data", "label": _("Year")}],
	}


def _resolve_year(filters) -> str | None:
	if filters and filters.get("year"):
		return str(filters.get("year"))

	today = datetime.date.today()
	year_name = frappe.db.get_value(
		"MPIT Year",
		{"start_date": ["<=", today], "end_date": [">=", today]},
		"name",
	)
	if year_name:
		return year_name

	return frappe.db.get_value("MPIT Year", {}, "name", order_by="year desc")


def get_data(filters=None):
	filters = frappe._dict(filters or {})
	year = _resolve_year(filters)
	cost_centers = filters.get("cost_centers") or None
	if cost_centers:
		cost_centers = tuple(cost_centers)
		if not cost_centers:
			return {"labels": [], "datasets": [], "type": "pie"}

	where = ["workflow_state = 'Submitted'"]
	params = {}
	if year:
		year_start, year_end = annualization.get_year_bounds(year)
		where.append("start_date <= %(end)s")
		where.append("end_date >= %(start)s")
		params.update({"start": year_start, "end": year_end})
	if cost_centers:
		projects = frappe.get_all(
			"MPIT Project",
			filters={"cost_center": ["in", cost_centers]},
			pluck="name",
			limit=None,
		)
		if not projects:
			return {
				"labels": [_("Covered"), _("Uncovered"), _("Out of Horizon")],
				"datasets": [{"name": _("Planned Items"), "values": [0, 0, 0]}],
				"type": "pie",
			}
		where.append("project IN %(projects)s")
		params["projects"] = projects

	rows = frappe.db.sql(
		f"""
		SELECT is_covered, out_of_horizon
		FROM `tabMPIT Planned Item`
		WHERE {" AND ".join(where)}
		""",
		params,
		as_dict=True,
	)

	counts = {
		_("Covered"): 0,
		_("Uncovered"): 0,
		_("Out of Horizon"): 0,
	}

	for row in rows:
		if row.out_of_horizon:
			counts[_("Out of Horizon")] += 1
		elif row.is_covered:
			counts[_("Covered")] += 1
		else:
			counts[_("Uncovered")] += 1

	labels = list(counts.keys())
	values = [counts[label] for label in labels]

	return {
		"labels": labels,
		"datasets": [{"name": _("Planned Items"), "values": values}],
		"type": "pie",
	}

@frappe.whitelist()
def get(
	chart_name=None,
	chart=None,
	no_cache=None,
	filters=None,
	from_date=None,
	to_date=None,
	timespan=None,
	time_interval=None,
	heatmap_year=None,
):
	# Normalizza filters (puo arrivare dict o JSON-string)
	if isinstance(filters, str):
		filters = frappe.parse_json(filters)

	filters = frappe._dict(filters or {})

	# Compatibilita: filtro UI usa cost_center singolo; i tuoi get_data usano cost_centers lista
	if filters.get("cost_center") and not filters.get("cost_centers"):
		filters.cost_centers = [filters.cost_center]

	return get_data(filters)
