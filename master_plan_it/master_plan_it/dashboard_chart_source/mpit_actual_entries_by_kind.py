"""
Dashboard Chart Source: Actual Entries by Kind

Counts MPIT Actual Entry records grouped by entry_kind for a given year.
"""

from __future__ import annotations

import datetime

import frappe
from frappe import _


def get_config():
	return {
		"fieldname": "year",
		"method": "master_plan_it.master_plan_it.dashboard_chart_source.mpit_actual_entries_by_kind.get_data",
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

	where = []
	params = {}
	if year:
		where.append("year = %(year)s")
		params["year"] = year

	where_clause = " AND ".join(where) if where else "1=1"
	rows = frappe.db.sql(
		f"""
		SELECT entry_kind, COUNT(*) AS total
		FROM `tabMPIT Actual Entry`
		WHERE {where_clause}
		GROUP BY entry_kind
		ORDER BY total DESC
		""",
		params,
		as_dict=True,
	)

	labels = []
	values = []
	for row in rows:
		label = row.entry_kind or _("Unknown")
		labels.append(label)
		values.append(int(row.total or 0))

	return {
		"labels": labels,
		"datasets": [{"name": _("Actual Entries"), "values": values}],
		"type": "bar",
	}
