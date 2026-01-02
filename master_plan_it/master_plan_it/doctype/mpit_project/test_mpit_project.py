# Copyright (c) 2025, DOT and Contributors
# See license.txt

import frappe
from frappe.tests.utils import FrappeTestCase


class TestMPITProject(FrappeTestCase):
	def setUp(self):
		if not frappe.db.exists("MPIT Year", "2031"):
			frappe.get_doc({"doctype": "MPIT Year", "year": 2031, "start_date": "2031-01-01", "end_date": "2031-12-31"}).insert()
		if not frappe.db.exists("MPIT Cost Center", "Infrastructure CC"):
			frappe.get_doc({"doctype": "MPIT Cost Center", "cost_center_name": "Infrastructure CC", "is_group": 0, "is_active": 1}).insert()

	def test_requires_allocation_before_approval(self):
		doc = frappe.get_doc({
			"doctype": "MPIT Project",
			"title": "Test Project",
			"status": "Approved",
			"cost_center": "Infrastructure CC",
		})
		with self.assertRaises(frappe.ValidationError):
			doc.insert()

		doc.append("allocations", {"year": "2031", "cost_center": "Infrastructure CC", "planned_amount": 1000})
		doc.insert()  # should now succeed
