"""
Dashboard Chart Source: Actual Entries by Status

Counts MPIT Actual Entry records grouped by status for a given year.
"""

from __future__ import annotations

import datetime

import frappe
from frappe import _


def get_config():
	return {
		"fieldname": "year",
		"method": "master_plan_it.master_plan_it.dashboard_chart_source.mpit_actual_entries_by_status.mpit_actual_entries_by_status.get",
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
			return {"labels": [], "datasets": [], "type": "percentage"}

	where = []
	params = {}
	if year:
		where.append("year = %(year)s")
		params["year"] = year
	if cost_centers:
		where.append("cost_center IN %(cost_centers)s")
		params["cost_centers"] = cost_centers

	where_clause = " AND ".join(where) if where else "1=1"
	rows = frappe.db.sql(
		f"""
		SELECT status, COUNT(*) AS total
		FROM `tabMPIT Actual Entry`
		WHERE {where_clause}
		GROUP BY status
		ORDER BY total DESC
		""",
		params,
		as_dict=True,
	)

	labels = []
	values = []
	for row in rows:
		label = row.status or _("Unknown")
		labels.append(label)
		values.append(int(row.total or 0))

	return {
		"labels": labels,
		"datasets": [{"name": _("Actual Entries"), "values": values}],
		"type": "percentage",
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
