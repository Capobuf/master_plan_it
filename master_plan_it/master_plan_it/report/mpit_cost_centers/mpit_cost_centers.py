# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

"""
MPIT Cost Centers Report

Analysis of Budget vs Actuals per Cost Center for a specific Year.
Includes:
- KPI Cards (Financial Totals)
- Table (Plan, Snapshot, Addendum, Cap, Actual, Remaining)
- Charts (Budget Distribution, Cap vs Actual)
"""

from __future__ import annotations

import datetime

import frappe
from frappe import _
from frappe.utils import flt


def execute(filters=None):
	filters = frappe._dict(filters or {})
	year = _resolve_year(filters)
	
	columns = get_columns()
	data = get_data(year, filters)
	report_summary = get_report_summary(data)
	chart = get_chart(data)
	message = get_extra_charts(data)
	
	return columns, data, None, chart, report_summary, message


def _resolve_year(filters) -> str | None:
	if filters.get("year"):
		return filters.get("year")
	
	today = datetime.date.today()
	return frappe.db.get_value(
		"MPIT Year",
		{"start_date": ["<=", today], "end_date": [">=", today]},
		"name",
	)


def get_columns():
	return [
		{"label": _("Cost Center"), "fieldname": "cost_center", "fieldtype": "Link", "options": "MPIT Cost Center", "width": 200},
		{"label": _("Parent"), "fieldname": "parent_cost_center", "fieldtype": "Link", "options": "MPIT Cost Center", "width": 150},
		{"label": _("Plan (Live)"), "fieldname": "plan_live", "fieldtype": "Currency", "options": "currency", "width": 120},
		{"label": _("Snapshot"), "fieldname": "snapshot", "fieldtype": "Currency", "options": "currency", "width": 120},
		{"label": _("Addendum"), "fieldname": "addendum", "fieldtype": "Currency", "options": "currency", "width": 120},
		{"label": _("Cap"), "fieldname": "cap", "fieldtype": "Currency", "options": "currency", "width": 120},
		{"label": _("Actual"), "fieldname": "actual", "fieldtype": "Currency", "options": "currency", "width": 120},
		{"label": _("Remaining"), "fieldname": "remaining", "fieldtype": "Currency", "options": "currency", "width": 120},
		{"label": _("Over Cap"), "fieldname": "over_cap", "fieldtype": "Currency", "options": "currency", "width": 120},
	]


def get_data(year: str | None, filters: frappe._dict) -> list[dict]:
	if not year:
		return []
	
	parent_filter = filters.get("parent_mpit_cost_center")
	
	# Fetch all Cost Centers (optionally filtered by parent)
	cc_filters = {}
	if parent_filter:
		cc_filters["parent_mpit_cost_center"] = parent_filter
		
	cost_centers = frappe.get_all(
		"MPIT Cost Center",
		filters=cc_filters,
		fields=["name", "parent_mpit_cost_center"],
		order_by="name asc"
	)
	
	# Build maps for budgets and actuals
	# Reuse logic logic from mpit_overview.py but simpler since we iterate known CCs
	
	# 1. Plan (Live)
	live_budget = frappe.db.get_value("MPIT Budget", {"year": year, "budget_type": "Live", "docstatus": 0}, "name")
	plan_map = {}
	if live_budget:
		lines = frappe.db.get_all(
			"MPIT Budget Line",
			filters={"parent": live_budget},
			fields=["cost_center", "sum(annual_net) as total"],
			group_by="cost_center"
		)
		for row in lines:
			plan_map[row.cost_center] = flt(row.total)
			
	# 2. Snapshot (Latest Submitted)
	snapshot_budget = frappe.db.get_value(
		"MPIT Budget",
		{"year": year, "budget_type": "Snapshot", "docstatus": 1},
		"name",
		order_by="modified desc"
	)
	snapshot_map = {}
	if snapshot_budget:
		lines = frappe.db.get_all(
			"MPIT Budget Line",
			filters={"parent": snapshot_budget, "line_kind": "Allowance"},
			fields=["cost_center", "sum(annual_net) as total"],
			group_by="cost_center"
		)
		for row in lines:
			snapshot_map[row.cost_center] = flt(row.total)
			
	# 3. Addendums
	addendum_data = frappe.db.get_all(
		"MPIT Budget Addendum",
		filters={"year": year, "docstatus": 1},
		fields=["cost_center", "sum(delta_amount) as total"],
		group_by="cost_center"
	)
	addendum_map = {row.cost_center: flt(row.total) for row in addendum_data}
	
	# 4. Actuals
	actual_data = frappe.db.get_all(
		"MPIT Actual Entry",
		filters={"year": year, "status": "Verified"},
		fields=["cost_center", "sum(amount_net) as total"],
		group_by="cost_center"
	)
	actual_map = {row.cost_center: flt(row.total) for row in actual_data}
	
	data = []
	for cc in cost_centers:
		name = cc.name
		
		plan = flt(plan_map.get(name, 0))
		snapshot = flt(snapshot_map.get(name, 0))
		addendum = flt(addendum_map.get(name, 0))
		cap = snapshot + addendum
		actual = flt(actual_map.get(name, 0))
		remaining = max(cap - actual, 0)
		over_cap = max(actual - cap, 0)
		
		# Only include if relevant (has any numbers or is selected explicitly)
		if any([plan, snapshot, addendum, actual]) or not parent_filter:
			data.append({
				"cost_center": name,
				"parent_cost_center": cc.parent_mpit_cost_center,
				"plan_live": plan,
				"snapshot": snapshot,
				"addendum": addendum,
				"cap": cap,
				"actual": actual,
				"remaining": remaining,
				"over_cap": over_cap,
			})
			
	return data


