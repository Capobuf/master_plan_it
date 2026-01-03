from __future__ import annotations

import datetime
from typing import Iterable

import frappe
from frappe import _
from frappe.modules import scrub
from frappe.utils import cint, flt, getdate, nowdate

from master_plan_it import annualization
# Chart sources are resolved dynamically by name via Dashboard Chart Source records.
# Do NOT import chart source modules directly here: packages with the same name as a module
# may shadow the module and cause AttributeError: module '...mpit_planned_items_coverage' has no attribute 'get_data'.
# Use `_call_dashboard_chart_source` to resolve and call the method declared in the Dashboard Chart Source record.
# (The actual chart source implementations still live under dashboard_chart_source/.)
from master_plan_it.master_plan_it.report.mpit_renewals_window import (
	mpit_renewals_window as rpt_renewals,
)


# ─────────────────────────────────────────────────────────────────────────────
# Helpers
# ─────────────────────────────────────────────────────────────────────────────


def _parse_filters(filters_json) -> frappe._dict:
	filters = filters_json
	if isinstance(filters_json, str):
		filters = frappe.parse_json(filters_json)
	filters = frappe._dict(filters or {})

	if not filters.get("year"):
		frappe.throw(_("Year is required"))

	filters.include_children = cint(filters.get("include_children"))
	filters.cost_centers = _resolve_cost_centers(filters.get("cost_center"), filters.include_children)
	return filters


def _resolve_cost_centers(cost_center: str | None, include_children: int = 0) -> list[str] | None:
	if not cost_center:
		return None
	if not include_children:
		return [cost_center]

	row = frappe.db.get_value("MPIT Cost Center", cost_center, ["lft", "rgt"], as_dict=True)
	if not row or row.lft is None or row.rgt is None:
		frappe.throw(_("Cost Center {0} is missing tree bounds (lft/rgt).").format(cost_center))

	return frappe.db.get_all(
		"MPIT Cost Center",
		filters={"lft": [">=", row.lft], "rgt": ["<=", row.rgt]},
		pluck="name",
	)


def _get_year_bounds(year: str) -> tuple[datetime.date, datetime.date]:
	start, end = annualization.get_year_bounds(year)
	return getdate(start), getdate(end)


def _get_plan_total(year: str, cost_centers: Iterable[str] | None) -> float:
	live_budget = frappe.db.get_value(
		"MPIT Budget",
		{"year": year, "budget_type": "Live", "docstatus": 0},
		"name",
	)
	if not live_budget:
		return 0.0

	condition = "parent = %(parent)s"
	params: dict = {"parent": live_budget}
	if cost_centers:
		cc_tuple = tuple(cost_centers)
		if not cc_tuple:
			return 0.0
		condition += " AND cost_center IN %(cost_centers)s"
		params["cost_centers"] = cc_tuple

	res = frappe.db.sql(
		f"SELECT COALESCE(SUM(annual_net), 0) FROM `tabMPIT Budget Line` WHERE {condition}",
		params,
	)
	return flt(res[0][0]) if res else 0.0


def _get_snapshot_allowance(year: str, cost_centers: Iterable[str] | None) -> float:
	snapshot_budget = frappe.db.get_value(
		"MPIT Budget",
		{"year": year, "budget_type": "Snapshot", "docstatus": 1},
		"name",
		order_by="modified desc",
	)
	if not snapshot_budget:
		return 0.0

	condition = "parent = %(parent)s AND line_kind = 'Allowance'"
	params: dict = {"parent": snapshot_budget}
	if cost_centers:
		cc_tuple = tuple(cost_centers)
		if not cc_tuple:
			return 0.0
		condition += " AND cost_center IN %(cost_centers)s"
		params["cost_centers"] = cc_tuple

	res = frappe.db.sql(
		f"SELECT COALESCE(SUM(annual_net), 0) FROM `tabMPIT Budget Line` WHERE {condition}",
		params,
	)
	return flt(res[0][0]) if res else 0.0


def _get_addendum_total(year: str, cost_centers: Iterable[str] | None) -> float:
	condition = "year = %(year)s AND docstatus = 1"
	params: dict = {"year": year}
	if cost_centers:
		cc_tuple = tuple(cost_centers)
		if not cc_tuple:
			return 0.0
		condition += " AND cost_center IN %(cost_centers)s"
		params["cost_centers"] = cc_tuple

	res = frappe.db.sql(
		f"SELECT COALESCE(SUM(delta_amount), 0) FROM `tabMPIT Budget Addendum` WHERE {condition}",
		params,
	)
	return flt(res[0][0]) if res else 0.0


