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
		"method": "master_plan_it.master_plan_it.dashboard_chart_source.mpit_cap_vs_actual_by_cost_center.mpit_cap_vs_actual_by_cost_center.get",
		"filters": [{"fieldname": "year", "fieldtype": "Data", "label": _("Year")}],
	}


def get_data(filters=None):
	filters = frappe._dict(filters or {})
	today = datetime.date.today()
	year = str(filters.get("year") or today.year)
	top_n = cint(filters.get("top_n") or 10)
	cost_centers = filters.get("cost_centers") or None
	if cost_centers:
		cost_centers = tuple(cost_centers)
		if not cost_centers:
			return {"labels": [], "datasets": [], "type": "bar"}
	cc_clause = " AND cost_center IN %(cost_centers)s" if cost_centers else ""

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
		params = {"parent": live_budget}
		if cost_centers:
			params["cost_centers"] = cost_centers
		for row in frappe.db.sql(
			f"""
			SELECT cost_center, COALESCE(SUM(annual_net), 0) AS total
			FROM `tabMPIT Budget Line`
			WHERE parent = %(parent)s{cc_clause}
			GROUP BY cost_center
			""",
			params,
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
		params = {"parent": snapshot_budget}
		if cost_centers:
			params["cost_centers"] = cost_centers
		for row in frappe.db.sql(
			f"""
			SELECT cost_center, COALESCE(SUM(annual_net), 0) AS total
			FROM `tabMPIT Budget Line`
			WHERE parent = %(parent)s AND line_kind = 'Allowance'{cc_clause}
			GROUP BY cost_center
			""",
			params,
			as_dict=True,
		):
			if row.cost_center:
				cap_map[row.cost_center] = flt(row.total)

	for row in frappe.db.sql(
		f"""
		SELECT cost_center, COALESCE(SUM(delta_amount), 0) AS total
		FROM `tabMPIT Budget Addendum`
		WHERE year = %(year)s AND docstatus = 1{cc_clause}
		GROUP BY cost_center
		""",
		{"year": year, "cost_centers": cost_centers} if cost_centers else {"year": year},
		as_dict=True,
	):
		if row.cost_center:
			cap_map[row.cost_center] = cap_map.get(row.cost_center, 0) + flt(row.total)

	for row in frappe.db.sql(
		f"""
		SELECT cost_center, COALESCE(SUM(amount_net), 0) AS total
		FROM `tabMPIT Actual Entry`
		WHERE year = %(year)s AND status = 'Verified' AND cost_center IS NOT NULL{cc_clause}
		GROUP BY cost_center
		""",
		{"year": year, "cost_centers": cost_centers} if cost_centers else {"year": year},
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
