# -*- coding: utf-8 -*-
"""Deterministic checks for MPIT Budget Engine V2 semantics."""

from __future__ import annotations

import datetime

import frappe
import pytest

from master_plan_it import annualization
from master_plan_it.master_plan_it.doctype.mpit_budget.mpit_budget import MPITBudget
from master_plan_it.master_plan_it.doctype.mpit_actual_entry.mpit_actual_entry import MPITActualEntry


def _ensure_year(year: int):
	if not frappe.db.exists("MPIT Year", str(year)):
		doc = frappe.get_doc({"doctype": "MPIT Year", "year": str(year), "start_date": f"{year}-01-01", "end_date": f"{year}-12-31"})
		doc.insert(ignore_permissions=True)


def _ensure_category(name: str = "Test Category"):
	if not frappe.db.exists("MPIT Category", name):
		frappe.get_doc({"doctype": "MPIT Category", "category_name": name}).insert(ignore_permissions=True)
	return name


def _ensure_cost_center(name: str = "All Cost Centers"):
	if not frappe.db.exists("MPIT Cost Center", name):
		frappe.get_doc({"doctype": "MPIT Cost Center", "cost_center_name": name, "is_group": 1, "is_active": 1}).insert(ignore_permissions=True)
	return name


def _ensure_contract(name: str = "CT-TEST"):
	_ensure_category()
	if not frappe.db.exists("MPIT Vendor", "Vendor-X"):
		frappe.get_doc({"doctype": "MPIT Vendor", "vendor_name": "Vendor-X"}).insert(ignore_permissions=True)
	if frappe.db.exists("MPIT Contract", name):
		return name
	frappe.get_doc(
		{
			"doctype": "MPIT Contract",
			"name": name,
			"title": name,
			"vendor": "Vendor-X",
			"category": "Test Category",
			"contract_kind": "Contract",
			"current_amount_net": 100,
			"start_date": "2025-01-01",
		}
	).insert(ignore_permissions=True)
	return name


def test_overlap_months_touched():
	year_start = datetime.date(2025, 1, 1)
	year_end = datetime.date(2025, 12, 31)
	assert annualization.overlap_months("2025-01-15", "2025-03-02", year_start, year_end) == 3
	assert annualization.overlap_months("2024-01-01", "2024-12-31", year_start, year_end) == 0


def test_spread_months_across_years():
	year_start = datetime.date(2025, 1, 1)
	year_end = datetime.date(2025, 12, 31)
	# Spread 36 months from mid-2025 → mid-2028
	start = datetime.date(2025, 6, 15)
	end = datetime.date(2028, 6, 14)
	count_2025 = annualization.overlap_months(start, end, year_start, year_end)
	count_2026 = annualization.overlap_months(start, end, datetime.date(2026, 1, 1), datetime.date(2026, 12, 31))
	count_2027 = annualization.overlap_months(start, end, datetime.date(2027, 1, 1), datetime.date(2027, 12, 31))
	count_2028 = annualization.overlap_months(start, end, datetime.date(2028, 1, 1), datetime.date(2028, 12, 31))
	assert (count_2025 + count_2026 + count_2027 + count_2028) == 36
	assert count_2025 == 7 and count_2026 == 12 and count_2027 == 12 and count_2028 == 5


def test_rate_schedule_segments_months():
	year_start = datetime.date(2025, 1, 1)
	year_end = datetime.date(2025, 12, 31)
	seg1 = annualization.overlap_months("2025-01-01", "2025-07-31", year_start, year_end)
	seg2 = annualization.overlap_months("2025-08-01", "2025-12-31", year_start, year_end)
	assert seg1 == 7 and seg2 == 5


def test_refresh_idempotence_upsert():
	_ensure_year(2025)
	budget = MPITBudget()
	budget.year = "2025"
	budget.budget_kind = "Forecast"
	budget.lines = []

	first_payload = {"source_key": "CONTRACT::X", "line_kind": "Contract", "is_generated": 1, "monthly_amount": 10, "is_active": 1}
	budget._upsert_generated_lines([first_payload])
	assert len(budget.lines) == 1
	assert budget.lines[0].monthly_amount == 10

	# Re-run with updated amount: should update, not duplicate
	second_payload = dict(first_payload, monthly_amount=20)
	budget._upsert_generated_lines([second_payload])
	assert len(budget.lines) == 1
	assert budget.lines[0].monthly_amount == 20

	# Add stale generated line and ensure it gets deactivated
	stale = {"source_key": "CONTRACT::Y", "line_kind": "Contract", "is_generated": 1, "monthly_amount": 5, "is_active": 1}
	budget._upsert_generated_lines([second_payload, stale])
	assert len(budget.lines) == 2
	budget._upsert_generated_lines([second_payload])  # remove stale
	stale_line = [l for l in budget.lines if l.source_key == "CONTRACT::Y"][0]
	assert stale_line.is_active == 0


def test_actual_entry_constraints_allowance_and_delta():
	_ensure_year(2025)
	_ensure_category()
	cc = _ensure_cost_center()
	contract_name = _ensure_contract()

	# Allowance spend must require cost center and forbid links
	ae = MPITActualEntry()
	ae.posting_date = "2025-01-10"
	ae.category = "Test Category"
	ae.entry_kind = "Allowance Spend"
	ae.amount = 100
	ae.cost_center = None
	with pytest.raises(frappe.ValidationError):
		ae.validate()

	ae.cost_center = cc
	ae.contract = contract_name
	with pytest.raises(frappe.ValidationError):
		ae.validate()

	# Delta requires exactly one link
	ae2 = MPITActualEntry()
	ae2.posting_date = "2025-02-01"
	ae2.category = "Test Category"
	ae2.entry_kind = "Delta"
	ae2.amount = 50
	ae2.contract = contract_name
	ae2.project = "NonExistent"
	with pytest.raises(frappe.ValidationError):
		ae2.validate()