def _get_actual_ytd(year: str, start: datetime.date, end: datetime.date, cost_centers: Iterable[str] | None) -> float:
	where = [
		"year = %(year)s",
		"status = 'Verified'",
		"posting_date BETWEEN %(start)s AND %(end)s",
	]
	params: dict = {"year": year, "start": start, "end": end}
	if cost_centers:
		cc_tuple = tuple(cost_centers)
		if not cc_tuple:
			return 0.0
		where.append("cost_center IN %(cost_centers)s")
		params["cost_centers"] = cc_tuple

	res = frappe.db.sql(
		f"SELECT COALESCE(SUM(amount_net), 0) FROM `tabMPIT Actual Entry` WHERE {' AND '.join(where)}",
		params,
	)
	return flt(res[0][0]) if res else 0.0


def _get_actual_map(year: str, start: datetime.date, end: datetime.date, cost_centers: Iterable[str] | None) -> dict[str, float]:
	where = [
		"year = %(year)s",
		"status = 'Verified'",
		"cost_center IS NOT NULL",
		"posting_date BETWEEN %(start)s AND %(end)s",
	]
	params: dict = {"year": year, "start": start, "end": end}
	if cost_centers:
		cc_tuple = tuple(cost_centers)
		if not cc_tuple:
			return {}
		where.append("cost_center IN %(cost_centers)s")
		params["cost_centers"] = cc_tuple

	rows = frappe.db.sql(
		f"""
		SELECT cost_center, COALESCE(SUM(amount_net), 0) AS total
		FROM `tabMPIT Actual Entry`
		WHERE {" AND ".join(where)}
		GROUP BY cost_center
		""",
		params,
		as_dict=True,
	)
	return {row.cost_center: flt(row.total) for row in rows if row.cost_center}


def _get_cap_map(year: str, cost_centers: Iterable[str] | None) -> dict[str, float]:
	cap_map: dict[str, float] = {}
	cc_tuple = tuple(cost_centers) if cost_centers else None

	snapshot_budget = frappe.db.get_value(
		"MPIT Budget",
		{"year": year, "budget_type": "Snapshot", "docstatus": 1},
		"name",
		order_by="modified desc",
	)
	if snapshot_budget:
		where = ["parent = %(parent)s", "line_kind = 'Allowance'"]
		params: dict = {"parent": snapshot_budget}
		if cc_tuple:
			where.append("cost_center IN %(cost_centers)s")
			params["cost_centers"] = cc_tuple
		rows = frappe.db.sql(
			f"""
			SELECT cost_center, COALESCE(SUM(annual_net), 0) AS total
			FROM `tabMPIT Budget Line`
			WHERE {" AND ".join(where)}
			GROUP BY cost_center
			""",
			params,
			as_dict=True,
		)
		for row in rows:
			if row.cost_center:
				cap_map[row.cost_center] = flt(row.total)

	where = ["year = %(year)s", "docstatus = 1"]
	params: dict = {"year": year}
	if cc_tuple:
		where.append("cost_center IN %(cost_centers)s")
		params["cost_centers"] = cc_tuple

	for row in frappe.db.sql(
		f"""
		SELECT cost_center, COALESCE(SUM(delta_amount), 0) AS total
		FROM `tabMPIT Budget Addendum`
		WHERE {" AND ".join(where)}
		GROUP BY cost_center
		""",
		params,
		as_dict=True,
	):
		if row.cost_center:
			cap_map[row.cost_center] = cap_map.get(row.cost_center, 0) + flt(row.total)

	return cap_map


def _get_over_cap(year: str, start: datetime.date, end: datetime.date, cost_centers: Iterable[str] | None) -> tuple[int, float, dict]:
	cap_map = _get_cap_map(year, cost_centers)
	actual_map = _get_actual_map(year, start, end, cost_centers)

	over_count = 0
	over_amount = 0.0
	rows = {}

	for cc, actual in actual_map.items():
		cap = cap_map.get(cc, 0)
		if actual > cap:
			diff = flt(actual - cap, 2)
			over_count += 1
			over_amount += diff
			rows[cc] = {"cap": flt(cap, 2), "actual": flt(actual, 2), "over": diff}

	return over_count, flt(over_amount, 2), rows


def _get_renewal_window_days() -> int:
	settings = frappe.get_single("MPIT Settings")
	return cint(settings.renewal_window_days or 90)


