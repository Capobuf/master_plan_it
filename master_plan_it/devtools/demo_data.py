# -*- coding: utf-8 -*-
"""Create deterministic demo data to exercise V2 flows with Cost Centers only (no categories)."""

from __future__ import annotations

import frappe
from frappe.utils import flt


def _get_or_make(doctype: str, name: str | None = None, **fields):
	if name and frappe.db.exists(doctype, name):
		return frappe.get_doc(doctype, name)

	data = {"doctype": doctype, **fields}
	if name:
		data["name"] = name

	doc = frappe.get_doc(data)
	doc.insert(ignore_permissions=True)
	return doc


def _ensure_user_role(user: str, role: str) -> None:
	"""Ensure the given user has a role (needed for quote approval tests)."""
	if frappe.db.exists("Has Role", {"role": role, "parent": user}):
		return
	user_doc = frappe.get_doc("User", user)
	user_doc.add_roles(role)


def _has_role(role: str, user: str | None = None) -> bool:
	"""Minimal fallback for frappe.has_role in bench context."""
	target = user or frappe.session.user
	return bool(frappe.db.exists("Has Role", {"parent": target, "role": role}))


def _make_contracts(year: str, vendor_map: dict, cc_map: dict) -> list:
	contracts = []
	# Monthly contract with VAT included
	contracts.append(
		_get_or_make(
			"MPIT Contract",
			description="CT-ZZZ-MONTHLY",
			vendor=vendor_map["A"].name,
			cost_center=cc_map["A"].name,
			status="Active",
			billing_cycle="Monthly",
			current_amount=122.0,
			current_amount_includes_vat=1,
			vat_rate=22.0,
			start_date=f"{year}-01-01",
			end_date=f"{year}-12-31",
		)
	)
	# Quarterly contract, VAT excluded
	contracts.append(
		_get_or_make(
			"MPIT Contract",
			description="CT-ZZZ-QUARTERLY",
			vendor=vendor_map["B"].name,
			cost_center=cc_map["B"].name,
			status="Active",
			billing_cycle="Quarterly",
			current_amount=300.0,
			current_amount_includes_vat=0,
			vat_rate=22.0,
			start_date=f"{year}-01-01",
			end_date=f"{year}-12-31",
		)
	)
	return contracts


def _make_projects(year: str, vendor_map: dict, cc_map: dict) -> list:
	projects = []
	# Project with approved quote (needs vCIO role)
	projects.append(
		_get_or_make(
			"MPIT Project",
			name="PRJ-ZZZ-APPROVED",
			title="ZZZ Project Approved Quote",
			status="Approved",
			cost_center=cc_map["A"].name,
			start_date=f"{year}-01-01",
			end_date=f"{year}-12-31",
			allocations=[
				{
					"doctype": "MPIT Project Allocation",
					"year": year,
					"cost_center": cc_map["A"].name,
					"planned_amount": 1200,
					"planned_amount_includes_vat": 0,
					"vat_rate": 22.0,
				}
			],
			quotes=[
				{
					"doctype": "MPIT Project Quote",
					"vendor": vendor_map["A"].name,
					"cost_center": cc_map["A"].name,
					"amount": 2500,
					"amount_includes_vat": 0,
					"vat_rate": 22.0,
					"status": "Approved",
				}
			],
		)
	)
	# Project with only planned allocation (no approved quotes)
	projects.append(
		_get_or_make(
			"MPIT Project",
			name="PRJ-ZZZ-PLANNED",
			title="ZZZ Project Planned Only",
			status="Approved",
			cost_center=cc_map["B"].name,
			start_date=f"{year}-01-01",
			end_date=f"{year}-12-31",
			allocations=[
				{
					"doctype": "MPIT Project Allocation",
					"year": year,
					"cost_center": cc_map["B"].name,
					"planned_amount": 1800,
					"planned_amount_includes_vat": 0,
					"vat_rate": 22.0,
				}
			],
			quotes=[],
		)
	)
	return projects


