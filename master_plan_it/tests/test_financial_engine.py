from __future__ import annotations

from uuid import uuid4

import frappe
from frappe.tests.utils import FrappeTestCase

from master_plan_it.master_plan_it.financial_engine import (
    get_actual_totals,
    get_contract_forecast_totals,
    get_cost_center_financial_summary,
    get_expense_forecast_totals,
    get_monthly_forecast_vs_actual,
    get_overview_dataset,
    get_plafond_document_totals,
    get_plafond_totals,
    get_project_financial_summary,
)


class TestFinancialEngine(FrappeTestCase):
    def setUp(self):
        frappe.set_user("Administrator")
        self.suffix = uuid4().hex[:6].upper()
        self.year = ensure_year(2030)
        self.cost_center = ensure_cost_center(f"CC-FE-{self.suffix}")
        self.vendor = ensure_vendor(f"Vendor FE {self.suffix}")
        self.project = ensure_project(f"Project FE {self.suffix}", self.cost_center)

    def test_contract_forecast_uses_terms_and_zero_when_terms_do_not_overlap_year(self):
        contract = make_contract_with_terms(
            description=f"Contract Terms {self.suffix}",
            vendor=self.vendor,
            cost_center=self.cost_center,
            terms=[
                term_row("2030-01-01", "2030-12-31", 1200, "Annual"),
            ],
        )
        totals = get_contract_forecast_totals(self.year, cost_center=self.cost_center, contract=contract.name)
        self.assertEqual(totals["contract_forecast_total"], 1200)

        off_year_contract = make_contract_with_terms(
            description=f"Contract Terms Off Year {self.suffix}",
            vendor=self.vendor,
            cost_center=self.cost_center,
            terms=[
                term_row("2029-01-01", "2029-12-31", 900, "Annual"),
            ],
        )
        off_year = get_contract_forecast_totals(self.year, cost_center=self.cost_center, contract=off_year_contract.name)
        self.assertEqual(off_year["contract_forecast_total"], 0)

    def test_contract_header_fallback_applies_only_without_terms(self):
        contract = frappe.get_doc(
            {
                "doctype": "MPIT Contract",
                "description": f"Contract Header {self.suffix}",
                "vendor": self.vendor,
                "cost_center": self.cost_center,
                "start_date": "2030-01-01",
                "end_date": "2030-12-31",
                "current_amount": 100,
                "current_amount_includes_vat": 0,
                "vat_rate": 22,
                "billing_cycle": "Monthly",
            }
        )
        contract.insert()
        totals = get_contract_forecast_totals(self.year, cost_center=self.cost_center, contract=contract.name)
        self.assertEqual(totals["contract_forecast_total"], 1200)

    def test_expense_forecast_and_actual_respect_row_and_doc_states(self):
        expense_open = make_expense(
            year=self.year,
            cost_center=self.cost_center,
            project=self.project,
            is_extra=1,
            rows=[
                expense_row("Estimate", 100, "Active", spend_date="2030-01-10"),
                expense_row("Quote", 200, "Active", spend_date="2030-02-10"),
                expense_row("Actual", 50, "Active", spend_date="2030-03-10"),
                expense_row("Quote", 999, "Replaced", spend_date="2030-04-10"),
            ],
        )
        self.assertIsNotNone(expense_open.name)

        expense_closed = make_expense(
            year=self.year,
            cost_center=self.cost_center,
            project=self.project,
            workflow_state="Closed",
            is_extra=1,
            rows=[expense_row("Actual", 20, "Active", spend_date="2030-05-10")],
        )
        self.assertIsNotNone(expense_closed.name)

        expense_cancelled = make_expense(
            year=self.year,
            cost_center=self.cost_center,
            project=self.project,
            workflow_state="Cancelled",
            is_extra=1,
            rows=[expense_row("Actual", 999, "Active", spend_date="2030-06-10")],
        )
        self.assertIsNotNone(expense_cancelled.name)

        forecast = get_expense_forecast_totals(self.year, project=self.project)
        actual = get_actual_totals(self.year, project=self.project)

        self.assertEqual(forecast["estimate_total"], 100)
        self.assertEqual(forecast["quote_total"], 200)
        self.assertEqual(forecast["expense_forecast_total"], 300)
        self.assertEqual(actual["actual_total"], 70)

    def test_plafond_totals_split_consumed_and_extra(self):
        plafond = make_expense(
            year=self.year,
            cost_center=self.cost_center,
            expense_kind="Plafond",
            rows=[
                expense_row("Actual", 500, "Active", spend_date="2030-01-05"),
                expense_row("Actual", -100, "Active", spend_date="2030-01-06"),
            ],
        )

        make_expense(
            year=self.year,
            cost_center=self.cost_center,
            project=self.project,
            uses_plafond=1,
            is_extra=0,
            plafond_expense=plafond.name,
            rows=[expense_row("Actual", 250, "Active", spend_date="2030-02-05")],
        )
        make_expense(
            year=self.year,
            cost_center=self.cost_center,
            project=self.project,
            is_extra=1,
            rows=[expense_row("Actual", 80, "Active", spend_date="2030-02-10")],
        )

        plafond_totals = get_plafond_totals(self.year, cost_center=self.cost_center)
        actual_totals = get_actual_totals(self.year, cost_center=self.cost_center, project=self.project)

        self.assertEqual(plafond_totals["plafond_total"], 400)
        self.assertEqual(plafond_totals["plafond_consumed"], 250)
        self.assertEqual(plafond_totals["plafond_remaining"], 150)
        self.assertEqual(plafond_totals["plafond_over"], 0)
        self.assertEqual(actual_totals["actual_on_plafond"], 250)
        self.assertEqual(actual_totals["actual_extra"], 80)

    def test_monthly_dataset_and_project_summary_share_same_totals(self):
        make_expense(
            year=self.year,
            cost_center=self.cost_center,
            project=self.project,
            is_extra=1,
            rows=[
                expense_row(
                    "Estimate",
                    300,
                    "Active",
                    spend_date=None,
                    start_date="2030-01-01",
                    end_date="2030-03-31",
                    distribution="all",
                ),
                expense_row("Actual", 90, "Active", spend_date="2030-02-15"),
            ],
        )

        monthly = get_monthly_forecast_vs_actual(self.year, project=self.project)
        project_summary = get_project_financial_summary(self.project, year=self.year)

        self.assertEqual(monthly["totals"]["forecast_total"], project_summary["forecast_total_net"])
        self.assertEqual(monthly["totals"]["actual_total"], project_summary["actual_total_net"])

    def test_standalone_extra_is_included_in_cost_center_and_overview_totals(self):
        make_expense(
            year=self.year,
            cost_center=self.cost_center,
            is_extra=1,
            rows=[expense_row("Actual", 60, "Active", spend_date="2030-03-15")],
        )

        cost_center_summary = get_cost_center_financial_summary(self.year, self.cost_center)
        overview = get_overview_dataset(self.year, cost_center=self.cost_center)

        self.assertEqual(cost_center_summary["actual_extra"], 60)
        self.assertEqual(cost_center_summary["actual_total"], 60)
        self.assertEqual(overview["summary"]["actual_extra"], 60)
        self.assertEqual(overview["summary"]["actual_total"], 60)

    def test_standalone_uses_plafond_consumes_only_selected_document(self):
        plafond_a = make_expense(
            year=self.year,
            cost_center=self.cost_center,
            expense_kind="Plafond",
            rows=[expense_row("Actual", 400, "Active", spend_date="2030-01-05")],
        )
        plafond_b = make_expense(
            year=self.year,
            cost_center=self.cost_center,
            expense_kind="Plafond",
            workflow_state="Cancelled",
            rows=[expense_row("Actual", 700, "Active", spend_date="2030-01-06")],
        )
        self.assertIsNotNone(plafond_b.name)

        make_expense(
            year=self.year,
            cost_center=self.cost_center,
            uses_plafond=1,
            is_extra=0,
            plafond_expense=plafond_a.name,
            rows=[expense_row("Actual", 125, "Active", spend_date="2030-02-05")],
        )

        totals_a = get_plafond_document_totals(plafond_a.name)
        totals_b = get_plafond_document_totals(plafond_b.name)
        aggregate = get_plafond_totals(self.year, cost_center=self.cost_center)

        self.assertEqual(totals_a["plafond_total"], 400)
        self.assertEqual(totals_a["plafond_consumed"], 125)
        self.assertEqual(totals_a["plafond_remaining"], 275)
        self.assertEqual(totals_b["plafond_consumed"], 0)
        self.assertEqual(aggregate["plafond_total"], 400)
        self.assertEqual(aggregate["plafond_consumed"], 125)

    def test_cross_cost_center_plafond_consumption_semantics(self):
        year = ensure_year(2030)
        cc_funding = ensure_cost_center(f"CC-FUNDING-{self.suffix}")
        cc_infra = ensure_cost_center(f"CC-INFRA-{self.suffix}")

        plafond = make_expense(
            year=year,
            cost_center=cc_funding,
            expense_kind="Plafond",
            rows=[expense_row("Actual", 1000, "Active", spend_date="2030-01-10")],
        )
        make_expense(
            year=year,
            cost_center=cc_infra,
            uses_plafond=1,
            is_extra=0,
            plafond_expense=plafond.name,
            rows=[expense_row("Actual", 250, "Active", spend_date="2030-02-10")],
        )

        actual_infra = get_actual_totals(year, cost_center=cc_infra)
        actual_funding = get_actual_totals(year, cost_center=cc_funding)
        plafond_funding = get_plafond_totals(year, cost_center=cc_funding)
        summary_funding = get_cost_center_financial_summary(year, cc_funding)

        self.assertEqual(actual_infra["actual_on_plafond"], 250)
        self.assertEqual(actual_funding["actual_on_plafond"], 0)
        self.assertEqual(plafond_funding["plafond_total"], 1000)
        self.assertEqual(plafond_funding["plafond_consumed"], 250)
        self.assertEqual(plafond_funding["plafond_remaining"], 750)
        self.assertEqual(summary_funding["plafond_consumed"], 250)


