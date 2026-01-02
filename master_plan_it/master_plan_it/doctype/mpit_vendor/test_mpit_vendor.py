# Copyright (c) 2025, DOT and Contributors
# See license.txt

import frappe
from frappe.tests.utils import FrappeTestCase


class TestMPITVendor(FrappeTestCase):
	def test_quick_entry_populates_vendor_name(self):
		"""Simulate quick entry where the typed value is passed as __newname."""
		v = frappe.get_doc({"doctype": "MPIT Vendor", "__newname": "Quick Vendor"}).insert()
		self.assertEqual(v.vendor_name, "Quick Vendor")
