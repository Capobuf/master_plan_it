# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

"""
MPIT Actual Entries Report

A comprehensive Script Report for Actual Entries with:
- KPI Cards (Total amounts, counts by status/kind)
- Detailed table with all entry information
- Charts: Monthly trend, Status distribution, Cost Center breakdown
"""

from __future__ import annotations

import frappe
from frappe import _
from frappe.utils import flt, getdate
from master_plan_it.master_plan_it.utils.dashboard_utils import normalize_dashboard_filters


def execute(filters=None):
	filters = normalize_dashboard_filters(filters)
	filters = frappe._dict(filters or {})
	
	columns = get_columns()
	data = get_data(filters)
	report_summary = get_report_summary(filters, data)
	chart = get_chart(data)
	message = get_extra_charts(filters, data)
	
	return columns, data, None, chart, report_summary, message


def get_columns():
	return [
		{"label": _("ID"), "fieldname": "name", "fieldtype": "Link", "options": "MPIT Actual Entry", "width": 100},
		{"label": _("Year"), "fieldname": "year", "fieldtype": "Link", "options": "MPIT Year", "width": 80},
		{"label": _("Date"), "fieldname": "posting_date", "fieldtype": "Date", "width": 100},
		{"label": _("Status"), "fieldname": "status", "fieldtype": "Data", "width": 90},
		{"label": _("Entry Kind"), "fieldname": "entry_kind", "fieldtype": "Data", "width": 120},
		{"label": _("Cost Center"), "fieldname": "cost_center", "fieldtype": "Link", "options": "MPIT Cost Center", "width": 150},
		{"label": _("Contract"), "fieldname": "contract", "fieldtype": "Link", "options": "MPIT Contract", "width": 120},
		{"label": _("Project"), "fieldname": "project", "fieldtype": "Link", "options": "MPIT Project", "width": 120},
		{"label": _("Amount"), "fieldname": "amount", "fieldtype": "Currency", "options": "currency", "width": 120},
		{"label": _("VAT Included"), "fieldname": "amount_includes_vat", "fieldtype": "Check", "width": 100},
		{"label": _("VAT Rate"), "fieldname": "vat_rate", "fieldtype": "Percent", "width": 90},
		{"label": _("Net Amount"), "fieldname": "amount_net", "fieldtype": "Currency", "options": "currency", "width": 120},
		{"label": _("VAT Amount"), "fieldname": "amount_vat", "fieldtype": "Currency", "options": "currency", "width": 120},
		{"label": _("Gross Amount"), "fieldname": "amount_gross", "fieldtype": "Currency", "options": "currency", "width": 120},
		{"label": _("Description"), "fieldname": "description", "fieldtype": "Data", "width": 200},
	]


def get_data(filters: frappe._dict) -> list[dict]:
	"""
	Fetch actual entries with applied filters.
	"""
	conditions = []
	values = {}
	
	if filters.get("year"):
		conditions.append("year = %(year)s")
		values["year"] = filters.year
	
	if filters.get("cost_center"):
		conditions.append("cost_center = %(cost_center)s")
		values["cost_center"] = filters.cost_center
	
	if filters.get("entry_kind"):
		conditions.append("entry_kind = %(entry_kind)s")
		values["entry_kind"] = filters.entry_kind
	
	if filters.get("status"):
		conditions.append("status = %(status)s")
		values["status"] = filters.status
	
	if filters.get("from_date"):
		conditions.append("posting_date >= %(from_date)s")
		values["from_date"] = filters.from_date
	
	if filters.get("to_date"):
		conditions.append("posting_date <= %(to_date)s")
		values["to_date"] = filters.to_date
	
	if filters.get("contract"):
		conditions.append("contract = %(contract)s")
		values["contract"] = filters.contract
	
	if filters.get("project"):
		conditions.append("project = %(project)s")
		values["project"] = filters.project
	
	where_clause = " AND " + " AND ".join(conditions) if conditions else ""
	
	query = f"""
		SELECT
			name,
			year,
			posting_date,
			status,
			entry_kind,
			cost_center,
			contract,
			project,
			amount,
			amount_includes_vat,
			vat_rate,
			amount_net,
			amount_vat,
			amount_gross,
			description
		FROM `tabMPIT Actual Entry`
		WHERE 1=1 {where_clause}
		ORDER BY posting_date DESC, name DESC
	"""
	
	return frappe.db.sql(query, values, as_dict=1)


