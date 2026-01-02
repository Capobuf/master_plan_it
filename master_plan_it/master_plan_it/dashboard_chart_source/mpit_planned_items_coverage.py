"""
Dashboard Chart Source: Planned Items Coverage

Counts MPIT Planned Items by coverage state (Covered, Uncovered, Out of Horizon)
for a given year based on start_date.
"""

from __future__ import annotations

import datetime

import frappe
from frappe import _

from master_plan_it import annualization


def get_config():
	return {
		"fieldname": "year",
		"method": "master_plan_it.master_plan_it.dashboard_chart_source.mpit_planned_items_coverage.get_data",
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

	where = ["docstatus = 1"]
	params = {}
	if year:
		year_start, year_end = annualization.get_year_bounds(year)
		where.append("start_date BETWEEN %(start)s AND %(end)s")
		params.update({"start": year_start, "end": year_end})

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
		"type": "bar",
	}
