# Copyright (c) 2025, DOT and Contributors
# See license.txt

import frappe
from frappe.tests.utils import FrappeTestCase


class TestMPITProject(FrappeTestCase):
	def setUp(self):
		if not frappe.db.exists("MPIT Year", "2031"):
			frappe.get_doc({"doctype": "MPIT Year", "year": 2031, "start_date": "2031-01-01", "end_date": "2031-12-31"}).insert()
		if not frappe.db.exists("MPIT Cost Center", "Infrastructure CC"):
			frappe.get_doc({"doctype": "MPIT Cost Center", "cost_center_name": "Infrastructure CC", "is_group": 0}).insert()

	def test_approval_flow_guidance(self):
		"""Verify Project can be Approved without Allocations (v3 flow)."""
		doc = frappe.get_doc({
			"doctype": "MPIT Project",
			"title": "Test Project v3",
			"status": "Draft",
			"cost_center": "Infrastructure CC",
		}).insert()
		
		# Move to Approved without allocations -> Should succeed (no validation error)
		doc.status = "Approved"
		doc.save()
		
		self.assertEqual(doc.status, "Approved")
		
		# Note: We cannot easily assert msgprint in unit tests without mocking,
		# but the absence of ValidationError proves the fix.
