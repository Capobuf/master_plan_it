# Copyright (c) 2025, DOT and Contributors
# See license.txt

import frappe
from frappe.tests.utils import FrappeTestCase


class TestMPITYear(FrappeTestCase):
	def test_year_date_overlap_blocked(self):
		"""Overlapping year ranges must be rejected."""
		base = 9000 + (hash(frappe.generate_hash(length=8)) % 500)
		year_a = base
		year_b = base + 1

		doc_a = frappe.get_doc({
			"doctype": "MPIT Year",
			"year": year_a,
			"start_date": f"{year_a}-01-01",
			"end_date": f"{year_a}-12-31",
		}).insert()

		with self.assertRaises(frappe.ValidationError):
			frappe.get_doc({
				"doctype": "MPIT Year",
				"year": year_b,
				"start_date": f"{year_a}-06-01",
				"end_date": f"{year_b}-05-31",
			}).insert()

		doc_a.delete()
