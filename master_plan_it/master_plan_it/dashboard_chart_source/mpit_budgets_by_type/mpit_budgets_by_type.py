"""
Dashboard Chart Source: Budgets by Type

Counts MPIT Budget records grouped by budget_type for a given year.
"""

from __future__ import annotations

import datetime

import frappe
from frappe import _


def get_config():
	return {
		"fieldname": "year",
		"method": "master_plan_it.master_plan_it.dashboard_chart_source.mpit_budgets_by_type.mpit_budgets_by_type.get",
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
	if isinstance(filters, list):
		filters = _normalize_dashboard_filters(filters)
	filters = frappe._dict(filters or {})
	year = _resolve_year(filters)

	where = []
	params = {}
	if year:
		where.append("year = %(year)s")
		params["year"] = year

	where_clause = " AND ".join(where) if where else "1=1"
	rows = frappe.db.sql(
		f"""
		SELECT budget_type, COUNT(*) AS total
		FROM `tabMPIT Budget`
		WHERE {where_clause}
		GROUP BY budget_type
		ORDER BY total DESC
		""",
		params,
		as_dict=True,
	)

	labels = []
	values = []
	for row in rows:
		label = row.budget_type or _("Unknown")
		labels.append(label)
		values.append(int(row.total or 0))
	
	if not labels:
		labels = [_("No Data")]
		values = [0]

	return {
		"labels": labels,
		"datasets": [{"name": _("Budgets"), "values": values}],
		"type": "pie",
		"colors": ["#5E64FF", "#7CD6FD", "#743ee2", "#ffb86c", "#ff5858"]
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
	refresh=None,
):
	# Normalizza filters (puo arrivare dict o JSON-string)
	if isinstance(filters, str):
		filters = frappe.parse_json(filters)

	filters = frappe._dict(filters or {})

	# Compatibilita: filtro UI usa cost_center singolo; i tuoi get_data usano cost_centers lista
	if filters.get("cost_center") and not filters.get("cost_centers"):
		filters.cost_centers = [filters.cost_center]

	return get_data(filters)

def _normalize_dashboard_filters(filters_list: list) -> dict:
	"""
	Dashboard Chart (backend) passes filters as a list and appends a docstatus check.
	We must convert carefully.
	Expected format in list: [doctype, fieldname, op, value, ...]
	"""
	out = {}
	for f in filters_list:
		if isinstance(f, (list, tuple)) and len(f) >= 4:
			# f[1] is fieldname, f[3] is value
			fieldname = f[1]
			value = f[3]
			if fieldname:
				out[fieldname] = value
	return out
