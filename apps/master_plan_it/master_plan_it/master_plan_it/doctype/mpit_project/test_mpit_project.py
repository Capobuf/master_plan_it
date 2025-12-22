# Copyright (c) 2025, DOT and Contributors
# See license.txt

import frappe
from frappe.tests.utils import FrappeTestCase


class TestMPITProject(FrappeTestCase):
	def setUp(self):
		if not frappe.db.exists("MPIT Year", "2031"):
			frappe.get_doc({"doctype": "MPIT Year", "year": 2031, "start_date": "2031-01-01", "end_date": "2031-12-31"}).insert()

	def test_requires_allocation_before_approval(self):
		doc = frappe.get_doc({
			"doctype": "MPIT Project",
			"title": "Test Project",
			"status": "Approved",
		})
		with self.assertRaises(frappe.ValidationError):
			doc.insert()

		doc.append("allocations", {"year": "2031", "planned_amount": 1000})
		doc.insert()  # should now succeed
