"""
FILE: master_plan_it/report/mpit_monthly_plan/mpit_monthly_plan.py
SCOPO: Report mensile che rispetta spend_date per Planned Items.
INPUT: Filtri (year obbligatorio, cost_center opzionale).
OUTPUT: Righe aggregate per Cost Center con colonne mensili (gen-dic), totale annuale.
"""

from __future__ import annotations

import calendar
from datetime import date

import frappe
from frappe import _
from frappe.utils import flt, getdate

from master_plan_it import annualization
from master_plan_it.master_plan_it.utils.dashboard_utils import normalize_dashboard_filters


def execute(filters=None):
	filters = normalize_dashboard_filters(filters)
	filters = frappe._dict(filters or {})

	filters.year = _resolve_year(filters)
	if not filters.year:
		frappe.throw(_("No MPIT Year found. Please create one or set the Year filter."))

	columns = _get_columns()
	data = _get_data(filters)
	chart = _build_chart(data)

	return columns, data, None, chart


def _resolve_year(filters) -> str | None:
	"""Resolve year from filters or the MPIT Year covering today (fallback: latest year)."""
	if filters.get("year"):
		return str(filters.year)

	today = date.today()
	year_name = frappe.db.get_value(
		"MPIT Year",
		{"start_date": ["<=", today], "end_date": [">=", today]},
		"name",
	)
	if year_name:
		return year_name

	return frappe.db.get_value("MPIT Year", {}, "name", order_by="year desc")


def _get_columns() -> list[dict]:
	cols = [
		{"label": _("Cost Center"), "fieldname": "cost_center", "fieldtype": "Link", "options": "MPIT Cost Center", "width": 180},
		{"label": _("Source"), "fieldname": "source_type", "fieldtype": "Data", "width": 100},
	]

	# Add monthly columns
	month_names = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"]
	for i, name in enumerate(month_names, 1):
		cols.append({
			"label": _(name),
			"fieldname": f"month_{i}",
			"fieldtype": "Currency",
			"width": 100,
		})

	cols.append({
		"label": _("Total"),
		"fieldname": "total",
		"fieldtype": "Currency",
		"width": 120,
	})

	return cols


def _get_data(filters) -> list[dict]:
	year = filters.year
	cost_center_filter = filters.get("cost_center")

	year_start, year_end = annualization.get_year_bounds(year)

	# Get contract lines monthly distribution
	contract_monthly = _get_contract_monthly(year, year_start, year_end, cost_center_filter)

	# Get planned item lines monthly distribution (respecting spend_date/distribution)
	planned_monthly = _get_planned_item_monthly(year, year_start, year_end, cost_center_filter)

	# Merge by cost center
	all_ccs = set(contract_monthly.keys()) | set(planned_monthly.keys())

	rows = []
	for cc in sorted(all_ccs):
		contract_data = contract_monthly.get(cc, {})
		planned_data = planned_monthly.get(cc, {})

		# Add contract row for this CC
		if contract_data:
			row = {
				"cost_center": cc,
				"source_type": "Contract",
				"total": 0,
			}
			for m in range(1, 13):
				amount = flt(contract_data.get(m, 0), 2)
				row[f"month_{m}"] = amount
				row["total"] += amount
			row["total"] = flt(row["total"], 2)
			rows.append(row)

		# Add planned item row for this CC
		if planned_data:
			row = {
				"cost_center": cc,
				"source_type": "Planned Item",
				"total": 0,
			}
			for m in range(1, 13):
				amount = flt(planned_data.get(m, 0), 2)
				row[f"month_{m}"] = amount
				row["total"] += amount
			row["total"] = flt(row["total"], 2)
			rows.append(row)

	return rows


