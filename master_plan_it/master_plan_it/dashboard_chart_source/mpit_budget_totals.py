"""
Dashboard Chart Source: Budget Totals (Plan vs Cap vs Actual)

Aggregates, for a given year (default: current MPIT Year or calendar year), the key budget totals:
- Plan (Live, draft state)
- Snapshot (APP, allowance-only)
- Addendum total
- Cap (Snapshot + Addendum)
- Actual (Verified)
- Remaining vs Over Cap

Filters:
- year (optional, defaults to the year covering today)
"""

from __future__ import annotations

import datetime

import frappe
from frappe import _
from frappe.utils import flt


def get_config():
	return {
		"fieldname": "year",
		"method": "master_plan_it.master_plan_it.dashboard_chart_source.mpit_budget_totals.get_data",
		"filters": [{"fieldname": "year", "fieldtype": "Data", "label": _("Year")}],
	}


def _resolve_year(filters) -> str:
	"""Pick explicit year or the MPIT Year covering today (fallback: calendar year)."""
	today = datetime.date.today()
	if filters and filters.get("year"):
		return str(filters.get("year"))

	year_from_mpit = frappe.db.get_value(
		"MPIT Year",
		{"start_date": ["<=", today], "end_date": [">=", today]},
		"name",
	)
	return year_from_mpit or str(today.year)


def _sum(sql: str, params: dict) -> float:
	res = frappe.db.sql(sql, params)
	return flt(res[0][0]) if res else 0.0


def _get_live_total(year: str) -> float:
	budget = frappe.db.get_value(
		"MPIT Budget",
		{"year": year, "budget_type": "Live", "docstatus": 0},
		"name",
	)
	if not budget:
		return 0.0

	return _sum(
		"select coalesce(sum(annual_net), 0) from `tabMPIT Budget Line` where parent = %(parent)s",
		{"parent": budget},
	)


def _get_snapshot_total(year: str) -> float:
	budget = frappe.db.get_value(
		"MPIT Budget",
		{"year": year, "budget_type": "Snapshot", "docstatus": 1},
		"name",
		order_by="modified desc",
	)
	if not budget:
		return 0.0

	return _sum(
		"""
		select coalesce(sum(annual_net), 0)
		from `tabMPIT Budget Line`
		where parent = %(parent)s and line_kind = 'Allowance'
		""",
		{"parent": budget},
	)


def _get_addendum_total(year: str) -> float:
	return _sum(
		"select coalesce(sum(delta_amount), 0) from `tabMPIT Budget Addendum` where year = %(year)s and docstatus = 1",
		{"year": year},
	)


def _get_actual_total(year: str) -> float:
	return _sum(
		"""
		select coalesce(sum(amount_net), 0)
		from `tabMPIT Actual Entry`
		where year = %(year)s and status = 'Verified'
		""",
		{"year": year},
	)


def get_data(filters=None):
	filters = frappe._dict(filters or {})
	year = _resolve_year(filters)

	plan_total = flt(_get_live_total(year), 2)
	snapshot_total = flt(_get_snapshot_total(year), 2)
	addendum_total = flt(_get_addendum_total(year), 2)
	cap_total = flt(snapshot_total + addendum_total, 2)
	actual_total = flt(_get_actual_total(year), 2)
	remaining = flt(max(cap_total - actual_total, 0), 2)
	over_cap = flt(max(actual_total - cap_total, 0), 2)

	labels = [
		_("Plan (Live)"),
		_("Snapshot (APP)"),
		_("Addendum"),
		_("Cap"),
		_("Actual"),
		_("Remaining"),
		_("Over Cap"),
	]

	return {
		"labels": labels,
		"datasets": [
			{
				"name": _("Budget Totals"),
				"values": [
					plan_total,
					snapshot_total,
					addendum_total,
					cap_total,
					actual_total,
					remaining,
					over_cap,
				],
			}
		],
		"type": "bar",
	}