def _get_renewals(filters: frappe._dict) -> tuple[list[dict], list[dict]]:
	days = _get_renewal_window_days()
	rfilters = frappe._dict(
		{
			"days": days,
			"include_past": 0,
			"auto_renew_only": 0,
			"allowed_cost_centers": filters.cost_centers,
		}
	)
	return rpt_renewals._get_data(rfilters)


def _call_dashboard_chart_source(source_name: str, filters: dict) -> dict:
	"""Resolve Dashboard Chart Source by name and call its `method`.
	Raises if the source or method is missing; returns a payload with `_error` only on execution failure.
	"""
	if not source_name:
		frappe.throw(_("Dashboard Chart Source name is required."))

	slug = scrub(source_name)
	method = f"master_plan_it.master_plan_it.dashboard_chart_source.{slug}.{slug}.get"

	try:
		callable_obj = frappe.get_attr(method)
	except Exception:
		frappe.throw(_("Dashboard Chart Source method not found: {0}").format(method))

	if not callable(callable_obj):
		frappe.throw(_("Dashboard Chart Source method is not callable: {0}").format(method))

	try:
		return callable_obj(filters=filters or {}) or {"labels": [], "datasets": [], "type": "bar", "_error": "Chart source returned empty result"}
	except Exception as e:
		# Fail-soft: return payload with _error and let UI show N/A for this block
		return {"labels": [], "datasets": [], "type": "bar", "_error": f"Chart source execution failed: {e}"}


def _get_coverage(filters: frappe._dict) -> tuple[float, dict]:
	data = _call_dashboard_chart_source("MPIT Planned Items Coverage", {"year": filters.year, "cost_centers": filters.cost_centers})
	if data.get("_error"):
		# fail-soft: return zero coverage and surface the error in the extra payload
		return 0.0, {"labels": [], "values": [], "_error": data.get("_error")}
	values = data.get("datasets", [{}])[0].get("values", []) if data.get("datasets") else []
	labels = data.get("labels", [])
	total = sum(values) if values else 0
	covered = 0
	for idx, label in enumerate(labels):
		if label == _("Covered"):
			covered = values[idx] if idx < len(values) else 0
			break
	coverage = flt((covered / total * 100), 2) if total else 0.0
	return coverage, {"labels": labels, "values": values}


# ─────────────────────────────────────────────────────────────────────────────
# Public endpoints
# ─────────────────────────────────────────────────────────────────────────────


@frappe.whitelist()
def get_overview(filters_json=None):
	filters = _parse_filters(filters_json)
	year_start, year_end = _get_year_bounds(filters.year)
	today = min(getdate(nowdate()), year_end)

	plan_total = flt(_get_plan_total(filters.year, filters.cost_centers), 2)
	snapshot_total = flt(_get_snapshot_allowance(filters.year, filters.cost_centers), 2)
	addendum_total = flt(_get_addendum_total(filters.year, filters.cost_centers), 2)
	cap_total = flt(snapshot_total + addendum_total, 2)
	actual_ytd = flt(_get_actual_ytd(filters.year, year_start, today, filters.cost_centers), 2)
	remaining = flt(cap_total - actual_ytd, 2)
	over_cap_count, over_cap_amount, over_cap_rows = _get_over_cap(filters.year, year_start, today, filters.cost_centers)

	renewal_rows, renewal_summary = _get_renewals(filters)
	upcoming_count = len([r for r in renewal_rows if not r.get("expired_count")])

	coverage_pct, coverage_counts = _get_coverage(filters)

	kpis = [
		{
			"label": _("Plan (Year)"),
			"value": plan_total,
			"subtitle": _("Live budget (annual_net)"),
			"route_options": {"doctype": "MPIT Budget", "filters": {"year": filters.year, "budget_type": "Live"}},
		},
		{
			"label": _("Cap (Year)"),
			"value": cap_total,
			"subtitle": _("Snapshot allowance + Addendums"),
			"route_options": {"report": "MPIT Plan vs Cap vs Actual", "filters": {"year": filters.year}},
		},
		{
			"label": _("Actual YTD"),
			"value": actual_ytd,
			"subtitle": _("Verified entries up to today"),
			"route_options": {
				"doctype": "MPIT Actual Entry",
				"filters": {"status": "Verified", "year": filters.year},
			},
		},
		{
			"label": _("Remaining"),
			"value": remaining,
			"subtitle": _("Cap - Actual YTD"),
			"route_options": {"report": "MPIT Plan vs Cap vs Actual", "filters": {"year": filters.year}},
		},
		{
			"label": _("Addendums (Year)"),
			"value": addendum_total,
			"subtitle": _("Approved addendums"),
			"route_options": {"doctype": "MPIT Budget Addendum", "filters": {"year": filters.year, "docstatus": 1}},
		},
		{
			"label": _("Over Cap"),
			"value": over_cap_amount,
			"subtitle": _("Count: {0}").format(over_cap_count),
			"route_options": {"report": "MPIT Plan vs Cap vs Actual", "filters": {"year": filters.year}},
		},
		{
			"label": _("Renewals Window"),
			"value": upcoming_count,
			"subtitle": _("Next {0} days").format(_get_renewal_window_days()),
			"route_options": {"report": "MPIT Renewals Window"},
		},
		{
			"label": _("Coverage"),
			"value": ("N/A" if coverage_counts.get("_error") else coverage_pct),
			"subtitle": _("% Covered Planned Items"),
			"route_options": {"doctype": "MPIT Planned Item", "filters": {"docstatus": 1}},
			"extra": coverage_counts,
		},
	]

	charts = {
		"monthly_plan_vs_actual": _call_dashboard_chart_source(
			"MPIT Monthly Plan vs Actual", {"year": filters.year, "cost_centers": filters.cost_centers}
		),
		"cap_vs_actual_by_cost_center": _call_dashboard_chart_source(
			"MPIT Cap vs Actual by Cost Center", {"year": filters.year, "cost_centers": filters.cost_centers, "top_n": 10}
		),
	}

	return {
		"kpis": kpis,
		"charts": charts,
		"filters": {"year": filters.year, "cost_center": filters.get("cost_center"), "include_children": filters.include_children},
		"over_cap_rows": over_cap_rows,
		"renewals": {"rows": renewal_rows, "summary": renewal_summary},
	}