def get_report_summary(filters: frappe._dict, data: list[dict]) -> list[dict]:
	"""
	Generate KPI cards for the report summary.
	"""
	summary = []
	
	# Total Entries
	summary.append({
		"label": _("Total Entries"),
		"value": len(data),
		"datatype": "Int",
		"indicator": "blue",
	})
	
	# Count by status
	recorded_count = sum(1 for row in data if row.get("status") == "Recorded")
	verified_count = sum(1 for row in data if row.get("status") == "Verified")
	
	summary.append({
		"label": _("Recorded"),
		"value": recorded_count,
		"datatype": "Int",
		"indicator": "orange",
	})
	
	summary.append({
		"label": _("Verified"),
		"value": verified_count,
		"datatype": "Int",
		"indicator": "green",
	})
	
	# Count by entry kind
	delta_count = sum(1 for row in data if row.get("entry_kind") == "Delta")
	allowance_count = sum(1 for row in data if row.get("entry_kind") == "Allowance Spend")
	
	summary.append({
		"label": _("Delta"),
		"value": delta_count,
		"datatype": "Int",
		"indicator": "blue",
	})
	
	summary.append({
		"label": _("Allowance Spend"),
		"value": allowance_count,
		"datatype": "Int",
		"indicator": "purple",
	})
	
	# Total amounts
	total_net = sum(flt(row.get("amount_net", 0)) for row in data)
	total_vat = sum(flt(row.get("amount_vat", 0)) for row in data)
	total_gross = sum(flt(row.get("amount_gross", 0)) for row in data)
	
	summary.append({
		"label": _("Total Net"),
		"value": total_net,
		"datatype": "Currency",
		"indicator": "blue",
	})
	
	summary.append({
		"label": _("Total VAT"),
		"value": total_vat,
		"datatype": "Currency",
		"indicator": "orange",
	})
	
	summary.append({
		"label": _("Total Gross"),
		"value": total_gross,
		"datatype": "Currency",
		"indicator": "green",
	})
	
	return summary


def get_chart(data: list[dict]) -> dict:
	"""
	Primary chart: Monthly trend of net amounts.
	"""
	if not data:
		return {}
	
	# Group by month
	monthly_data = {}
	for row in data:
		if row.get("posting_date"):
			posting_date = getdate(row.get("posting_date"))
			month_key = posting_date.strftime("%Y-%m")
			if month_key not in monthly_data:
				monthly_data[month_key] = 0
			monthly_data[month_key] += flt(row.get("amount_net", 0))
	
	# Sort by month
	sorted_months = sorted(monthly_data.keys())
	labels = [month for month in sorted_months]
	values = [monthly_data[month] for month in sorted_months]
	
	return {
		"data": {
			"labels": labels,
			"datasets": [
				{"name": _("Net Amount"), "type": "bar", "values": values},
			],
		},
		"type": "axis-mixed",
		"colors": ["#5e64ff"],
		"fieldtype": "Currency",
	}


def get_extra_charts(filters: frappe._dict, data: list[dict]) -> dict:
	"""
	Additional charts returned via message payload.
	"""
	charts = {}
	
	# Entries by Status (Pie)
	if data:
		status_counts = {}
		for row in data:
			status = row.get("status") or _("Unknown")
			status_counts[status] = status_counts.get(status, 0) + 1
		
		if status_counts:
			charts["entries_by_status"] = {
				"title": _("Entries by Status"),
				"type": "pie",
				"data": {
					"labels": list(status_counts.keys()),
					"datasets": [{"values": list(status_counts.values())}],
				},
			}
	
	# Entries by Entry Kind (Pie)
	if data:
		kind_counts = {}
		for row in data:
			kind = row.get("entry_kind") or _("Unknown")
			kind_counts[kind] = kind_counts.get(kind, 0) + 1
		
		if kind_counts:
			charts["entries_by_kind"] = {
				"title": _("Entries by Entry Kind"),
				"type": "pie",
				"data": {
					"labels": list(kind_counts.keys()),
					"datasets": [{"values": list(kind_counts.values())}],
				},
			}
	
	# Entries by Cost Center (Bar)
	if data:
		cc_amounts = {}
		for row in data:
			cc = row.get("cost_center") or _("No Cost Center")
			cc_amounts[cc] = cc_amounts.get(cc, 0) + flt(row.get("amount_net", 0))
		
		if cc_amounts:
			charts["entries_by_cost_center"] = {
				"title": _("Net Amount by Cost Center"),
				"type": "bar",
				"data": {
					"labels": list(cc_amounts.keys()),
					"datasets": [{"values": list(cc_amounts.values())}],
				},
				"fieldtype": "Currency",
			}
	
	return {"charts": charts}
