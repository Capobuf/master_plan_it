"""
Dashboard Chart Source: Monthly Plan vs Actual

Shows monthly Plan (Live budget) vs Actual (Verified) for a given year.
Filters:
- year (default: current year)
"""

from __future__ import annotations

import calendar
import datetime

import frappe
from frappe import _
from frappe.utils import flt, getdate


def get_config():
	return {
		"fieldname": "year",
		"method": "master_plan_it.master_plan_it.dashboard_chart_source.mpit_monthly_plan_vs_actual.mpit_monthly_plan_vs_actual.get",
		"filters": [{"fieldname": "year", "fieldtype": "Data", "label": _("Year")}],
	}


def get_data(filters=None):
	filters = frappe._dict(filters or {})
	today = datetime.date.today()
	year = str(filters.get("year") or today.year)
	year_int = int(year)
	cost_centers = filters.get("cost_centers") or None
	if cost_centers:
		cost_centers = tuple(cost_centers)
		if not cost_centers:
			return {"labels": [], "datasets": [], "type": "bar"}
	cc_clause = " AND cost_center IN %(cost_centers)s" if cost_centers else ""

	plan = [0.0] * 12
	actual = [0.0] * 12

	live_budget = frappe.db.get_value(
		"MPIT Budget",
		{"year": year, "budget_type": "Live", "docstatus": 0},
		"name",
	)
	if live_budget:
		params = {"parent": live_budget}
		if cost_centers:
			params["cost_centers"] = cost_centers
		lines = frappe.db.sql(
			f"""
			SELECT monthly_amount, period_start_date, period_end_date
			FROM `tabMPIT Budget Line`
			WHERE parent = %(parent)s{cc_clause}
			""",
			params,
			as_dict=True,
		)
		year_start = datetime.date(year_int, 1, 1)
		year_end = datetime.date(year_int, 12, 31)
		for line in lines:
			monthly = flt(line.monthly_amount or 0)
			start = getdate(line.period_start_date) if line.period_start_date else year_start
			end = getdate(line.period_end_date) if line.period_end_date else year_end
			for m in range(1, 13):
				month_start = datetime.date(year_int, m, 1)
				month_end = datetime.date(year_int, m, calendar.monthrange(year_int, m)[1])
				if start <= month_end and end >= month_start:
					plan[m - 1] += monthly

	actual_rows = frappe.db.sql(
		f"""
		SELECT MONTH(posting_date) AS m, COALESCE(SUM(amount_net), 0) AS total
		FROM `tabMPIT Actual Entry`
		WHERE year = %(year)s AND status = 'Verified'{cc_clause}
		GROUP BY MONTH(posting_date)
		""",
		{"year": year, "cost_centers": cost_centers} if cost_centers else {"year": year},
		as_dict=True,
	)
	for row in actual_rows:
		m = int(row.m or 0)
		if 1 <= m <= 12:
			actual[m - 1] += flt(row.total)

	labels = [calendar.month_abbr[i] for i in range(1, 13)]

	return {
		"labels": labels,
		"datasets": [
			{"name": _("Plan (Live)"), "values": [flt(v, 2) for v in plan]},
			{"name": _("Actual"), "values": [flt(v, 2) for v in actual]},
		],
		"type": "bar",
		"colors": ["#5E64FF", "#FF5858"],
		"barOptions": {"stacked": False},
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
