"""
Dashboard Chart Source: Budgets by Type

Counts MPIT Budget records grouped by budget_type for a given year.
"""

from __future__ import annotations

import datetime

import frappe
from frappe import _
from master_plan_it.master_plan_it.utils.dashboard_utils import normalize_dashboard_filters


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
	filters = normalize_dashboard_filters(filters)
	year = _resolve_year(filters)

	# Build ORM filters for MPIT Budget
	orm_filters = {}
	if year:
		orm_filters["year"] = year

	# Support cost_center filtering if provided by dashboard
	if filters.get("cost_center"):
		orm_filters["cost_center"] = filters.get("cost_center")

	# Fetch data using ORM Group By
	data = frappe.db.get_all(
		"MPIT Budget",
		filters=orm_filters,
		fields=["budget_type", "count(name) as total"],
		group_by="budget_type",
		order_by="total desc",
	)

	labels = []
	values = []
	for row in data:
		label = row.budget_type or _("Unknown")
		labels.append(label)
		val = int(row.total or 0)
		values.append(val)

	# Safety check: Pie/Donut charts crash if all values are 0 or empty
	if not labels or sum(values) == 0:
		labels = [_("No Data")]
		values = [1]

	return {
		"labels": labels,
		"datasets": [{"name": _("Budgets"), "values": values}],
		"type": "pie",
		# Colors removed to use Frappe defaults
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
	return get_data(filters)
