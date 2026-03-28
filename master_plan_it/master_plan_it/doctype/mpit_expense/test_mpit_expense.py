from __future__ import annotations

import frappe
from frappe.tests.utils import FrappeTestCase
from frappe.utils import add_days

from master_plan_it.master_plan_it.doctype.mpit_expense.mpit_expense import (
    get_expense_row_replacement_options,
)


class TestMPITExpense(FrappeTestCase):
    def setUp(self):
        frappe.set_user("Administrator")
        self.year_name = ensure_year(2026)
        self.cost_center = ensure_cost_center("CC-EXPENSE-TEST")
        self.vendor = ensure_vendor("Vendor Expense Test")
        self.project = ensure_project("Project Expense Test", self.cost_center)
        self.contract = ensure_contract("Contract Expense Test", self.vendor, self.cost_center)

    def test_ordinary_allows_standalone_cost_center_context(self):
        doc = base_expense(self.year_name, self.cost_center)
        doc.append("rows", base_row(phase="Actual", amount=100, spend_date="2026-01-10"))
        doc.insert()
        self.assertFalse(doc.project)
        self.assertFalse(doc.contract)

    def test_ordinary_rejects_project_and_contract_together(self):
        doc = base_expense(self.year_name, self.cost_center, self.project, self.contract)
        doc.append("rows", base_row(phase="Actual", amount=100, spend_date="2026-01-10"))
        with self.assertRaises(frappe.ValidationError):
            doc.insert()

    def test_ordinary_requires_exclusive_funding(self):
        doc = base_expense(self.year_name, self.cost_center, self.project)
        doc.uses_plafond = 0
        doc.is_extra = 0
        with self.assertRaises(frappe.ValidationError):
            doc.insert()

    def test_ordinary_rejects_negative_estimate_and_quote(self):
        for phase in ("Estimate", "Quote"):
            doc = base_expense(self.year_name, self.cost_center, self.project)
            doc.append("rows", base_row(phase=phase, amount=-10))
            with self.assertRaises(frappe.ValidationError):
                doc.insert()

    def test_ordinary_requires_plafond_reference_when_flagged(self):
        doc = base_expense(self.year_name, self.cost_center, self.project)
        doc.uses_plafond = 1
        doc.is_extra = 0
        with self.assertRaises(frappe.ValidationError):
            doc.insert()

    def test_row_date_must_stay_in_document_year(self):
        doc = base_expense(self.year_name, self.cost_center, self.project)
        doc.append("rows", base_row(spend_date="2027-01-10"))
        with self.assertRaises(frappe.ValidationError):
            doc.insert()

    def test_row_must_use_single_time_mode(self):
        doc = base_expense(self.year_name, self.cost_center, self.project)
        doc.append(
            "rows",
            base_row(spend_date="2026-03-10", start_date="2026-03-01", end_date="2026-03-31", distribution="all"),
        )
        with self.assertRaises(frappe.ValidationError):
            doc.insert()

    def test_plafond_single_open_per_year_and_cost_center(self):
        first = frappe.get_doc(
            {
                "doctype": "MPIT Expense",
                "expense_kind": "Plafond",
                "expense_title": "Main Plafond",
                "workflow_state": "Closed",
                "year": self.year_name,
                "cost_center": self.cost_center,
                "rows": [base_row(phase="Actual", amount=1000, spend_date="2026-01-10")],
            }
        )
        first.insert()

        second = frappe.get_doc(
            {
                "doctype": "MPIT Expense",
                "expense_kind": "Plafond",
                "expense_title": "Second Plafond",
                "workflow_state": "Open",
                "year": self.year_name,
                "cost_center": self.cost_center,
                "rows": [base_row(phase="Actual", amount=500, spend_date="2026-02-10")],
            }
        )
        with self.assertRaises(frappe.ValidationError):
            second.insert()

    def test_replaced_and_cancelled_rows_are_excluded(self):
        doc = base_expense(self.year_name, self.cost_center, self.project)
        doc.append("rows", base_row(phase="Estimate", amount=100, row_state="Active", spend_date="2026-01-15"))
        doc.append("rows", base_row(phase="Quote", amount=50, row_state="Replaced", spend_date="2026-02-15"))
        doc.append("rows", base_row(phase="Actual", amount=20, row_state="Cancelled", spend_date="2026-03-15"))
        doc.insert()

        self.assertEqual(doc.total_estimate_net, 100)
        self.assertEqual(doc.total_quote_net, 0)
        self.assertEqual(doc.total_actual_net, 0)

    def test_row_can_reference_sibling_in_same_expense(self):
        doc = base_expense(self.year_name, self.cost_center, self.project)
        doc.append("rows", base_row(phase="Estimate", amount=100, spend_date="2026-01-10"))
        doc.append("rows", base_row(phase="Quote", amount=120, spend_date="2026-02-10"))
        doc.insert()

        target = doc.rows[0].name
        doc.rows[1].replaces_row_name = target
        doc.save()

        self.assertEqual(doc.rows[1].replaces_row_name, target)

    def test_row_cannot_reference_itself(self):
        doc = base_expense(self.year_name, self.cost_center, self.project)
        doc.append("rows", base_row(phase="Estimate", amount=100, spend_date="2026-01-10"))
        doc.insert()

        doc.rows[0].replaces_row_name = doc.rows[0].name
        with self.assertRaises(frappe.ValidationError):
            doc.save()

    def test_row_reference_must_exist(self):
        doc = base_expense(self.year_name, self.cost_center, self.project)
        doc.append("rows", base_row(phase="Estimate", amount=100, spend_date="2026-01-10"))
        doc.append("rows", base_row(phase="Quote", amount=120, spend_date="2026-02-10"))
        doc.insert()

        doc.rows[1].replaces_row_name = "DOES-NOT-EXIST"
        with self.assertRaises(frappe.ValidationError):
            doc.save()

    def test_row_cannot_reference_other_expense_row(self):
        source = base_expense(self.year_name, self.cost_center, self.project)
        source.append("rows", base_row(phase="Estimate", amount=100, spend_date="2026-01-10"))
        source.insert()

        doc = base_expense(self.year_name, self.cost_center, self.project)
        doc.append("rows", base_row(phase="Estimate", amount=100, spend_date="2026-03-10"))
        doc.append("rows", base_row(phase="Quote", amount=120, spend_date="2026-04-10"))
        doc.insert()

        doc.rows[1].replaces_row_name = source.rows[0].name
        with self.assertRaises(frappe.ValidationError):
            doc.save()

    def test_row_reference_query_returns_only_siblings(self):
        doc = base_expense(self.year_name, self.cost_center, self.project)
        doc.append("rows", base_row(phase="Estimate", amount=100, spend_date="2026-01-10"))
        doc.append("rows", base_row(phase="Quote", amount=120, spend_date="2026-02-10"))
        doc.append("rows", base_row(phase="Actual", amount=140, spend_date="2026-03-10"))
        doc.insert()

        other = base_expense(self.year_name, self.cost_center, self.project)
        other.append("rows", base_row(phase="Estimate", amount=100, spend_date="2026-05-10"))
        other.insert()

        options = get_expense_row_replacement_options(
            "MPIT Expense Row",
            "",
            "name",
            0,
            20,
            {"parent_expense": doc.name, "current_row_name": doc.rows[2].name},
        )
        names = [row[0] for row in options]

        self.assertIn(doc.rows[0].name, names)
        self.assertIn(doc.rows[1].name, names)
        self.assertNotIn(doc.rows[2].name, names)
        self.assertNotIn(other.rows[0].name, names)


