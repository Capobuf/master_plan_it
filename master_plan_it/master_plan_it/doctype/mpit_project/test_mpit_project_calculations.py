# Copyright (c) 2025, DOT and Contributors
# See license.txt

import frappe
from frappe.tests.utils import FrappeTestCase
from master_plan_it.master_plan_it.doctype.mpit_project.mpit_project import MPITProject

class TestMPITProjectCalculations(FrappeTestCase):
	def setUp(self):
		# Ensure dependencies exist
		if not frappe.db.exists("MPIT Cost Center", "Test CC"):
			frappe.get_doc({"doctype": "MPIT Cost Center", "cost_center_name": "Test CC", "is_group": 0}).insert()

	def test_vat_split_logic_gross(self):
		"""Verify that Gross input (includes_vat=1) is correctly split to Net."""
		project = frappe.new_doc("MPIT Project")
		project.title = "Test VAT Gross"
		project.cost_center = "Test CC"
		
		# Allocation: 122 Gross, 22% VAT -> Expected 100 Net
		alloc = project.append("allocations", {})
		alloc.year = "2025"
		alloc.cost_center = "Test CC"
		alloc.planned_amount = 122.0
		alloc.planned_amount_includes_vat = 1 # Checked
		alloc.vat_rate = 22.0
		
		# Validation triggers computation
		project.save(ignore_permissions=True)
		
		# Check row calculations
		self.assertAlmostEqual(alloc.planned_amount_net, 100.0)
		self.assertAlmostEqual(alloc.planned_amount_vat, 22.0)
		self.assertAlmostEqual(alloc.planned_amount_gross, 122.0)
		
		# Check Total Project Net
		self.assertAlmostEqual(project.planned_total_net, 100.0)

	def test_vat_split_logic_net(self):
		"""Verify that Net input (includes_vat=0) is correctly treated as Net."""
		project = frappe.new_doc("MPIT Project")
		project.title = "Test VAT Net"
		project.cost_center = "Test CC"
		
		# Allocation: 100 Net, 22% VAT -> Expected 122 Gross
		alloc = project.append("allocations", {})
		alloc.year = "2025"
		alloc.cost_center = "Test CC"
		alloc.planned_amount = 100.0
		alloc.planned_amount_includes_vat = 0 # Unchecked
		alloc.vat_rate = 22.0
		
		project.save(ignore_permissions=True)
		
		# Check row calculations
		self.assertAlmostEqual(alloc.planned_amount_net, 100.0)
		self.assertAlmostEqual(alloc.planned_amount_vat, 22.0)
		self.assertAlmostEqual(alloc.planned_amount_gross, 122.0)
		
		# Check Total Project Net
		self.assertAlmostEqual(project.planned_total_net, 100.0)

	def test_fallback_recalculation(self):
		"""Verify that totals are correct even if row net values are missing (simulated fallback)."""
		project = frappe.new_doc("MPIT Project")
		project.title = "Test Fallback"
		project.cost_center = "Test CC"
		
		alloc = project.append("allocations", {})
		alloc.year = "2025"
		alloc.cost_center = "Test CC"
		alloc.planned_amount = 61.0
		alloc.planned_amount_includes_vat = 1 # Gross
		alloc.vat_rate = 22.0 # Net should be 50.0
		
		# Manually trigger save to compute initially
		project.save(ignore_permissions=True)
		self.assertAlmostEqual(project.planned_total_net, 50.0)
		
		# SIMULATE DATA CORRUPTION/MISSING CACHE: Clear safe fields
		alloc.planned_amount_net = None
		
		# Directly call _compute_project_totals which triggers fallback
		project._compute_project_totals()
		
		# Should still be 50.0 thanks to resilient recalculation using amounts module
		self.assertAlmostEqual(project.planned_total_net, 50.0)

