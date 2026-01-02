# Copyright (c) 2025, DOT and Contributors
# See license.txt

import frappe
from frappe.tests.utils import FrappeTestCase
from frappe.utils import flt
from master_plan_it import amounts


class TestMPITBudget(FrappeTestCase):
	def setUp(self):
		super().setUp()
		self.year = self._ensure_year(self._find_unused_year())
		self.cost_center = self._ensure_cost_center("Totals Test CC")
		frappe.flags.allow_live_manual_lines = True

	def tearDown(self):
		frappe.flags.allow_live_manual_lines = False

	def _ensure_year(self, year_value: int):
		if not frappe.db.exists("MPIT Year", year_value):
			doc = frappe.get_doc({
				"doctype": "MPIT Year",
				"year": year_value,
				"start_date": f"{year_value}-01-01",
				"end_date": f"{year_value}-12-31"
			})
			doc.insert(ignore_if_duplicate=True)
		return frappe.get_doc("MPIT Year", year_value)

	def _find_unused_year(self) -> int:
		year = 2080
		while frappe.db.exists("MPIT Budget", {"year": str(year), "budget_type": "Live"}):
			year += 1
		return year

	def _ensure_cost_center(self, name: str):
		if not frappe.db.exists("MPIT Cost Center", name):
			doc = frappe.get_doc({
				"doctype": "MPIT Cost Center",
				"cost_center_name": name,
				"is_group": 0
			})
			doc.insert(ignore_if_duplicate=True)
		return frappe.get_doc("MPIT Cost Center", name)

	def test_totals_aggregated_from_lines(self):
		budget = frappe.get_doc({
			"doctype": "MPIT Budget",
			"year": self.year.name,
			"title": "Totals Test",
			"lines": [
				{
					"doctype": "MPIT Budget Line",
					"cost_center": self.cost_center.name,
					"line_kind": "Manual",
					"monthly_amount": 100,
					"amount_includes_vat": 0,
					"vat_rate": 10,
					"recurrence_rule": "Monthly"
				},
				{
					"doctype": "MPIT Budget Line",
					"cost_center": self.cost_center.name,
					"line_kind": "Manual",
					"monthly_amount": 200,
					"amount_includes_vat": 0,
					"vat_rate": 5,
					"recurrence_rule": "Monthly"
				}
			]
		})
		budget.insert()
		budget.reload()

		self.assertEqual(budget.total_amount_monthly, flt(300, 2))
		self.assertEqual(budget.total_amount_annual, flt(3600, 2))
		self.assertEqual(budget.total_amount_net, flt(3600, 2))
		self.assertEqual(budget.total_amount_vat, flt(240, 2))
		self.assertEqual(budget.total_amount_gross, flt(3840, 2))

	def test_totals_update_when_line_saved_individually(self):
		budget = frappe.get_doc({
			"doctype": "MPIT Budget",
			"year": self.year.name,
			"title": "Totals Child Save",
			"lines": [
				{
					"doctype": "MPIT Budget Line",
					"cost_center": self.cost_center.name,
					"line_kind": "Manual",
					"monthly_amount": 100,
					"amount_includes_vat": 0,
					"vat_rate": 10,
					"recurrence_rule": "Monthly"
				},
				{
					"doctype": "MPIT Budget Line",
					"cost_center": self.cost_center.name,
					"line_kind": "Manual",
					"monthly_amount": 200,
					"amount_includes_vat": 0,
					"vat_rate": 5,
					"recurrence_rule": "Monthly"
				}
			]
		})
		budget.insert()
		budget.reload()

		line = frappe.get_doc("MPIT Budget Line", budget.lines[0].name)
		updated = amounts.compute_line_amounts(
			qty=1,
			unit_price=0,
			monthly_amount=150,
			annual_amount=0,
			vat_rate=10,
			amount_includes_vat=False,
			recurrence_rule="Monthly",
			overlap_months=12,
		)
		line.monthly_amount = updated["monthly_amount"]
		line.annual_amount = updated["annual_amount"]
		line.amount_net = updated["amount_net"]
		line.amount_vat = updated["amount_vat"]
		line.amount_gross = updated["amount_gross"]
		line.annual_net = updated["annual_net"]
		line.annual_vat = updated["annual_vat"]
		line.annual_gross = updated["annual_gross"]
		line.save()

		updated_budget = frappe.get_doc("MPIT Budget", budget.name)
		self.assertEqual(updated_budget.total_amount_monthly, flt(350, 2))
		self.assertEqual(updated_budget.total_amount_annual, flt(4200, 2))
		self.assertEqual(updated_budget.total_amount_net, flt(4200, 2))
		self.assertEqual(updated_budget.total_amount_vat, flt(300, 2))
		self.assertEqual(updated_budget.total_amount_gross, flt(4500, 2))
