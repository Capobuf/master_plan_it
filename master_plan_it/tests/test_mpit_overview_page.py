from __future__ import annotations

from uuid import uuid4

import frappe
from frappe.tests.utils import FrappeTestCase

from master_plan_it.master_plan_it.financial_engine import (
    get_overview_buildup_dataset,
    get_overview_dataset,
    get_overview_lines_dataset,
)
from master_plan_it.master_plan_it.page.mpit_overview.mpit_overview import get_overview_page_data


class TestMPITOverviewPage(FrappeTestCase):
    def setUp(self):
        frappe.set_user("Administrator")
        self.suffix = uuid4().hex[:6].upper()
        self.year = ensure_year(2034)
        self.cost_center = ensure_cost_center(f"CC-PAGE-{self.suffix}")
        self.vendor = ensure_vendor(f"Vendor PAGE {self.suffix}")
        self.project = ensure_project(f"Project PAGE {self.suffix}", self.cost_center)

        self.contract = frappe.get_doc(
            {
                "doctype": "MPIT Contract",
                "description": f"Contract PAGE {self.suffix}",
                "vendor": self.vendor,
                "status": "Active",
                "cost_center": self.cost_center,
                "start_date": "2034-01-01",
                "end_date": "2034-12-31",
                "billing_cycle": "Monthly",
                "current_amount": 200,
                "current_amount_net": 200,
                "current_amount_includes_vat": 0,
                "vat_rate": 22,
            }
        ).insert(ignore_permissions=True)

        self.plafond = frappe.get_doc(
            {
                "doctype": "MPIT Expense",
                "expense_title": f"Plafond PAGE {self.suffix}",
                "expense_kind": "Plafond",
                "workflow_state": "Open",
                "year": self.year,
                "cost_center": self.cost_center,
                "rows": [
                    {
                        "doctype": "MPIT Expense Row",
                        "row_description": "Plafond row",
                        "row_phase": "Actual",
                        "row_state": "Active",
                        "amount": 1000,
                        "amount_includes_vat": 0,
                        "vat_rate": 0,
                        "spend_date": "2034-01-15",
                    }
                ],
            }
        ).insert(ignore_permissions=True)

        frappe.get_doc(
            {
                "doctype": "MPIT Expense",
                "expense_title": f"Expense PAGE {self.suffix}",
                "expense_kind": "Ordinary",
                "workflow_state": "Open",
                "year": self.year,
                "cost_center": self.cost_center,
                "project": self.project,
                "vendor": self.vendor,
                "uses_plafond": 1,
                "plafond_expense": self.plafond.name,
                "is_extra": 0,
                "rows": [
                    {
                        "doctype": "MPIT Expense Row",
                        "row_description": "Estimate row",
                        "row_phase": "Estimate",
                        "row_state": "Active",
                        "amount": 300,
                        "amount_includes_vat": 0,
                        "vat_rate": 0,
                        "spend_date": "2034-02-01",
                    },
                    {
                        "doctype": "MPIT Expense Row",
                        "row_description": "Actual row",
                        "row_phase": "Actual",
                        "row_state": "Active",
                        "amount": 250,
                        "amount_includes_vat": 0,
                        "vat_rate": 0,
                        "spend_date": "2034-03-01",
                    },
                ],
            }
        ).insert(ignore_permissions=True)

    def test_page_payload_matches_engine_for_overview_and_buildup(self):
        filters = {"year": self.year, "cost_center": self.cost_center}
        payload = get_overview_page_data(filters=filters, include_lines=0)

        expected_overview = get_overview_dataset(self.year, cost_center=self.cost_center)
        expected_buildup = get_overview_buildup_dataset(
            self.year,
            cost_center=self.cost_center,
            section_scope="All",
            contract=None,
            project=None,
            vendor=None,
            show_zero_rows=False,
        )

        self.assertEqual(payload["filters"]["year"], self.year)
        self.assertEqual(payload["overview"]["summary"], expected_overview["summary"])
        self.assertEqual(payload["buildup"]["summary"], expected_buildup["summary"])
        self.assertFalse(payload["lines"]["loaded"])
        self.assertEqual(payload["lines"]["rows"], [])

    def test_page_payload_includes_lines_only_on_demand(self):
        filters = {
            "year": self.year,
            "cost_center": self.cost_center,
            "section_scope": "Expenses",
            "expense_phase": "Actual",
            "project": self.project,
        }
        payload = get_overview_page_data(filters=filters, include_lines=1)
        expected_lines = get_overview_lines_dataset(
            self.year,
            cost_center=self.cost_center,
            section_scope="Expenses",
            expense_phase="Actual",
            contract=None,
            project=self.project,
            vendor=None,
            show_zero_rows=False,
        )

        self.assertTrue(payload["lines"]["loaded"])
        self.assertEqual(payload["lines"]["summary"], expected_lines["summary"])
        self.assertEqual(len(payload["lines"]["rows"]), len(expected_lines["rows"]))

    def test_page_payload_normalizes_invalid_scope_filters(self):
        payload = get_overview_page_data(
            filters={
                "year": self.year,
                "cost_center": self.cost_center,
                "section_scope": "Invalid Scope",
                "expense_phase": "Invalid Phase",
            },
            include_lines=0,
        )

        self.assertEqual(payload["filters"]["section_scope"], "All")
        self.assertEqual(payload["filters"]["expense_phase"], "All")


def ensure_year(year: int) -> str:
    if frappe.db.exists("MPIT Year", str(year)):
        return str(year)

    return frappe.get_doc(
        {
            "doctype": "MPIT Year",
            "year": year,
            "start_date": f"{year}-01-01",
            "end_date": f"{year}-12-31",
        }
    ).insert(ignore_permissions=True).name


def ensure_cost_center(name: str) -> str:
    if frappe.db.exists("MPIT Cost Center", name):
        return name

    return frappe.get_doc(
        {
            "doctype": "MPIT Cost Center",
            "cost_center_name": name,
            "is_group": 0,
            "parent_mpit_cost_center": "All Cost Centers",
        }
    ).insert(ignore_permissions=True).name


def ensure_vendor(name: str) -> str:
    existing = frappe.db.get_value("MPIT Vendor", {"vendor_name": name}, "name")
    if existing:
        return existing

    return frappe.get_doc({"doctype": "MPIT Vendor", "vendor_name": name}).insert(ignore_permissions=True).name


def ensure_project(title: str, cost_center: str) -> str:
    existing = frappe.db.get_value("MPIT Project", {"title": title}, "name")
    if existing:
        return existing

    return frappe.get_doc(
        {
            "doctype": "MPIT Project",
            "title": title,
            "workflow_state": "Open",
            "cost_center": cost_center,
        }
    ).insert(ignore_permissions=True).name