@frappe.whitelist()
def get_secondary(filters_json=None):
	filters = _parse_filters(filters_json)
	common = {"year": filters.year, "cost_centers": filters.cost_centers}

	return {
		"actual_by_kind": _call_dashboard_chart_source("MPIT Actual Entries by Kind", common),
		"contracts_by_status": _call_dashboard_chart_source("MPIT Contracts by Status", {"cost_centers": filters.cost_centers}),
		"projects_by_status": _call_dashboard_chart_source("MPIT Projects by Status", {"cost_centers": filters.cost_centers}),
	}


@frappe.whitelist()
def get_worklists(filters_json=None):
	filters = _parse_filters(filters_json)
	year_start, year_end = _get_year_bounds(filters.year)
	today = min(getdate(nowdate()), year_end)

	over_cap_rows = []
	over_cap_count, over_cap_amount, over_cap_map = _get_over_cap(filters.year, year_start, today, filters.cost_centers)
	for cc, row in over_cap_map.items():
		over_cap_rows.append(
			{
				"cost_center": cc,
				"cap": row["cap"],
				"actual": row["actual"],
				"over": row["over"],
			}
		)
	over_cap_rows = sorted(over_cap_rows, key=lambda r: r["over"], reverse=True)[:10]

	renewal_rows, _ = _get_renewals(filters)
	renewal_rows = sorted(renewal_rows, key=lambda r: r.get("days_to_renewal", 9999))[:10]

	planned_rows = _get_planned_exceptions(filters, year_start, year_end)
	actual_rows = _get_latest_actuals(filters, year_start, today)

	return {
		"over_cap_cost_centers": {
			"columns": [
				{"label": frappe._("Cost Center"), "fieldname": "cost_center", "fieldtype": "Link", "options": "MPIT Cost Center"},
				{"label": frappe._("Cap"), "fieldname": "cap", "fieldtype": "Currency"},
				{"label": frappe._("Actual YTD"), "fieldname": "actual", "fieldtype": "Currency"},
				{"label": frappe._("Over"), "fieldname": "over", "fieldtype": "Currency"},
			],
			"rows": over_cap_rows,
			"route_options": {"report": "MPIT Plan vs Cap vs Actual", "filters": {"year": filters.year}},
		},
		"renewals": {
			"columns": [
				{"label": frappe._("Contract"), "fieldname": "contract", "fieldtype": "Link", "options": "MPIT Contract"},
				{"label": frappe._("Vendor"), "fieldname": "vendor", "fieldtype": "Link", "options": "MPIT Vendor"},
				{"label": frappe._("Cost Center"), "fieldname": "cost_center", "fieldtype": "Link", "options": "MPIT Cost Center"},
				{"label": frappe._("Next Renewal"), "fieldname": "next_renewal_date", "fieldtype": "Date"},
				{"label": frappe._("Days"), "fieldname": "days_to_renewal", "fieldtype": "Int"},
				{"label": frappe._("Status"), "fieldname": "status", "fieldtype": "Data"},
			],
			"rows": [
				{
					"contract": r.get("contract"),
					"vendor": r.get("vendor"),
					"cost_center": r.get("cost_center"),
					"next_renewal_date": r.get("renewal_date"),
					"days_to_renewal": r.get("days_to_renewal"),
					"status": r.get("status"),
				}
				for r in renewal_rows
			],
			"route_options": {"report": "MPIT Renewals Window"},
		},
		"planned_exceptions": {
			"columns": [
				{"label": frappe._("Project"), "fieldname": "project", "fieldtype": "Link", "options": "MPIT Project"},
				{"label": frappe._("Description"), "fieldname": "description", "fieldtype": "Data"},
				{"label": frappe._("Amount"), "fieldname": "amount", "fieldtype": "Currency"},
				{"label": frappe._("Start Date"), "fieldname": "start_date", "fieldtype": "Date"},
				{"label": frappe._("Covered"), "fieldname": "is_covered", "fieldtype": "Check"},
				{"label": frappe._("Out of Horizon"), "fieldname": "out_of_horizon", "fieldtype": "Check"},
			],
			"rows": planned_rows,
			"route_options": {"doctype": "MPIT Planned Item", "filters": {"docstatus": 1}},
		},
		"latest_actual_entries": {
			"columns": [
				{"label": frappe._("Posting Date"), "fieldname": "posting_date", "fieldtype": "Date"},
				{"label": frappe._("Description"), "fieldname": "description", "fieldtype": "Data"},
				{"label": frappe._("Cost Center"), "fieldname": "cost_center", "fieldtype": "Link", "options": "MPIT Cost Center"},
				{"label": frappe._("Amount (Net)"), "fieldname": "amount_net", "fieldtype": "Currency"},
			],
			"rows": actual_rows,
			"route_options": {"doctype": "MPIT Actual Entry", "filters": {"status": "Verified", "year": filters.year}},
		},
	}


