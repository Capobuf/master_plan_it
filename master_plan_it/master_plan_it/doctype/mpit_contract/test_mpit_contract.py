# Copyright (c) 2025, DOT and Contributors
# See license.txt

from datetime import date

import frappe
from frappe.tests.utils import FrappeTestCase
from frappe.utils import flt, now_datetime


class TestMPITContract(FrappeTestCase):
	def setUp(self):
		super().setUp()
		self.vendor = self._ensure_vendor("Test Contract Vendor")
		self.cost_center = self._ensure_cost_center("Test Contract CC")

	def _ensure_vendor(self, name: str):
		if not frappe.db.exists("MPIT Vendor", name):
			frappe.get_doc(
				{
					"doctype": "MPIT Vendor",
					"vendor_name": name,
				}
			).insert(ignore_if_duplicate=True)
		return frappe.get_doc("MPIT Vendor", name)

	def _ensure_cost_center(self, name: str):
		if not frappe.db.exists("MPIT Cost Center", name):
			frappe.get_doc(
				{
					"doctype": "MPIT Cost Center",
					"cost_center_name": name,
					"is_group": 0,
				}
			).insert(ignore_if_duplicate=True)
		return frappe.get_doc("MPIT Cost Center", name)

	def _make_contract(
		self,
		description_suffix: str,
		amount: float,
		billing_cycle: str = "Monthly",
		start_date: str = "2025-01-01",
	):
		"""Create a contract with a single term (terms are the source of truth)."""
		timestamp = now_datetime().strftime("%Y%m%d%H%M%S%f")
		description = f"Contract {description_suffix} {timestamp}"
		doc = frappe.get_doc(
			{
				"doctype": "MPIT Contract",
				"description": description,
				"vendor": self.vendor.name,
				"cost_center": self.cost_center.name,
				"auto_renew": 0,
				"start_date": start_date,
				"terms": [
					{
						"from_date": start_date,
						"amount": amount,
						"amount_includes_vat": 0,
						"vat_rate": 0,
						"billing_cycle": billing_cycle,
					}
				],
			}
		)

		doc.insert()
		doc.reload()
		return doc

	def test_monthly_amount_from_billing_cycle(self):
		"""Term monthly_amount_net is computed from billing_cycle."""
		monthly = self._make_contract("Monthly", amount=100, billing_cycle="Monthly")
		self.assertEqual(monthly.terms[0].monthly_amount_net, flt(100, 2))

		quarterly = self._make_contract("Quarterly", amount=1200, billing_cycle="Quarterly")
		# Quarterly: amount * 4 / 12 = 1200 * 4 / 12 = 400
		self.assertEqual(quarterly.terms[0].monthly_amount_net, flt(400, 2))

		annual = self._make_contract("Annual", amount=1200, billing_cycle="Annual")
		# Annual: amount / 12 = 1200 / 12 = 100
		self.assertEqual(annual.terms[0].monthly_amount_net, flt(100, 2))

	def test_auto_renew_contract_stays_active(self):
		"""Auto-renew contracts should not have Pending Renewal status."""
		contract = self._make_contract("AutoRenew", amount=100, billing_cycle="Monthly")
		contract.status = "Pending Renewal"
		contract.auto_renew = 1
		contract.save()
		contract.reload()
		self.assertEqual(contract.status, "Active")

	def test_contract_requires_at_least_one_term(self):
		"""Contract without terms should fail validation."""
		with self.assertRaises(frappe.ValidationError):
			frappe.get_doc(
				{
					"doctype": "MPIT Contract",
					"vendor": self.vendor.name,
					"cost_center": self.cost_center.name,
					"start_date": "2025-01-01",
					"terms": [],
				}
			).insert()

	def test_contract_requires_vendor(self):
		"""Contract without vendor should fail validation."""
		with self.assertRaises(frappe.ValidationError):
			frappe.get_doc(
				{
					"doctype": "MPIT Contract",
					"cost_center": self.cost_center.name,
					"terms": [
						{
							"from_date": "2025-01-01",
							"amount": 100,
							"billing_cycle": "Monthly",
							"vat_rate": 0,
						}
					],
				}
			).insert()

	def test_overlapping_terms_rejected(self):
		"""Overlapping date ranges should fail validation."""
		with self.assertRaises(frappe.ValidationError):
			frappe.get_doc(
				{
					"doctype": "MPIT Contract",
					"vendor": self.vendor.name,
					"cost_center": self.cost_center.name,
					"start_date": "2025-01-01",
					"terms": [
						{
							"from_date": "2025-01-01",
							"to_date": "2025-06-30",
							"amount": 100,
							"billing_cycle": "Monthly",
						},
						{
							"from_date": "2025-06-01",
							"amount": 120,
							"billing_cycle": "Monthly",
						},
					],
				}
			).insert()

	def test_annual_summary_computed(self):
		"""Annual summaries should reflect terms."""
		current_year = date.today().year
		contract = frappe.get_doc(
			{
				"doctype": "MPIT Contract",
				"vendor": self.vendor.name,
				"cost_center": self.cost_center.name,
				"start_date": f"{current_year}-01-01",
				"terms": [
					{
						"from_date": f"{current_year}-01-01",
						"amount": 100,
						"billing_cycle": "Monthly",
						"vat_rate": 0,
					}
				],
			}
		)
		contract.insert()
		# 12 months × 100 = 1200
		self.assertEqual(contract.annual_amount_current_year, 1200)

	def test_current_term_identified(self):
		"""Current term should be identified based on today's date."""
		current_year = date.today().year
		contract = frappe.get_doc(
			{
				"doctype": "MPIT Contract",
				"vendor": self.vendor.name,
				"cost_center": self.cost_center.name,
				"start_date": f"{current_year}-01-01",
				"terms": [
					{
						"from_date": f"{current_year}-01-01",
						"amount": 100,
						"billing_cycle": "Monthly",
					},
					{
						"from_date": f"{current_year + 1}-06-01",
						"amount": 150,
						"billing_cycle": "Monthly",
					},
				],
			}
		)
		contract.insert()
		# If we are in the current year, the active term is the first one
		self.assertEqual(contract.current_term_amount, 100)
		self.assertEqual(contract.current_term_billing_cycle, "Monthly")

	def test_open_ended_term_respects_status(self):
		"""Open-ended terms should not be treated as active when status is inactive."""
		current_year = date.today().year
		contract = frappe.get_doc(
			{
				"doctype": "MPIT Contract",
				"vendor": self.vendor.name,
				"cost_center": self.cost_center.name,
				"status": "Cancelled",
				"auto_renew": 0,
				"terms": [
					{
						"from_date": f"{current_year}-01-01",
						"amount": 100,
						"billing_cycle": "Monthly",
						"vat_rate": 0,
					}
				],
			}
		)
		contract.flags.skip_terms_auto_compute = True
		contract.insert()
		self.assertIsNone(contract.current_term_amount)
