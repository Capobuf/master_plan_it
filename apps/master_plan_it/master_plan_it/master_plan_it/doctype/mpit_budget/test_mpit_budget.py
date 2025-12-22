# Copyright (c) 2025, DOT and Contributors
# See license.txt

import frappe
from frappe.tests.utils import FrappeTestCase
from frappe.utils import flt


class TestMPITBudget(FrappeTestCase):
	def setUp(self):
		super().setUp()
		self.year = self._ensure_year(2099)
		self.category = self._ensure_category("Totals Test Category")

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

	def _ensure_category(self, category_name: str):
		if not frappe.db.exists("MPIT Category", category_name):
			doc = frappe.get_doc({
				"doctype": "MPIT Category",
				"category_name": category_name,
				"is_group": 0
			})
			doc.insert(ignore_if_duplicate=True)
		return frappe.get_doc("MPIT Category", category_name)

	def test_totals_aggregated_from_lines(self):
		budget = frappe.get_doc({
			"doctype": "MPIT Budget",
			"year": self.year.name,
			"title": "Totals Test",
			"lines": [
				{
					"doctype": "MPIT Budget Line",
					"category": self.category.name,
					"amount": 100,
					"amount_includes_vat": 0,
					"vat_rate": 10,
					"recurrence_rule": "None"
				},
				{
					"doctype": "MPIT Budget Line",
					"category": self.category.name,
					"amount": 200,
					"amount_includes_vat": 0,
					"vat_rate": 5,
					"recurrence_rule": "None"
				}
			]
		})
		budget.insert()
		budget.reload()

		self.assertEqual(budget.total_amount_input, flt(300, 2))
		self.assertEqual(budget.total_amount_net, flt(300, 2))
		self.assertEqual(budget.total_amount_vat, flt(20, 2))
		self.assertEqual(budget.total_amount_gross, flt(320, 2))

	def test_totals_update_when_line_saved_individually(self):
		budget = frappe.get_doc({
			"doctype": "MPIT Budget",
			"year": self.year.name,
			"title": "Totals Child Save",
			"lines": [
				{
					"doctype": "MPIT Budget Line",
					"category": self.category.name,
					"amount": 100,
					"amount_includes_vat": 0,
					"vat_rate": 10,
					"recurrence_rule": "None"
				},
				{
					"doctype": "MPIT Budget Line",
					"category": self.category.name,
					"amount": 200,
					"amount_includes_vat": 0,
					"vat_rate": 5,
					"recurrence_rule": "None"
				}
			]
		})
		budget.insert()
		budget.reload()

		line = frappe.get_doc("MPIT Budget Line", budget.lines[0].name)
		line.amount = 150
		line.amount_net = 150
		line.amount_vat = 15
		line.amount_gross = 165
		line.save()

		updated_budget = frappe.get_doc("MPIT Budget", budget.name)
		self.assertEqual(updated_budget.total_amount_input, flt(350, 2))
		self.assertEqual(updated_budget.total_amount_net, flt(350, 2))
		self.assertEqual(updated_budget.total_amount_vat, flt(25, 2))
		self.assertEqual(updated_budget.total_amount_gross, flt(375, 2))
