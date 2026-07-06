from __future__ import annotations

from pathlib import Path
from uuid import uuid4

import frappe
from frappe.tests.utils import FrappeTestCase

from master_plan_it.master_plan_it.report.mpit_budget_variance.mpit_budget_variance import (
    execute as run_budget_variance,
)
from master_plan_it.master_plan_it.report.mpit_economic_detail.mpit_economic_detail import (
    execute as run_economic_detail,
)
from master_plan_it.master_plan_it.report.mpit_economic_position.mpit_economic_position import (
    execute as run_economic_position,
)
from master_plan_it.master_plan_it.report.mpit_renewals_and_commitments.mpit_renewals_and_commitments import (
    execute as run_renewals,
)
from master_plan_it.master_plan_it.report.mpit_what_if_scenario.mpit_what_if_scenario import (
    execute as run_what_if,
)
from master_plan_it.master_plan_it.report.mpit_year_comparison.mpit_year_comparison import (
    execute as run_year_comparison,
)
from master_plan_it.master_plan_it.report.mpit_year_end_forecast.mpit_year_end_forecast import (
    execute as run_year_end_forecast,
)


NEW_REPORTS = {
    "mpit_budget_variance": "MPIT Budget Variance",
    "mpit_economic_detail": "MPIT Economic Detail",
    "mpit_economic_position": "MPIT Economic Position",
    "mpit_renewals_and_commitments": "MPIT Renewals And Commitments",
    "mpit_what_if_scenario": "MPIT What If Scenario",
    "mpit_year_comparison": "MPIT Year Comparison",
    "mpit_year_end_forecast": "MPIT Year End Forecast",
}


class TestReportingRefactor(FrappeTestCase):
    def setUp(self):
        frappe.set_user("Administrator")
        self.suffix = uuid4().hex[:6].upper()
        self.year = ensure_year(2031)
        self.previous_year = ensure_year(2030)
        self.cost_center = ensure_cost_center(f"CC-REP-{self.suffix}")
        self.project = ensure_project(f"Project REP {self.suffix}", self.cost_center)
        self.vendor = ensure_vendor(f"Vendor REP {self.suffix}")
        self.expense = frappe.get_doc(
            {
                "doctype": "MPIT Expense",
                "expense_title": f"Expense REP {self.suffix}",
                "expense_kind": "Ordinary",
                "year": self.year,
                "cost_center": self.cost_center,
                "project": self.project,
                "uses_plafond": 0,
                "is_extra": 0,
                "rows": [
                    {
                        "doctype": "MPIT Expense Row",
                        "row_description": "Forecast row",
                        "row_phase": "Estimate",
                        "row_state": "Active",
                        "vendor": self.vendor,
                        "amount": 120,
                        "amount_includes_vat": 0,
                        "vat_rate": 22,
                        "spend_date": "2031-01-10",
                    },
                    {
                        "doctype": "MPIT Expense Row",
                        "row_description": "Actual row",
                        "row_phase": "Actual",
                        "row_state": "Active",
                        "vendor": self.vendor,
                        "amount": 50,
                        "amount_includes_vat": 0,
                        "vat_rate": 22,
                        "spend_date": "2031-02-10",
                    },
                ],
            }
        ).insert()

    def test_new_reports_execute(self):
        filters = {"year": self.year, "cost_center": self.cost_center}
        report_calls = [
            run_economic_position,
            run_year_end_forecast,
            run_budget_variance,
            run_what_if,
            run_economic_detail,
            lambda f: run_year_comparison({"year_a": self.previous_year, "year_b": self.year}),
            run_renewals,
        ]

        for run_report in report_calls:
            columns, data, _message, _chart, report_summary = run_report(filters)
            self.assertTrue(columns)
            self.assertIsInstance(data, list)
            self.assertIsInstance(report_summary, list)

    def test_economic_position_uses_new_dataset_shape(self):
        columns, data, _message, _chart, report_summary = run_economic_position(
            {"year": self.year, "cost_center": self.cost_center}
        )
        fieldnames = {column["fieldname"] for column in columns}
        self.assertIn("year_end_forecast", fieldnames)
        self.assertIn("remaining_or_over", fieldnames)
        self.assertTrue(data)
        self.assertIn("available_budget", data[0])
        self.assertTrue(report_summary)

    def test_what_if_switches_columns_for_detail(self):
        summary_columns, *_ = run_what_if({"year": self.year, "cost_center": self.cost_center})
        detail_columns, *_ = run_what_if(
            {"year": self.year, "cost_center": self.cost_center, "show_detail": 1}
        )
        self.assertIn("scenario_total", {column["fieldname"] for column in summary_columns})
        self.assertIn("source_document", {column["fieldname"] for column in detail_columns})


def test_new_report_folders_have_native_files():
    report_root = Path(__file__).resolve().parents[1] / "master_plan_it" / "report"
    for slug in NEW_REPORTS:
        report_dir = report_root / slug
        assert report_dir.exists()
        for suffix in ("py", "js", "json", "html"):
            assert (report_dir / f"{slug}.{suffix}").exists()
        assert (report_dir / "__init__.py").exists()


def test_report_templates_do_not_contain_business_calculation_patterns():
    report_root = Path(__file__).resolve().parents[1] / "master_plan_it" / "report"
    forbidden = ("amount_net +", "actual_total +", "forecast_remaining +", "uses_plafond", "is_extra")
    for slug in NEW_REPORTS:
        html = (report_root / slug / f"{slug}.html").read_text()
        for token in forbidden:
            assert token not in html


def ensure_year(year: int) -> str:
    name = str(year)
    if not frappe.db.exists("MPIT Year", name):
        frappe.get_doc(
            {
                "doctype": "MPIT Year",
                "year": year,
                "start_date": f"{year}-01-01",
                "end_date": f"{year}-12-31",
                "is_active": 1,
            }
        ).insert()
    return name


def ensure_cost_center(name: str) -> str:
    if not frappe.db.exists("MPIT Cost Center", name):
        frappe.get_doc(
            {
                "doctype": "MPIT Cost Center",
                "cost_center_name": name,
                "is_group": 0,
            }
        ).insert()
    return name


def ensure_project(title: str, cost_center: str) -> str:
    existing = frappe.db.get_value("MPIT Project", {"title": title}, "name")
    if existing:
        return existing
    return frappe.get_doc(
        {
            "doctype": "MPIT Project",
            "title": title,
            "cost_center": cost_center,
            "workflow_state": "Approved",
        }
    ).insert().name


def ensure_vendor(name: str) -> str:
    if not frappe.db.exists("MPIT Vendor", name):
        frappe.get_doc(
            {
                "doctype": "MPIT Vendor",
                "vendor_name": name,
                "is_active": 1,
            }
        ).insert()
    return name
