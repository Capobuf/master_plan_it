"""
Dashboard Chart Source: Cap vs Actual by Cost Center

Shows, per cost center and year, Plan (Live), Cap (Snapshot Allowance + Addendum) and Actual (Verified).
Filters:
- year (default: current year)
- top_n (default: 10)
"""

from __future__ import annotations

import datetime

import frappe
from frappe import _
from frappe.utils import cint, flt


def get_config():
	return {
		"fieldname": "year",
		"method": "master_plan_it.master_plan_it.dashboard_chart_source.mpit_cap_vs_actual_by_cost_center.get_data",
		"filters": [{"fieldname": "year", "fieldtype": "Data", "label": _("Year")}],
	}


def get_data(filters=None):
	filters = frappe._dict(filters or {})
	today = datetime.date.today()
	year = str(filters.get("year") or today.year)
	top_n = cint(filters.get("top_n") or 10)

	labels: list[str] = []
	plan_map: dict[str, float] = {}
	cap_map: dict[str, float] = {}
	actual_map: dict[str, float] = {}

	live_budget = frappe.db.get_value(
		"MPIT Budget",
		{"year": year, "budget_type": "Live", "docstatus": 0},
		"name",
	)
	if live_budget:
		for row in frappe.db.sql(
			"""
			SELECT cost_center, COALESCE(SUM(annual_net), 0) AS total
			FROM `tabMPIT Budget Line`
			WHERE parent = %(parent)s
			GROUP BY cost_center
			""",
			{"parent": live_budget},
			as_dict=True,
		):
			if row.cost_center:
				plan_map[row.cost_center] = flt(row.total)

	snapshot_budget = frappe.db.get_value(
		"MPIT Budget",
		{"year": year, "budget_type": "Snapshot", "docstatus": 1},
		"name",
		order_by="modified desc",
	)
	if snapshot_budget:
		for row in frappe.db.sql(
			"""
			SELECT cost_center, COALESCE(SUM(annual_net), 0) AS total
			FROM `tabMPIT Budget Line`
			WHERE parent = %(parent)s AND line_kind = 'Allowance'
			GROUP BY cost_center
			""",
			{"parent": snapshot_budget},
			as_dict=True,
		):
			if row.cost_center:
				cap_map[row.cost_center] = flt(row.total)

	for row in frappe.db.sql(
		"""
		SELECT cost_center, COALESCE(SUM(delta_amount), 0) AS total
		FROM `tabMPIT Budget Addendum`
		WHERE year = %(year)s AND docstatus = 1
		GROUP BY cost_center
		""",
		{"year": year},
		as_dict=True,
	):
		if row.cost_center:
			cap_map[row.cost_center] = cap_map.get(row.cost_center, 0) + flt(row.total)

	for row in frappe.db.sql(
		"""
		SELECT cost_center, COALESCE(SUM(amount_net), 0) AS total
		FROM `tabMPIT Actual Entry`
		WHERE year = %(year)s AND status = 'Verified' AND cost_center IS NOT NULL
		GROUP BY cost_center
		""",
		{"year": year},
		as_dict=True,
	):
		if row.cost_center:
			actual_map[row.cost_center] = flt(row.total)

	# Assemble and sort by Cap desc (fallback plan/actual)
	all_cc = set(plan_map) | set(cap_map) | set(actual_map)
	sorted_cc = sorted(
		all_cc,
		key=lambda cc: cap_map.get(cc, 0) or plan_map.get(cc, 0) or actual_map.get(cc, 0),
		reverse=True,
	)
	if top_n > 0:
		sorted_cc = sorted_cc[:top_n]

	plan_vals = []
	cap_vals = []
	actual_vals = []
	for cc in sorted_cc:
		labels.append(cc)
		plan_vals.append(flt(plan_map.get(cc, 0), 2))
		cap_vals.append(flt(cap_map.get(cc, 0), 2))
		actual_vals.append(flt(actual_map.get(cc, 0), 2))

	return {
		"labels": labels,
		"datasets": [
			{"name": _("Cap"), "values": cap_vals},
			{"name": _("Actual"), "values": actual_vals},
			{"name": _("Plan (Live)"), "values": plan_vals},
		],
		"type": "bar",
	}
