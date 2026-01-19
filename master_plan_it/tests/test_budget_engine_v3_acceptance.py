"""
Acceptance tests per i 9 criteri funzionali del Budget Engine v3.
Ogni test crea dati minimi (Year, Cost Center, Contract/Planned Item/Budget) e verifica il comportamento atteso.
"""

from __future__ import annotations

import datetime

import frappe
from frappe.tests.utils import FrappeTestCase
from frappe.utils import nowdate


class TestBudgetEngineV3Acceptance(FrappeTestCase):
	def setUp(self):
		super().setUp()
		today = datetime.date.today()
		self.year_current = str(today.year)
		self.year_next = str(today.year + 1)
		self.year_past = str(today.year - 2)
		self.year_closed = str(today.year - 1)
		frappe.flags.allow_live_manual_lines = False

		self._ensure_year(self.year_current)
		self._ensure_year(self.year_next)
		self._ensure_year(self.year_past)
		self._ensure_year(self.year_closed)

		self.cost_center = self._ensure_cost_center("Acceptance CC")

	def _ensure_year(self, year_value: str):
		if not frappe.db.exists("MPIT Year", year_value):
			frappe.get_doc(
				{
					"doctype": "MPIT Year",
					"year": year_value,
					"start_date": f"{year_value}-01-01",
					"end_date": f"{year_value}-12-31",
				}
			).insert(ignore_if_duplicate=True)

	def _ensure_cost_center(self, name: str):
		if not frappe.db.exists("MPIT Cost Center", name):
			frappe.get_doc(
				{
					"doctype": "MPIT Cost Center",
					"cost_center_name": name,
					"is_group": 0,
				}
			).insert(ignore_if_duplicate=True)
		return frappe.get_doc("MPIT Cost Center", name)

	def _make_live_budget(self, year: str) -> frappe.Document:
		name = frappe.db.get_value("MPIT Budget", {"year": year, "budget_type": "Live"}, "name")
		if name:
			doc = frappe.get_doc("MPIT Budget", name)
			# Reload to get latest version (avoid TimestampMismatchError from bg jobs)
			doc.reload()
			return doc
		return frappe.get_doc(
			{
				"doctype": "MPIT Budget",
				"year": year,
				"budget_type": "Live",
				"title": f"Live {year}",
			}
		).insert()

	def _make_contract(
		self, description: str, vendor_name: str, cost_center: str,
		amount: float, billing_cycle: str = "Monthly",
		amount_includes_vat: int = 0, vat_rate: float = 0,
		status: str = "Active", start_date: str | None = None
	) -> frappe.Document:
		"""Create contract with a single term (terms are the source of truth)."""
		if start_date is None:
			start_date = f"{self.year_current}-01-01"
		return frappe.get_doc(
			{
				"doctype": "MPIT Contract",
				"description": description,
				"vendor": self._ensure_vendor(vendor_name).name,
				"cost_center": cost_center,
				"status": status,
				"start_date": start_date,
				"terms": [
					{
						"from_date": start_date,
						"amount": amount,
						"amount_includes_vat": amount_includes_vat,
						"vat_rate": vat_rate,
						"billing_cycle": billing_cycle,
					}
				],
			}
		).insert()

	def test_draft_contract_excluded_from_budget(self):
		cc = self._ensure_cost_center(f"CC-Draft-{frappe.generate_hash(length=6)}")
		budget = self._make_live_budget(self.year_current)
		contract = self._make_contract(
			description="Draft Contract",
			vendor_name="Vendor Draft",
			cost_center=cc.name,
			amount=100,
			status="Draft",
		)

		budget.refresh_from_sources()
		budget.reload()
		self.assertFalse(
			[ln for ln in budget.lines if ln.contract == contract.name],
			"Draft contracts must not generate budget lines",
		)

	def test_regression_status_removes_budget_lines(self):
		cc = self._ensure_cost_center(f"CC-Active-{frappe.generate_hash(length=6)}")
		budget = self._make_live_budget(self.year_current)
		contract = self._make_contract(
			description="Active Contract",
			vendor_name="Vendor Active",
			cost_center=cc.name,
			amount=100,
		)

		budget.refresh_from_sources()
		budget.reload()
		self.assertTrue([ln for ln in budget.lines if ln.contract == contract.name])

		contract.status = "Cancelled"
		contract.save()
		budget.refresh_from_sources()
		budget.reload()
		self.assertFalse(
			[ln for ln in budget.lines if ln.contract == contract.name],
			"Cancelled contract should remove generated lines",
		)

	def test_contract_annual_amount_preserves_total(self):
		cc = self._ensure_cost_center(f"CC-Annual-{frappe.generate_hash(length=6)}")
		budget = self._make_live_budget(self.year_current)
		contract = self._make_contract(
			description="Annual Contract",
			vendor_name="Vendor Annual",
			cost_center=cc.name,
			amount=1000,
			billing_cycle="Annual",
		)

		budget.refresh_from_sources()
		budget.reload()
		lines = [ln for ln in budget.lines if ln.contract == contract.name]
		self.assertEqual(len(lines), 1, "Annual contract should generate one line")
		line = lines[0]
		# Monthly may have more precision, but annual must stay exact to input
		self.assertAlmostEqual(float(line.annual_net), 1000.0, places=2)
		self.assertAlmostEqual(float(line.amount_net), 1000.0, places=2)

	def test_contract_annual_amount_with_vat_preserves_net_and_gross(self):
		cc = self._ensure_cost_center(f"CC-Annual-VAT-{frappe.generate_hash(length=6)}")
		budget = self._make_live_budget(self.year_current)
		contract = self._make_contract(
			description="Annual Contract VAT",
			vendor_name="Vendor Annual VAT",
			cost_center=cc.name,
			amount=1220,
			amount_includes_vat=1,
			vat_rate=22,
			billing_cycle="Annual",
		)

		budget.refresh_from_sources()
		budget.reload()
		lines = [ln for ln in budget.lines if ln.contract == contract.name]
		self.assertEqual(len(lines), 1, "Annual contract with VAT should generate one line")
		line = lines[0]
		self.assertAlmostEqual(float(line.annual_net), 1000.0, places=2)
		self.assertAlmostEqual(float(line.annual_vat), 220.0, places=2)
		self.assertAlmostEqual(float(line.annual_gross), 1220.0, places=2)

	def test_contract_monthly_amounts_are_consistent(self):
		cc = self._ensure_cost_center(f"CC-Monthly-{frappe.generate_hash(length=6)}")
		budget = self._make_live_budget(self.year_current)
		contract = self._make_contract(
			description="Monthly Contract",
			vendor_name="Vendor Monthly",
			cost_center=cc.name,
			amount=100,
		)

		budget.refresh_from_sources()
		budget.reload()
		line = [ln for ln in budget.lines if ln.contract == contract.name][0]
		self.assertAlmostEqual(float(line.monthly_amount), 100.0, places=2)
		self.assertAlmostEqual(float(line.annual_net), 1200.0, places=2)

	def test_contract_quarterly_amounts_are_consistent(self):
		cc = self._ensure_cost_center(f"CC-Quarterly-{frappe.generate_hash(length=6)}")
		budget = self._make_live_budget(self.year_current)
		contract = self._make_contract(
			description="Quarterly Contract",
			vendor_name="Vendor Quarterly",
			cost_center=cc.name,
			amount=300,
			billing_cycle="Quarterly",
		)

		budget.refresh_from_sources()
		budget.reload()
		line = [ln for ln in budget.lines if ln.contract == contract.name][0]
		self.assertAlmostEqual(float(line.monthly_amount), 100.0, places=2)
		self.assertAlmostEqual(float(line.annual_net), 1200.0, places=2)

	def test_multi_year_planned_item_impacts_both_years(self):
		year_a = str(int(self.year_current) + 5)
		year_b = str(int(self.year_current) + 6)
		self._ensure_year(year_a)
		self._ensure_year(year_b)

		budget_current = self._make_live_budget(year_a)
		budget_next = self._make_live_budget(year_b)
		project = frappe.get_doc(
			{
				"doctype": "MPIT Project",
				"title": "Planned Project",
				# v3: only validated project statuses feed the budget
				"operational_status": "On Hold",
				"cost_center": self.cost_center.name,
			}
		).insert()
		item = frappe.get_doc(
			{
				"doctype": "MPIT Planned Item",
				"project": project.name,
				"description": "Two-year item",
				"amount": 1200,
				"start_date": f"{year_a}-01-01",
				"end_date": f"{year_b}-12-31",
				"distribution": "all",
				"docstatus": 1,
				"covered_by_type": "",
				"covered_by_name": "",
			}
		)
		item.insert()

		# Reload budgets just before refresh to avoid timestamp mismatch from other hooks
		frappe.call("master_plan_it.master_plan_it.doctype.mpit_budget.mpit_budget.refresh_from_sources", budget=budget_current.name)
		frappe.call("master_plan_it.master_plan_it.doctype.mpit_budget.mpit_budget.refresh_from_sources", budget=budget_next.name)
		budget_current.reload()
		budget_next.reload()

		self.assertGreater(len(budget_current.lines), 0, "Current year budget should have planned item lines")
		self.assertGreater(len(budget_next.lines), 0, "Next year budget should have planned item lines")

	def test_planned_item_distribution_all_splits_evenly(self):
		budget = self._make_live_budget(self.year_current)
		project = frappe.get_doc(
			{
				"doctype": "MPIT Project",
				"title": "Planned Dist All",
				"cost_center": self.cost_center.name,
			}
		).insert()
		# Bypass workflow to set Approved state directly (test only)
		frappe.db.set_value("MPIT Project", project.name, "workflow_state", "Approved")
		item = frappe.get_doc(
			{
				"doctype": "MPIT Planned Item",
				"project": project.name,
				"description": "Dist all",
				"amount": 1200,
				"start_date": f"{self.year_current}-01-01",
				"end_date": f"{self.year_current}-12-31",
				"distribution": "all",
				"docstatus": 1,
				"is_covered": 0,
				"covered_by_type": "",
				"covered_by_name": "",
			}
		).insert()

		budget.refresh_from_sources()
		budget.reload()
		line = [ln for ln in budget.lines if ln.project == project.name][0]
		self.assertAlmostEqual(float(line.monthly_amount), 100.0, places=2)
		self.assertAlmostEqual(float(line.annual_net), 1200.0, places=2)

	def test_planned_item_distribution_start_single_month(self):
		budget = self._make_live_budget(self.year_current)
		project = frappe.get_doc(
			{
				"doctype": "MPIT Project",
				"title": "Planned Dist Start",
				"cost_center": self.cost_center.name,
			}
		).insert()
		# Bypass workflow to set Approved state directly (test only)
		frappe.db.set_value("MPIT Project", project.name, "workflow_state", "Approved")
		item = frappe.get_doc(
			{
				"doctype": "MPIT Planned Item",
				"project": project.name,
				"description": "Dist start",
				"amount": 1200,
				"start_date": f"{self.year_current}-01-01",
				"end_date": f"{self.year_current}-12-31",
				"distribution": "start",
				"docstatus": 1,
				"is_covered": 0,
				"covered_by_type": "",
				"covered_by_name": "",
			}
		).insert()

		budget.refresh_from_sources()
		budget.reload()
		line = [ln for ln in budget.lines if ln.project == project.name][0]
		self.assertAlmostEqual(float(line.monthly_amount), 1200.0, places=2)
		self.assertAlmostEqual(float(line.annual_net), 1200.0, places=2)

	def test_planned_item_spend_date_single_month(self):
		budget = self._make_live_budget(self.year_current)
		project = frappe.get_doc(
			{
				"doctype": "MPIT Project",
				"title": "Planned Spend Date",
				"cost_center": self.cost_center.name,
			}
		).insert()
		# Bypass workflow to set Approved state directly (test only)
		frappe.db.set_value("MPIT Project", project.name, "workflow_state", "Approved")
		item = frappe.get_doc(
			{
				"doctype": "MPIT Planned Item",
				"project": project.name,
				"description": "Spend date only",
				"amount": 500,
				"start_date": f"{self.year_current}-10-01",
				"end_date": f"{self.year_current}-10-31",
				"spend_date": f"{self.year_current}-10-15",
				"docstatus": 1,
				"is_covered": 0,
				"covered_by_type": "",
				"covered_by_name": "",
			}
		).insert()

		budget.refresh_from_sources()
		budget.reload()
		line = [ln for ln in budget.lines if ln.project == project.name][0]
		self.assertAlmostEqual(float(line.monthly_amount), 500.0, places=2)
		self.assertAlmostEqual(float(line.annual_net), 500.0, places=2)

	def test_auto_refresh_skips_out_of_horizon_years(self):
		from master_plan_it.master_plan_it.doctype.mpit_budget.mpit_budget import enqueue_budget_refresh

		# ensure no budget exists for past year
		self.assertFalse(frappe.db.exists("MPIT Budget", {"year": self.year_past, "budget_type": "Live"}))
		enqueue_budget_refresh([self.year_past])
		self.assertFalse(
			frappe.db.exists("MPIT Budget", {"year": self.year_past, "budget_type": "Live"}),
			"Auto-refresh must not create Live budgets outside horizon",
		)

	def test_manual_refresh_allowed_on_closed_year_with_comment(self):
		budget = self._make_live_budget(self.year_closed)
		budget.refresh_from_sources(is_manual=1, reason="Acceptance test")
		comments = frappe.get_all(
			"Comment",
			filters={"reference_doctype": "MPIT Budget", "reference_name": budget.name, "content": ["like", "%Manual refresh on closed year%"]},
		)
		self.assertTrue(comments, "Manual refresh on closed year should log a timeline comment")

	def test_snapshot_is_immutable(self):
		# Use a dedicated year to avoid clashing with deterministic Live naming in other tests.
		year = str(int(self.year_next) + 10)
		self._ensure_year(year)

		frappe.flags.allow_live_manual_lines = True
		live = frappe.get_doc(
			{
				"doctype": "MPIT Budget",
				"year": year,
				"budget_type": "Live",
				"title": "Live for Snapshot",
				"lines": [
					{
						"doctype": "MPIT Budget Line",
						"cost_center": self.cost_center.name,
						"line_kind": "Manual",
						"monthly_amount": 100,
						"recurrence_rule": "Monthly",
						"amount_includes_vat": 0,
						"vat_rate": 0,
						"is_generated": 1,
					}
				],
			}
		).insert()
		frappe.flags.allow_live_manual_lines = False

		snapshot_name = frappe.call("master_plan_it.master_plan_it.doctype.mpit_budget.mpit_budget.create_snapshot", source_budget=live.name)
		snapshot = frappe.get_doc("MPIT Budget", snapshot_name)
		snapshot.reload()
		snapshot.lines[0].description = "Edited"
		with self.assertRaises(frappe.ValidationError):
			snapshot.save()

	def test_addendum_increases_cap(self):
		frappe.flags.allow_live_manual_lines = True
		snapshot = frappe.get_doc(
			{
				"doctype": "MPIT Budget",
				"year": self.year_current,
				"budget_type": "Snapshot",
				"title": "Snapshot Cap",
				"workflow_state": "Draft",
				"lines": [
					{
						"doctype": "MPIT Budget Line",
						"cost_center": self.cost_center.name,
						"line_kind": "Allowance",
						"monthly_amount": 100,
						"recurrence_rule": "Monthly",
						"amount_includes_vat": 0,
						"vat_rate": 0,
						"is_generated": 1,
					}
				],
			}
		)
		snapshot.flags.skip_immutability = True
		snapshot.flags.skip_generated_guard = True
		snapshot.insert()
		snapshot.submit()
		frappe.flags.allow_live_manual_lines = False

		addendum = frappe.get_doc(
			{
				"doctype": "MPIT Budget Addendum",
				"year": self.year_current,
				"cost_center": self.cost_center.name,
				"reference_snapshot": snapshot.name,
				"delta_amount": 50,
				"reason": "Increase cap",
			}
		).insert()
		addendum.submit()

		from master_plan_it.master_plan_it.doctype.mpit_budget.mpit_budget import get_cap_for_cost_center

		cap = get_cap_for_cost_center(self.year_current, self.cost_center.name)
		self.assertEqual(cap["cap_total"], 1250)

	def test_covered_planned_item_excluded_from_budget(self):
		budget = self._make_live_budget(self.year_current)
		project = frappe.get_doc(
			{
				"doctype": "MPIT Project",
				"title": "Covered PI Project",
				"workflow_state": "Draft",
				"cost_center": self.cost_center.name,
			}
		).insert()
		item = frappe.get_doc(
			{
				"doctype": "MPIT Planned Item",
				"project": project.name,
				"description": "Covered item",
				"amount": 500,
				"start_date": f"{self.year_current}-01-01",
				"end_date": f"{self.year_current}-12-31",
				"distribution": "all",
				"docstatus": 0,
			}
		)
		item.flags.ignore_validate = True
		item.insert()
		frappe.db.set_value(
			"MPIT Planned Item",
			item.name,
			{"is_covered": 1, "docstatus": 1, "covered_by_type": "", "covered_by_name": ""},
		)

		budget.refresh_from_sources()
		budget.reload()
		self.assertFalse(
			[ln for ln in budget.lines if (ln.source_key or "").startswith(f"PLANNED_ITEM::{item.name}")],
			"Covered Planned Item must be excluded from budget lines",
		)

	def test_live_budget_not_editable_manually(self):
		# Ensure enforcement even in tests (no allow_live_manual_lines flag)
		year = str(int(self.year_next) + 11)
		self._ensure_year(year)
		with self.assertRaises(frappe.ValidationError):
			frappe.get_doc(
				{
					"doctype": "MPIT Budget",
					"year": year,
					"budget_type": "Live",
					"title": "Live Manual Block",
					"lines": [
						{
							"doctype": "MPIT Budget Line",
							"cost_center": self.cost_center.name,
							"line_kind": "Manual",
							"monthly_amount": 100,
							"recurrence_rule": "Monthly",
							"amount_includes_vat": 0,
							"vat_rate": 0,
						}
					],
				}
			).insert()

	def _ensure_vendor(self, name: str):
		if not frappe.db.exists("MPIT Vendor", name):
			frappe.get_doc({"doctype": "MPIT Vendor", "vendor_name": name}).insert(ignore_if_duplicate=True)
		return frappe.get_doc("MPIT Vendor", name)
