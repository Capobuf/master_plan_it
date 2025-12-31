# -*- coding: utf-8 -*-
"""Smoke test VAT computations across MPIT doctypes.

Creates temporary documents, checks computed net/vat/gross, then cleans up.
Not run automatically; use:
bench --site <site> execute master_plan_it.devtools.vat_smoke.run --kwargs '{"year":"2025"}'
"""

from __future__ import annotations

import frappe
from frappe.utils import flt


def _approx(value, expected, precision=2) -> bool:
	return flt(value, precision) == flt(expected, precision)


def run(year: str = "2025") -> dict:
	results = {}
	errors = []

	# Create master data
	cat = frappe.get_doc({"doctype": "MPIT Category", "category_name": "ZZZ VAT Test Category"})
	vendor = frappe.get_doc({"doctype": "MPIT Vendor", "vendor_name": "ZZZ VAT Test Vendor"})
	cc = frappe.get_doc(
		{
			"doctype": "MPIT Cost Center",
			"cost_center_name": "ZZZ VAT Test CC",
			"parent_cost_center": "All Cost Centers",
			"is_group": 0,
			"is_active": 1,
		}
	)
	project = None
	contract = None
	actual = None
	budget = None

	try:
		cat.insert(ignore_permissions=True)
		vendor.insert(ignore_permissions=True)
		cc.insert(ignore_permissions=True)

		# Contract: gross 122 @22% includes VAT -> net 100, vat 22
		contract = frappe.get_doc(
			{
				"doctype": "MPIT Contract",
				"title": "ZZZ VAT Contract",
				"vendor": vendor.name,
				"category": cat.name,
				"cost_center": cc.name,
				"contract_kind": "Contract",
				"status": "Active",
				"billing_cycle": "Monthly",
				"current_amount": 122.0,
				"current_amount_includes_vat": 1,
				"vat_rate": 22.0,
				"start_date": f"{year}-01-01",
				"end_date": f"{year}-12-31",
			}
		)
		contract.insert(ignore_permissions=True)
		results["contract"] = {
			"net": contract.current_amount_net,
			"vat": contract.current_amount_vat,
			"gross": contract.current_amount_gross,
		}
		if not (_approx(contract.current_amount_net, 100) and _approx(contract.current_amount_vat, 22) and _approx(contract.current_amount_gross, 122)):
			errors.append("Contract VAT split mismatch")

		# Project with allocation+quote
		project = frappe.get_doc(
			{
				"doctype": "MPIT Project",
				"title": "ZZZ VAT Project",
				"status": "Approved",
				"cost_center": cc.name,
				"start_date": f"{year}-01-01",
				"end_date": f"{year}-12-31",
				"allocations": [
					{
						"doctype": "MPIT Project Allocation",
						"year": year,
						"category": cat.name,
						"planned_amount": 122.0,
						"planned_amount_includes_vat": 1,
						"vat_rate": 22.0,
					}
				],
				"quotes": [
					{
						"doctype": "MPIT Project Quote",
						"vendor": vendor.name,
						"category": cat.name,
						"amount": 244.0,
						"amount_includes_vat": 1,
						"vat_rate": 22.0,
						"status": "Informational",
					}
				],
			}
		)
		project.insert(ignore_permissions=True)
		results["project_alloc"] = {
			"net": project.allocations[0].planned_amount_net,
			"vat": project.allocations[0].planned_amount_vat,
			"gross": project.allocations[0].planned_amount_gross,
		}
		results["project_quote"] = {
			"net": project.quotes[0].amount_net,
			"vat": project.quotes[0].amount_vat,
			"gross": project.quotes[0].amount_gross,
		}
		if not (_approx(project.allocations[0].planned_amount_net, 100) and _approx(project.allocations[0].planned_amount_vat, 22)):
			errors.append("Project allocation VAT split mismatch")
		if not (_approx(project.quotes[0].amount_net, 200) and _approx(project.quotes[0].amount_vat, 44)):
			errors.append("Project quote VAT split mismatch")

		# Actual entry: gross 122 includes VAT -> net 100
		actual = frappe.get_doc(
			{
				"doctype": "MPIT Actual Entry",
				"posting_date": f"{year}-05-01",
				"status": "Verified",
				"entry_kind": "Delta",
				"category": cat.name,
				"vendor": vendor.name,
				"project": project.name,
				"amount": 122.0,
				"amount_includes_vat": 1,
				"vat_rate": 22.0,
				"description": "ZZZ VAT Actual",
			}
		)
		actual.insert(ignore_permissions=True)
		results["actual_entry"] = {
			"net": actual.amount_net,
			"vat": actual.amount_vat,
			"gross": actual.amount_gross,
		}
		if not (_approx(actual.amount_net, 100) and _approx(actual.amount_vat, 22)):
			errors.append("Actual Entry VAT split mismatch")

		# Budget + line: monthly net 100 @22% => annual net 1200, vat 264
		budget = frappe.get_doc(
			{
				"doctype": "MPIT Budget",
				"year": year,
				"title": "ZZZ VAT Budget",
				"budget_kind": "Forecast",
				"lines": [
					{
						"doctype": "MPIT Budget Line",
						"category": cat.name,
						"vendor": vendor.name,
						"line_kind": "Manual",
						"monthly_amount": 100,
						"amount_includes_vat": 0,
						"vat_rate": 22.0,
						"recurrence_rule": "Monthly",
						"period_start_date": f"{year}-01-01",
						"period_end_date": f"{year}-12-31",
					}
				],
			}
		)
		budget.insert(ignore_permissions=True)
		results["budget_line"] = {
			"annual_net": budget.lines[0].annual_net,
			"annual_vat": budget.lines[0].annual_vat,
			"annual_gross": budget.lines[0].annual_gross,
		}
		if not (_approx(budget.lines[0].annual_net, 1200) and _approx(budget.lines[0].annual_vat, 264)):
			errors.append("Budget line VAT/annualization mismatch")

	except Exception as exc:  # pragma: no cover - diagnostic return
		frappe.db.rollback()
		return {"errors": errors + [str(exc)]}
	else:
		frappe.db.commit()
	finally:
		# Clean up created docs to avoid polluting tenant data
		for doc in [budget, actual, project, contract, cc, vendor, cat]:
			try:
				if doc:
					doc.delete(ignore_permissions=True)
			except Exception:
				continue
		frappe.db.commit()

	results["errors"] = errors
	results["ok"] = not errors
	return results
