# Copyright (c) 2025, DOT and Contributors
# See license.txt

import frappe
import pytest
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
					"is_active": 1,
				}
			).insert(ignore_if_duplicate=True)
		return frappe.get_doc("MPIT Cost Center", name)

	def _make_contract(
		self,
		title_suffix: str,
		amount: float,
		billing_cycle: str = "Monthly",
		spread_months: int | None = None,
		rate_rows: list[dict] | None = None,
	):
		timestamp = now_datetime().strftime("%Y%m%d%H%M%S%f")
		title = f"Contract {title_suffix} {timestamp}"
		doc = frappe.get_doc(
			{
				"doctype": "MPIT Contract",
				"title": title,
				"vendor": self.vendor.name,
				"cost_center": self.cost_center.name,
				"auto_renew": 0,
				"current_amount": amount,
				"current_amount_includes_vat": 0,
				"vat_rate": 0,
				"billing_cycle": billing_cycle,
			}
		)

		if spread_months:
			doc.spread_months = spread_months
			doc.spread_start_date = "2099-01-01"

		if rate_rows:
			doc.rate_schedule = rate_rows

		doc.insert()
		doc.reload()
		return doc

	def test_monthly_amount_from_billing_cycle(self):
		monthly = self._make_contract("Monthly", amount=100, billing_cycle="Monthly")
		self.assertEqual(monthly.monthly_amount_net, flt(100, 2))

		quarterly = self._make_contract("Quarterly", amount=1200, billing_cycle="Quarterly")
		self.assertEqual(quarterly.monthly_amount_net, flt(400, 2))

		annual = self._make_contract("Annual", amount=1200, billing_cycle="Annual")
		self.assertEqual(annual.monthly_amount_net, flt(100, 2))

	def test_monthly_amount_respects_spread_months(self):
		self.skipTest("Spread_months legacy logic removed in Budget Engine v3")
		contract = self._make_contract("Spread", amount=1200, spread_months=12)
		self.assertEqual(contract.monthly_amount_net, flt(100, 2))

	def test_monthly_amount_omitted_for_rate_schedule(self):
		self.skipTest("Rate schedule legacy logic removed in Budget Engine v3")
		contract = self._make_contract(
			"RateSchedule",
			amount=0,
			rate_rows=[
				{
					"doctype": "MPIT Contract Rate",
					"effective_from": "2099-01-01",
					"amount": 100,
					"amount_includes_vat": 0,
					"vat_rate": 0,
				}
			],
		)
		self.assertIsNone(contract.monthly_amount_net)

	def test_auto_renew_contract_stays_active(self):
		contract = self._make_contract("AutoRenew", amount=100, billing_cycle="Monthly")
		contract.status = "Pending Renewal"
		contract.auto_renew = 1
		contract.save()
		contract.reload()
		self.assertEqual(contract.status, "Active")