def _make_actual_entries(year: str, projects: list, contracts: list, vendor_map: dict, cc_map: dict) -> None:
	# Delta linked to project
	if not frappe.db.exists("MPIT Actual Entry", {"project": projects[0].name, "description": "ZZZ delta A"}):
		frappe.get_doc(
			{
				"doctype": "MPIT Actual Entry",
				"posting_date": f"{year}-02-01",
				"status": "Verified",
				"entry_kind": "Delta",
				"vendor": vendor_map["A"].name,
				"project": projects[0].name,
				"cost_center": cc_map["A"].name,
				"amount": 150,
				"amount_includes_vat": 0,
				"vat_rate": 22.0,
				"description": "ZZZ delta A",
			}
		).insert(ignore_permissions=True)
	# Delta linked to contract
	if not frappe.db.exists("MPIT Actual Entry", {"contract": contracts[1].name, "description": "ZZZ delta B"}):
		frappe.get_doc(
			{
				"doctype": "MPIT Actual Entry",
				"posting_date": f"{year}-03-01",
				"status": "Verified",
				"entry_kind": "Delta",
				"vendor": vendor_map["B"].name,
				"contract": contracts[1].name,
				"cost_center": cc_map["B"].name,
				"amount": -50,  # savings
				"amount_includes_vat": 0,
				"vat_rate": 22.0,
				"description": "ZZZ delta B",
			}
		).insert(ignore_permissions=True)
	# Allowance spend for cost center
	if not frappe.db.exists("MPIT Actual Entry", {"cost_center": cc_map["A"].name, "entry_kind": "Allowance Spend"}):
		frappe.get_doc(
			{
				"doctype": "MPIT Actual Entry",
				"posting_date": f"{year}-04-15",
				"status": "Verified",
				"entry_kind": "Allowance Spend",
				"vendor": vendor_map["A"].name,
				"cost_center": cc_map["A"].name,
				"amount": 200,
				"amount_includes_vat": 0,
				"vat_rate": 22.0,
				"description": "ZZZ allowance spend",
			}
		).insert(ignore_permissions=True)


def run(year: str = "2025") -> dict:
	"""Create richer demo data for V2 flows (contracts, projects, deltas, allowance)."""
	out: dict = {}

	# Ensure frappe.has_role is available in this execution context
	if not getattr(frappe, "has_role", None):
		frappe.has_role = _has_role  # type: ignore[attr-defined]

	# Ensure session user can approve quotes (vCIO role)
	if frappe.session.user:
		_ensure_user_role(frappe.session.user, "vCIO Manager")

	# Master data
	vendor_map = {
		"A": _get_or_make("MPIT Vendor", name="ZZZ Demo Vendor A", vendor_name="ZZZ Demo Vendor A"),
		"B": _get_or_make("MPIT Vendor", name="ZZZ Demo Vendor B", vendor_name="ZZZ Demo Vendor B"),
	}
	_get_or_make("MPIT Cost Center", name="All Cost Centers", cost_center_name="All Cost Centers", is_group=1)
	cc_map = {
		"A": _get_or_make(
			"MPIT Cost Center",
			name="ZZZ Demo CC A",
			cost_center_name="ZZZ Demo CC A",
			parent_cost_center="All Cost Centers",
			is_group=0,
		),
		"B": _get_or_make(
			"MPIT Cost Center",
			name="ZZZ Demo CC B",
			cost_center_name="ZZZ Demo CC B",
			parent_cost_center="All Cost Centers",
			is_group=0,
		),
	}

	contracts = _make_contracts(year, vendor_map, cc_map)
	projects = _make_projects(year, vendor_map, cc_map)
	_make_actual_entries(year, projects, contracts, vendor_map, cc_map)

	# Create Forecast budget and refresh from sources
	budget = frappe.get_doc(
		{
			"doctype": "MPIT Budget",
			"year": year,
			"title": f"ZZZ Demo Forecast {year}",
			"budget_kind": "Forecast",
			"is_active_forecast": 0,
		}
	)
	budget.insert(ignore_permissions=True)
	budget.refresh_from_sources()

	frappe.db.commit()

	# Reload for fresh totals
	for c in contracts:
		c.reload()
	for p in projects:
		p.reload()
	budget.reload()

	out["contracts"] = [
		{"name": c.name, "net": flt(c.current_amount_net, 2), "vat": flt(c.current_amount_vat, 2), "gross": flt(c.current_amount_gross, 2)}
		for c in contracts
	]
	out["projects"] = [
		{
			"name": p.name,
			"planned_total_net": flt(p.planned_total_net, 2),
			"quoted_total_net": flt(p.quoted_total_net, 2),
			"expected_total_net": flt(p.expected_total_net, 2),
		}
		for p in projects
	]
	out["actual_entries"] = frappe.db.count("MPIT Actual Entry", {"status": "Verified"})
	out["budget"] = {
		"name": budget.name,
		"lines": len(budget.lines),
		"total_net": flt(budget.total_amount_net, 2),
		"source_keys": [l.source_key for l in budget.lines],
	}

	return out
