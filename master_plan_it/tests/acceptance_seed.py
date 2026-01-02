"""
Seed di dati di accettazione end-to-end per Budget Engine v3.
Usa lo stesso flusso utente: crea Live, refresh, Snapshot, Addendum e calcola Cap.
Può essere eseguito via:
bench --site <site> execute master_plan_it.tests.acceptance_seed.run_seed
"""

from __future__ import annotations

import datetime

import frappe


def _ensure_year(year: str) -> None:
	if not frappe.db.exists("MPIT Year", year):
		frappe.get_doc(
			{
				"doctype": "MPIT Year",
				"year": year,
				"start_date": f"{year}-01-01",
				"end_date": f"{year}-12-31",
			}
		).insert()


def _ensure_cc(name: str):
	if not frappe.db.exists("MPIT Cost Center", name):
		frappe.get_doc(
			{
				"doctype": "MPIT Cost Center",
				"cost_center_name": name,
				"is_group": 0,
				"is_active": 1,
			}
		).insert()
	return frappe.get_doc("MPIT Cost Center", name)


def _ensure_vendor(name: str):
	if not frappe.db.exists("MPIT Vendor", name):
		frappe.get_doc({"doctype": "MPIT Vendor", "vendor_name": name}).insert()
	return frappe.get_doc("MPIT Vendor", name)


def _get_or_create_live(year: str, title: str):
	name = frappe.db.get_value("MPIT Budget", {"year": year, "budget_type": "Live"}, "name")
	if name:
		return frappe.get_doc("MPIT Budget", name)
	doc = frappe.get_doc({"doctype": "MPIT Budget", "year": year, "budget_type": "Live", "title": title})
	doc.insert()
	return doc


def run_seed() -> dict:
	"""Crea dati di test e ritorna un riepilogo."""
	today = datetime.date.today()
	year = str(today.year)
	next_year = str(today.year + 1)

	_ensure_year(year)
	_ensure_year(next_year)
	cc = _ensure_cc("ACCEPT_CC")
	ven = _ensure_vendor("ACCEPT_VENDOR")

	# Project con allocation
	proj = frappe.get_doc(
		{
			"doctype": "MPIT Project",
			"title": "Acceptance Project",
			"status": "On Hold",
			"cost_center": cc.name,
		}
	)
	proj.append("allocations", {"year": year, "cost_center": cc.name, "planned_amount": 1000})
	proj.insert()

	# Planned Item multi-anno submitted
	pi = frappe.get_doc(
		{
			"doctype": "MPIT Planned Item",
			"project": proj.name,
			"description": "Acceptance PI",
			"amount": 1200,
			"start_date": f"{year}-01-01",
			"end_date": f"{next_year}-12-31",
			"distribution": "all",
			"covered_by_type": "",
			"covered_by_name": "",
		}
	)
	pi.flags.ignore_validate = True
	pi.insert()
	pi.submit()

	# Contract Active
	contract = frappe.get_doc(
		{
			"doctype": "MPIT Contract",
			"title": "Acceptance Contract",
			"vendor": ven.name,
			"cost_center": cc.name,
			"status": "Active",
			"current_amount": 600,
			"current_amount_includes_vat": 0,
			"vat_rate": 0,
			"billing_cycle": "Monthly",
		}
	)
	contract.insert()

	# Live budget con allowance
	live = _get_or_create_live(year, f"Live {year}")

	live.refresh_from_sources(is_manual=1, reason="Acceptance load")
	live.reload()

	# Snapshot + Addendum
	from master_plan_it.master_plan_it.doctype.mpit_budget.mpit_budget import create_snapshot, get_cap_for_cost_center

	snap_name = create_snapshot(live.name)
	snap = frappe.get_doc("MPIT Budget", snap_name)
	snap.flags.skip_immutability = True
	# Aggiungi allowance manuale sullo Snapshot (richiesto per Addendum/Cap)
	if not any(getattr(ln, "line_kind", "") == "Allowance" for ln in snap.lines):
		year_start = f"{year}-01-01"
		year_end = f"{year}-12-31"
		snap.append(
			"lines",
			{
				"line_kind": "Allowance",
				"cost_center": cc.name,
				"monthly_amount": 1000,
				"recurrence_rule": "Monthly",
				"amount_includes_vat": 0,
				"vat_rate": 0,
				"period_start_date": year_start,
				"period_end_date": year_end,
				"is_generated": 0,
			},
		)
	snap.save(ignore_permissions=True)
	snap.submit()

	add = frappe.get_doc(
		{
			"doctype": "MPIT Budget Addendum",
			"year": year,
			"cost_center": cc.name,
			"reference_snapshot": snap.name,
			"delta_amount": 300,
			"reason": "Acceptance addendum",
		}
	)
	add.insert()
	add.submit()

	cap = get_cap_for_cost_center(year, cc.name)

	summary = {
		"live_budget": live.name,
		"live_lines": len(live.lines),
		"snapshot": snap.name,
		"addendum": add.name,
		"cap_total": cap,
		"planned_item": pi.name,
		"contract": contract.name,
	}
	frappe.flags.allow_live_manual_lines = False
	print(summary)
	return summary
