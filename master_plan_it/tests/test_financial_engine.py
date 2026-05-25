from __future__ import annotations

from uuid import uuid4

import frappe
from frappe.tests.utils import FrappeTestCase

from master_plan_it.master_plan_it.financial_engine import (
    get_actual_totals,
    get_contract_year_contribution_lines,
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

    def test_contract_without_terms_is_rejected(self):
        contract = frappe.get_doc(
            {
                "doctype": "MPIT Contract",
                "description": f"Contract No Terms {self.suffix}",
                "vendor": self.vendor,
                "cost_center": self.cost_center,
            }
        )
        with self.assertRaises(frappe.ValidationError):
            contract.insert()

    def test_contract_contribution_lines_are_term_based_and_year_clipped(self):
        contract = make_contract_with_terms(
            description=f"Contract Cross Year {self.suffix}",
            vendor=self.vendor,
            cost_center=self.cost_center,
            terms=[
                term_row("2029-07-01", "2030-06-30", 120, "Monthly"),
            ],
        )
        lines = get_contract_year_contribution_lines(self.year, contract_name=contract.name)

        self.assertEqual(len(lines), 1)
        self.assertEqual(lines[0]["source_type"], "Contract Term")
        self.assertEqual(lines[0]["source_row"], contract.terms[0].name)
        self.assertEqual(str(lines[0]["period_start"]), "2030-01-01")
        self.assertEqual(str(lines[0]["period_end"]), "2030-06-30")
        self.assertNotEqual(lines[0]["source_row"], "HEAD" + "ER")

    def test_overview_forecast_totals_do_not_sum_contract_forecasts_directly(self):
        make_contract_with_terms(
            description=f"Contract Forecast Neutralized {self.suffix}",
            vendor=self.vendor,
            cost_center=self.cost_center,
            terms=[term_row("2030-01-01", "2030-12-31", 200, "Monthly")],
        )

        contract_totals = get_contract_forecast_totals(self.year, cost_center=self.cost_center)
        summary = get_cost_center_financial_summary(self.year, self.cost_center)

        self.assertGreater(contract_totals["contract_forecast_total"], 0)
        self.assertEqual(summary["forecast_contracts"], 0)
        self.assertEqual(summary["forecast_total"], summary["forecast_estimate"] + summary["forecast_quote"])

    def test_vendor_filter_uses_row_vendor(self):
        vendor_a = ensure_vendor(f"Vendor A {self.suffix}")
        vendor_b = ensure_vendor(f"Vendor B {self.suffix}")

        make_expense(
            year=self.year,
            cost_center=self.cost_center,
            rows=[
                expense_row("Actual", 100, "Active", spend_date="2030-01-10", vendor=vendor_a),
                expense_row("Actual", 40, "Active", spend_date="2030-01-12", vendor=vendor_b),
            ],
        )

        totals_a = get_actual_totals(self.year, cost_center=self.cost_center, vendor=vendor_a)
        totals_b = get_actual_totals(self.year, cost_center=self.cost_center, vendor=vendor_b)

        self.assertEqual(totals_a["actual_total"], 100)
        self.assertEqual(totals_b["actual_total"], 40)

    def test_project_stage_rules_drive_forecast_and_actual_inclusion(self):
        project_proposed = ensure_project_with_stage(f"Project Proposed {self.suffix}", self.cost_center, "Proposed")
        project_idea = ensure_project_with_stage(f"Project Idea {self.suffix}", self.cost_center, "Idea")

        make_expense(
            year=self.year,
            cost_center=self.cost_center,
            project=self.project,
            rows=[
                expense_row("Estimate", 50, "Active", spend_date="2030-01-10"),
                expense_row("Actual", 30, "Active", spend_date="2030-01-11"),
            ],
        )
        make_expense(
            year=self.year,
            cost_center=self.cost_center,
            project=project_proposed,
            rows=[
                expense_row("Estimate", 70, "Active", spend_date="2030-02-10"),
                expense_row("Actual", 40, "Active", spend_date="2030-02-11"),
            ],
        )
        make_expense(
            year=self.year,
            cost_center=self.cost_center,
            project=project_idea,
            rows=[expense_row("Estimate", 90, "Active", spend_date="2030-03-10")],
        )

        forecast = get_expense_forecast_totals(self.year, cost_center=self.cost_center)
        actual = get_actual_totals(self.year, cost_center=self.cost_center)

        self.assertEqual(forecast["expense_forecast_total"], 120)  # Approved + Proposed, excludes Idea
        self.assertEqual(forecast["ideas_total"], 90)
        self.assertEqual(forecast["proposals_total"], 70)
        self.assertEqual(actual["actual_total"], 30)  # Approved only

    def test_expense_forecast_and_actual_respect_active_rows(self):
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
            is_extra=1,
            rows=[expense_row("Actual", 20, "Active", spend_date="2030-05-10")],
        )
        self.assertIsNotNone(expense_closed.name)

        expense_inactive_row = make_expense(
            year=self.year,
            cost_center=self.cost_center,
            project=self.project,
            is_extra=1,
            rows=[expense_row("Actual", 999, "Cancelled", spend_date="2030-06-10")],
        )
        self.assertIsNotNone(expense_inactive_row.name)

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

    def test_monthly_dataset_respects_mp_it_year_start_and_end(self):
        fiscal_year = ensure_year_with_bounds(2400, "2400-04-01", "2401-03-31")

        make_expense(
            year=fiscal_year,
            cost_center=self.cost_center,
            project=self.project,
            is_extra=1,
            rows=[
                expense_row(
                    "Estimate",
                    120,
                    "Active",
                    spend_date=None,
                    start_date="2400-04-01",
                    end_date="2401-03-31",
                    distribution="all",
                ),
                expense_row("Actual", 24, "Active", spend_date="2401-01-15"),
            ],
        )

        monthly = get_monthly_forecast_vs_actual(fiscal_year, project=self.project)
        labels = [row["month"] for row in monthly["months"]]

        self.assertEqual(
            labels,
            [
                "Apr 2400",
                "May 2400",
                "Jun 2400",
                "Jul 2400",
                "Aug 2400",
                "Sep 2400",
                "Oct 2400",
                "Nov 2400",
                "Dec 2400",
                "Jan 2401",
                "Feb 2401",
                "Mar 2401",
            ],
        )
        self.assertEqual(monthly["months"][0]["month_index"], 4)
        self.assertEqual(monthly["months"][-1]["month_index"], 3)
        self.assertEqual(monthly["totals"]["forecast_total"], 120)
        self.assertEqual(monthly["totals"]["actual_total"], 24)

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
        self.assertEqual(totals_b["plafond_total"], 700)
        self.assertEqual(totals_b["plafond_consumed"], 0)
        self.assertEqual(aggregate["plafond_total"], 1100)
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

        actual_funding = get_actual_totals(year, cost_center=cc_funding)
        actual_infra = get_actual_totals(year, cost_center=cc_infra)
        plafond_funding = get_plafond_totals(year, cost_center=cc_funding)
        summary_funding = get_cost_center_financial_summary(year, cc_funding)
        summary_infra = get_cost_center_financial_summary(year, cc_infra)

        self.assertEqual(actual_funding["actual_on_plafond"], 0)
        self.assertEqual(actual_infra["actual_on_plafond"], 250)
        self.assertEqual(plafond_funding["plafond_total"], 1000)
        self.assertEqual(plafond_funding["plafond_consumed"], 250)
        self.assertEqual(plafond_funding["plafond_remaining"], 750)
        self.assertEqual(summary_funding["plafond_consumed"], 250)
        self.assertEqual(summary_funding["actual_total"], 0)
        self.assertEqual(summary_infra["actual_total"], 250)
        self.assertEqual(summary_infra["plafond"], 0)
        self.assertEqual(summary_infra["plafond_consumed"], 0)

    def test_actual_standard_and_total_invariant(self):
        plafond = make_expense(
            year=self.year,
            cost_center=self.cost_center,
            expense_kind="Plafond",
            rows=[expense_row("Actual", 1000, "Active", spend_date="2030-01-10")],
        )
        make_expense(
            year=self.year,
            cost_center=self.cost_center,
            project=self.project,
            uses_plafond=0,
            is_extra=0,
            rows=[expense_row("Actual", 100, "Active", spend_date="2030-03-10")],
        )
        make_expense(
            year=self.year,
            cost_center=self.cost_center,
            project=self.project,
            uses_plafond=1,
            is_extra=0,
            plafond_expense=plafond.name,
            rows=[expense_row("Actual", 250, "Active", spend_date="2030-04-10")],
        )
        make_expense(
            year=self.year,
            cost_center=self.cost_center,
            project=self.project,
            uses_plafond=0,
            is_extra=1,
            rows=[expense_row("Actual", 80, "Active", spend_date="2030-05-10")],
        )

        actual = get_actual_totals(self.year, cost_center=self.cost_center, project=self.project)
        summary = get_cost_center_financial_summary(self.year, self.cost_center)

        self.assertEqual(actual["actual_standard"], 100)
        self.assertEqual(actual["actual_on_plafond"], 250)
        self.assertEqual(actual["actual_extra"], 80)
        self.assertEqual(actual["actual_total"], 430)

        self.assertEqual(summary["actual_standard"], 100)
        self.assertEqual(summary["actual_on_plafond"], 250)
        self.assertEqual(summary["actual_extra"], 80)
        self.assertEqual(summary["actual_total"], 430)
        self.assertEqual(
            actual["actual_total"],
            actual["actual_standard"] + actual["actual_on_plafond"] + actual["actual_extra"],
        )


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
    uses_plafond: int = 0,
    is_extra: int = 1,
    plafond_expense: str | None = None,
    rows: list[dict] | None = None,
):
    payload = {
        "doctype": "MPIT Expense",
        "expense_title": f"Expense {uuid4().hex[:8]}",
        "expense_kind": expense_kind,
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
    vendor: str | None = None,
) -> dict:
    row_vendor = vendor or ensure_vendor("Vendor FE Row Default")
    return {
        "doctype": "MPIT Expense Row",
        "row_description": "row",
        "row_phase": phase,
        "row_state": row_state,
        "vendor": row_vendor,
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


def ensure_year_with_bounds(year: int, start_date: str, end_date: str) -> str:
    if frappe.db.exists("MPIT Year", str(year)):
        return str(year)

    doc = frappe.get_doc(
        {
            "doctype": "MPIT Year",
            "year": year,
            "start_date": start_date,
            "end_date": end_date,
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
    return ensure_project_with_stage(title, cost_center, "Approved")


def ensure_project_with_stage(title: str, cost_center: str, stage: str) -> str:
    existing = frappe.db.get_value("MPIT Project", {"title": title}, "name")
    if existing:
        return existing

    doc = frappe.get_doc(
        {
            "doctype": "MPIT Project",
            "title": title,
            "workflow_state": stage,
            "cost_center": cost_center,
        }
    )
    doc.insert(ignore_permissions=True)
    return doc.name