def base_expense(
    year_name: str,
    cost_center: str,
    project: str | None = None,
    contract: str | None = None,
):
    return frappe.get_doc(
        {
            "doctype": "MPIT Expense",
            "expense_kind": "Ordinary",
            "expense_title": "Expense Test",
            "workflow_state": "Open",
            "year": year_name,
            "cost_center": cost_center,
            "project": project,
            "contract": contract,
            "uses_plafond": 0,
            "is_extra": 1,
            "rows": [],
        }
    )


def base_row(
    phase: str = "Estimate",
    amount: float = 100,
    row_state: str = "Active",
    spend_date: str | None = "2026-01-10",
    start_date: str | None = None,
    end_date: str | None = None,
    distribution: str | None = None,
    replaces_row_name: str | None = None,
):
    return {
        "doctype": "MPIT Expense Row",
        "row_description": "Row",
        "row_phase": phase,
        "row_state": row_state,
        "amount": amount,
        "amount_includes_vat": 0,
        "vat_rate": 22,
        "spend_date": spend_date,
        "start_date": start_date,
        "end_date": end_date,
        "distribution": distribution,
        "replaces_row_name": replaces_row_name,
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


def ensure_contract(description: str, vendor: str, cost_center: str) -> str:
    existing = frappe.db.get_value("MPIT Contract", {"description": description}, "name")
    if existing:
        return existing

    doc = frappe.get_doc(
        {
            "doctype": "MPIT Contract",
            "description": description,
            "vendor": vendor,
            "cost_center": cost_center,
            "current_amount": 100,
            "current_amount_includes_vat": 0,
            "vat_rate": 22,
            "billing_cycle": "Monthly",
        }
    )
    doc.insert(ignore_permissions=True)
    return doc.name
