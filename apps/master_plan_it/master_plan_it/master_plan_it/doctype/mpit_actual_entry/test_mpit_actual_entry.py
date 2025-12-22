# Copyright (c) 2025, DOT and Contributors
# See license.txt

import frappe
from frappe.tests.utils import FrappeTestCase


class TestMPITActualEntry(FrappeTestCase):
	def setUp(self):
		# Ensure supporting records exist for tests
		if not frappe.db.exists("MPIT Year", "2030"):
			frappe.get_doc({"doctype": "MPIT Year", "year": 2030, "start_date": "2030-01-01", "end_date": "2030-12-31"}).insert()
		if not frappe.db.exists("MPIT Category", "Test Category"):
			frappe.get_doc({"doctype": "MPIT Category", "category_name": "Test Category", "is_active": 1}).insert()

	def test_year_is_derived_from_posting_date(self):
		doc = frappe.get_doc({
			"doctype": "MPIT Actual Entry",
			"posting_date": "2030-05-10",
			"category": "Test Category",
			"amount": 123.45,
		})
		doc.insert()
		self.assertEqual(doc.year, "2030")