def _get_contract_monthly(year: str, year_start: date, year_end: date, cost_center_filter: str | None) -> dict[str, dict[int, float]]:
	"""Get monthly amounts per cost center from Live budget contract lines."""
	live_budget = frappe.db.get_value(
		"MPIT Budget",
		filters={"year": year, "budget_type": "Live", "docstatus": 0},
		fieldname="name",
	)
	if not live_budget:
		return {}

	filters = {"parent": live_budget, "line_kind": "Contract"}
	if cost_center_filter:
		filters["cost_center"] = cost_center_filter

	lines = frappe.get_all(
		"MPIT Budget Line",
		filters=filters,
		fields=["cost_center", "monthly_amount", "period_start_date", "period_end_date"],
	)

	result: dict[str, dict[int, float]] = {}

	for line in lines:
		cc = line.cost_center
		if not cc:
			continue

		if cc not in result:
			result[cc] = {m: 0 for m in range(1, 13)}

		# Distribute monthly_amount across overlap months
		start = getdate(line.period_start_date) if line.period_start_date else year_start
		end = getdate(line.period_end_date) if line.period_end_date else year_end
		monthly = flt(line.monthly_amount or 0)

		for month in range(1, 13):
			month_start = date(year_start.year, month, 1)
			month_end = date(year_start.year, month, calendar.monthrange(year_start.year, month)[1])

			# Check if month overlaps with line period
			if start <= month_end and end >= month_start:
				result[cc][month] += monthly

	return result


def _get_planned_item_monthly(year: str, year_start: date, year_end: date, cost_center_filter: str | None) -> dict[str, dict[int, float]]:
	"""Get monthly amounts per cost center from Planned Items respecting spend_date/distribution and overlap."""
	items = frappe.get_all(
		"MPIT Planned Item",
		filters={"workflow_state": "Submitted", "is_covered": 0, "out_of_horizon": 0},
		fields=[
			"name",
			"project",
			"amount",
			"start_date",
			"end_date",
			"spend_date",
		],
	)

	if not items:
		return {}

	# Get project cost centers
	project_names = list(set(i.project for i in items if i.project))
	project_ccs = {}
	if project_names:
		projects = frappe.get_all(
			"MPIT Project",
			filters={"name": ["in", project_names], "workflow_state": "Approved"},
			fields=["name", "cost_center"],
		)
		project_ccs = {p.name: p.cost_center for p in projects}

	result: dict[str, dict[int, float]] = {}

	for item in items:
		cc = project_ccs.get(item.project)
		if not cc:
			continue

		if cost_center_filter and cc != cost_center_filter:
			continue

		if cc not in result:
			result[cc] = {m: 0 for m in range(1, 13)}

		amount = flt(item.amount or 0)
		if amount == 0:
			continue

		start = getdate(item.start_date) if item.start_date else year_start
		end = getdate(item.end_date) if item.end_date else year_end

		if end < year_start or start > year_end:
			continue

		# Case 1: spend_date specified - all amount goes to that month (if in year)
		if item.spend_date:
			spend = getdate(item.spend_date)
			if year_start <= spend <= year_end:
				result[cc][spend.month] += amount
			continue

		# Case 2: spread across period
		period_start = max(start, year_start)
		period_end = min(end, year_end)

		overlap_months = _get_overlap_month_list(period_start, period_end, year_start)
		if not overlap_months:
			continue

		total_months = _get_total_months(start, end)
		if total_months <= 0:
			continue

		monthly_amount = amount / total_months
		for m in overlap_months:
			result[cc][m] += monthly_amount

	return result


def _get_overlap_month_list(period_start: date, period_end: date, year_start: date) -> list[int]:
	"""Return list of month numbers (1-12) that overlap with the period."""
	months = []
	for month in range(1, 13):
		month_start = date(year_start.year, month, 1)
		month_end = date(year_start.year, month, calendar.monthrange(year_start.year, month)[1])

		if period_start <= month_end and period_end >= month_start:
			months.append(month)

	return months


def _get_total_months(start: date, end: date) -> int:
	"""Return total months in the item period (inclusive)."""
	return (end.year - start.year) * 12 + end.month - start.month + 1


def _build_chart(data: list[dict]) -> dict:
	"""Build stacked bar chart showing monthly amounts by source type."""
	if not data:
		return {}

	month_names = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"]

	# Aggregate by source type
	contract_totals = [0.0] * 12
	planned_totals = [0.0] * 12

	for row in data:
		for m in range(1, 13):
			amount = flt(row.get(f"month_{m}", 0))
			if row.get("source_type") == "Contract":
				contract_totals[m - 1] += amount
			else:
				planned_totals[m - 1] += amount

	return {
		"data": {
			"labels": month_names,
			"datasets": [
				{"name": _("Contracts"), "values": contract_totals},
				{"name": _("Planned Items"), "values": planned_totals},
			],
		},
		"type": "bar",
		"barOptions": {"stacked": True},
		"fieldtype": "Currency",
	}


