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
		"method": "master_plan_it.master_plan_it.dashboard_chart_source.mpit_monthly_plan_vs_actual.get_data",
		"filters": [{"fieldname": "year", "fieldtype": "Data", "label": _("Year")}],
	}


def get_data(filters=None):
	filters = frappe._dict(filters or {})
	today = datetime.date.today()
	year = str(filters.get("year") or today.year)
	year_int = int(year)

	plan = [0.0] * 12
	actual = [0.0] * 12

	live_budget = frappe.db.get_value(
		"MPIT Budget",
		{"year": year, "budget_type": "Live", "docstatus": 0},
		"name",
	)
	if live_budget:
		lines = frappe.db.sql(
			"""
			SELECT monthly_amount, period_start_date, period_end_date
			FROM `tabMPIT Budget Line`
			WHERE parent = %(parent)s
			""",
			{"parent": live_budget},
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
		"""
		SELECT MONTH(posting_date) AS m, COALESCE(SUM(amount_net), 0) AS total
		FROM `tabMPIT Actual Entry`
		WHERE year = %(year)s AND status = 'Verified'
		GROUP BY MONTH(posting_date)
		""",
		{"year": year},
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
