# Copyright (c) 2025, DOT and Contributors
# See license.txt

import frappe
from frappe.tests.utils import FrappeTestCase


class TestMPITPlannedItem(FrappeTestCase):
	def test_enforce_horizon_guard_with_missing_dates(self):
		"""_enforce_horizon_flag should not crash on missing dates."""
		doc = frappe.new_doc("MPIT Planned Item")
		doc.spend_date = None
		doc.start_date = None
		doc.end_date = None

		doc._enforce_horizon_flag()
		self.assertEqual(doc.out_of_horizon, 0)