def _get_planned_exceptions(filters: frappe._dict, year_start: datetime.date, year_end: datetime.date) -> list[dict]:
	where = [
		"pi.docstatus = 1",
		"(pi.is_covered = 0 OR pi.out_of_horizon = 1)",
	]
	params: dict = {"start": year_start, "end": year_end}

	# Align with coverage chart: overlap year bounds
	where.append("pi.start_date <= %(end)s")
	where.append("pi.end_date >= %(start)s")

	if filters.cost_centers:
		cc_tuple = tuple(filters.cost_centers)
		if not cc_tuple:
			return []
		where.append("pr.cost_center IN %(cost_centers)s")
		params["cost_centers"] = cc_tuple

	rows = frappe.db.sql(
		f"""
		SELECT
			pi.project,
			pi.description,
			pi.amount,
			pi.start_date,
			pi.is_covered,
			pi.out_of_horizon
		FROM `tabMPIT Planned Item` pi
		LEFT JOIN `tabMPIT Project` pr ON pr.name = pi.project
		WHERE {" AND ".join(where)}
		ORDER BY pi.out_of_horizon DESC, pi.start_date DESC
		LIMIT 10
		""",
		params,
		as_dict=True,
	)
	return rows or []


def _get_latest_actuals(filters: frappe._dict, start_date: datetime.date, end_date: datetime.date) -> list[dict]:
	where = [
		"status = 'Verified'",
		"posting_date BETWEEN %(start)s AND %(end)s",
	]
	params: dict = {"start": start_date, "end": end_date}

	if filters.year:
		where.append("year = %(year)s")
		params["year"] = filters.year

	if filters.cost_centers:
		cc_tuple = tuple(filters.cost_centers)
		if not cc_tuple:
			return []
		where.append("cost_center IN %(cost_centers)s")
		params["cost_centers"] = cc_tuple

	rows = frappe.db.sql(
		f"""
		SELECT posting_date, description, cost_center, amount_net
		FROM `tabMPIT Actual Entry`
		WHERE {" AND ".join(where)}
		ORDER BY posting_date DESC
		LIMIT 10
		""",
		params,
		as_dict=True,
	)
	return rows or []