def get_report_summary(data: list[dict]) -> list[dict]:
	summary = []
	
	total_plan = sum(row["plan_live"] for row in data)
	total_cap = sum(row["cap"] for row in data)
	total_actual = sum(row["actual"] for row in data)
	total_remaining = sum(row["remaining"] for row in data)
	
	summary.append({
		"label": _("Total Plan (Live)"),
		"value": total_plan,
		"datatype": "Currency",
		"indicator": "blue"
	})
	summary.append({
		"label": _("Total Cap"),
		"value": total_cap,
		"datatype": "Currency",
		"indicator": "purple"
	})
	summary.append({
		"label": _("Total Actual"),
		"value": total_actual,
		"datatype": "Currency",
		"indicator": "orange"
	})
	summary.append({
		"label": _("Total Remaining"),
		"value": total_remaining,
		"datatype": "Currency",
		"indicator": "green"
	})
	
	return summary


def get_chart(data: list[dict]) -> dict:
	# Top 15 Cost Centers by Cap
	sorted_data = sorted(data, key=lambda x: x["cap"], reverse=True)[:15]
	
	labels = [d["cost_center"] for d in sorted_data]
	cap_values = [d["cap"] for d in sorted_data]
	actual_values = [d["actual"] for d in sorted_data]
	
	return {
		"data": {
			"labels": labels,
			"datasets": [
				{"name": _("Cap"), "type": "bar", "values": cap_values},
				{"name": _("Actual"), "type": "bar", "values": actual_values}
			]
		},
		"type": "bar",
		"colors": ["#5e64ff", "#ffa00a"],
		"fieldtype": "Currency"
	}


def get_extra_charts(data: list[dict]) -> dict:
	charts = {}
	
	# 1. Budget Distribution (Pie of Cap)
	if data:
		# Top 10 + Others
		sorted_by_cap = sorted(data, key=lambda x: x["cap"], reverse=True)
		top_10 = sorted_by_cap[:10]
		others_cap = sum(x["cap"] for x in sorted_by_cap[10:])
		
		labels = [d["cost_center"] for d in top_10]
		values = [d["cap"] for d in top_10]
		
		if others_cap > 0:
			labels.append(_("Others"))
			values.append(others_cap)
			
		charts["budget_distribution"] = {
			"title": _("Budget Cap Distribution"),
			"type": "pie",
			"data": {
				"labels": labels,
				"datasets": [{"values": values}]
			}
		}

	# 2. Over Cap Cost Centers
	over_cap_data = [d for d in data if d["over_cap"] > 0]
	if over_cap_data:
		charts["over_cap_centers"] = {
			"title": _("Cost Centers Over Cap"),
			"type": "bar",
			"colors": ["#ff4d4d"],
			"data": {
				"labels": [d["cost_center"] for d in over_cap_data],
				"datasets": [{"values": [d["over_cap"] for d in over_cap_data]}]
			}
		}
		
	return {"charts": charts}
