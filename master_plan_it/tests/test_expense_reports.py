from __future__ import annotations

from uuid import uuid4

import frappe
from frappe.tests.utils import FrappeTestCase

from master_plan_it.master_plan_it.report.mpit_expenses.mpit_expenses import execute as run_expenses
from master_plan_it.master_plan_it.report.mpit_monthly_plan.mpit_monthly_plan import execute as run_monthly
from master_plan_it.master_plan_it.report.mpit_overview.mpit_overview import execute as run_overview
from master_plan_it.master_plan_it.report.mpit_project_forecast_vs_actual.mpit_project_forecast_vs_actual import (
    execute as run_project_report,
)


class TestExpenseReports(FrappeTestCase):
    def setUp(self):
        frappe.set_user("Administrator")
        self.suffix = uuid4().hex[:6].upper()
        self.year = ensure_year(2031)
        self.cost_center = ensure_cost_center(f"CC-REP-{self.suffix}")
        self.project = ensure_project(f"Project REP {self.suffix}", self.cost_center)

        expense = frappe.get_doc(
            {
                "doctype": "MPIT Expense",
                "expense_title": f"Expense REP {self.suffix}",
                "expense_kind": "Ordinary",
                "workflow_state": "Open",
                "year": self.year,
                "cost_center": self.cost_center,
                "project": self.project,
                "uses_plafond": 0,
                "is_extra": 1,
                "rows": [
                    {
                        "doctype": "MPIT Expense Row",
                        "row_description": "Estimate",
                        "row_phase": "Estimate",
                        "row_state": "Active",
                        "amount": 120,
                        "amount_includes_vat": 0,
                        "vat_rate": 22,
                        "spend_date": "2031-01-10",
                    },
                    {
                        "doctype": "MPIT Expense Row",
                        "row_description": "Actual",
                        "row_phase": "Actual",
                        "row_state": "Active",
                        "amount": 50,
                        "amount_includes_vat": 0,
                        "vat_rate": 22,
                        "spend_date": "2031-02-10",
                    },
                ],
            }
        )
        expense.insert()

    def test_overview_report_runs_on_expense_engine(self):
        columns, data, *_rest = run_overview({"year": self.year, "cost_center": self.cost_center})
        self.assertTrue(columns)
        self.assertTrue(data)
        self.assertIn("forecast_total", data[0])
        self.assertIn("actual_total", data[0])

    def test_monthly_plan_report_runs_on_expense_engine(self):
        columns, data, *_rest = run_monthly({"year": self.year, "project": self.project})
        self.assertTrue(columns)
        self.assertEqual(len(data), 12)
        self.assertIn("forecast", data[0])
        self.assertIn("actual", data[0])

    def test_expenses_report_runs(self):
        columns, data = run_expenses({"year": self.year, "cost_center": self.cost_center})
        self.assertTrue(columns)
        self.assertTrue(data)
        self.assertEqual(data[0]["year"], self.year)

    def test_project_forecast_vs_actual_report_runs(self):
        columns, data = run_project_report({"year": self.year, "cost_center": self.cost_center})
        self.assertTrue(columns)
        self.assertTrue(data)
        self.assertIn("variance", data[0])


def ensure_year(year: int) -> str:
    if frappe.db.exists("MPIT Year", str(year)):
        return str(year)

    doc = frappe.get_doc(
        {
            "doctype": "MPIT Year",
            "year": year,
            "start_date": f"{year}-01-01",
            "end_date": f"{year}-12-31",
        }
    )
    doc.insert(ignore_permissions=True)
    return doc.name


def ensure_cost_center(name: str) -> str:
    if frappe.db.exists("MPIT Cost Center", name):
        return name

    doc = frappe.get_doc(
        {
            "doctype": "MPIT Cost Center",
            "cost_center_name": name,
            "is_group": 0,
            "parent_mpit_cost_center": "All Cost Centers",
        }
    )
    doc.insert(ignore_permissions=True)
    return doc.name


def ensure_project(title: str, cost_center: str) -> str:
    existing = frappe.db.get_value("MPIT Project", {"title": title}, "name")
    if existing:
        return existing

    doc = frappe.get_doc(
        {
            "doctype": "MPIT Project",
            "title": title,
            "workflow_state": "Open",
            "cost_center": cost_center,
        }
    )
    doc.insert(ignore_permissions=True)
    return doc.name