def term_row(from_date: str, to_date: str, amount: float, billing_cycle: str) -> dict:
    return {
        "doctype": "MPIT Contract Term",
        "from_date": from_date,
        "to_date": to_date,
        "amount": amount,
        "amount_includes_vat": 0,
        "vat_rate": 22,
        "billing_cycle": billing_cycle,
    }


def make_contract_with_terms(description: str, vendor: str, cost_center: str, terms: list[dict]):
    doc = frappe.get_doc(
        {
            "doctype": "MPIT Contract",
            "description": description,
            "vendor": vendor,
            "cost_center": cost_center,
            "terms": terms,
        }
    )
    doc.insert()
    return doc


def make_expense(
    year: str,
    cost_center: str,
    project: str | None = None,
    contract: str | None = None,
    expense_kind: str = "Ordinary",
    workflow_state: str = "Open",
    uses_plafond: int = 0,
    is_extra: int = 1,
    plafond_expense: str | None = None,
    rows: list[dict] | None = None,
):
    payload = {
        "doctype": "MPIT Expense",
        "expense_title": f"Expense {uuid4().hex[:8]}",
        "expense_kind": expense_kind,
        "workflow_state": workflow_state,
        "year": year,
        "cost_center": cost_center,
        "project": project if expense_kind == "Ordinary" else None,
        "contract": contract if expense_kind == "Ordinary" else None,
        "uses_plafond": uses_plafond if expense_kind == "Ordinary" else 0,
        "is_extra": is_extra if expense_kind == "Ordinary" else 0,
        "plafond_expense": plafond_expense if expense_kind == "Ordinary" else None,
        "rows": rows or [],
    }
    doc = frappe.get_doc(payload)
    doc.insert()
    return doc


def expense_row(
    phase: str,
    amount: float,
    row_state: str,
    spend_date: str | None = None,
    start_date: str | None = None,
    end_date: str | None = None,
    distribution: str | None = None,
) -> dict:
    return {
        "doctype": "MPIT Expense Row",
        "row_description": "row",
        "row_phase": phase,
        "row_state": row_state,
        "amount": amount,
        "amount_includes_vat": 0,
        "vat_rate": 22,
        "spend_date": spend_date,
        "start_date": start_date,
        "end_date": end_date,
        "distribution": distribution,
    }


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


def ensure_vendor(name: str) -> str:
    existing = frappe.db.get_value("MPIT Vendor", {"vendor_name": name}, "name")
    if existing:
        return existing

    doc = frappe.get_doc({"doctype": "MPIT Vendor", "vendor_name": name})
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
