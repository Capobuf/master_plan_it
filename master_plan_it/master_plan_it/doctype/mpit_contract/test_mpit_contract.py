from __future__ import annotations

import frappe
from frappe.tests.utils import FrappeTestCase

from master_plan_it.master_plan_it.doctype.mpit_contract.mpit_contract import create_actual_from_contract


class TestMPITContract(FrappeTestCase):
    def setUp(self):
        frappe.set_user("Administrator")
        self.cost_center = ensure_cost_center("CC-CONTRACT-TEST")
        self.vendor = ensure_vendor("Vendor Contract Test")
        self.year_2026 = ensure_year(2026)
        self.year_2027 = ensure_year(2027)

    def test_contract_without_terms_saves_as_incomplete_for_actualization(self):
        doc = frappe.get_doc(
            {
                "doctype": "MPIT Contract",
                "description": "Contract No Terms",
                "vendor": self.vendor,
                "cost_center": self.cost_center,
            }
        )
        doc.insert()
        self.assertFalse(doc.terms)

    def test_contract_with_terms_computes_annual_amount(self):
        doc = frappe.get_doc(
            {
                "doctype": "MPIT Contract",
                "description": "Contract Terms",
                "vendor": self.vendor,
                "cost_center": self.cost_center,
                "terms": [
                    {
                        "doctype": "MPIT Contract Term",
                        "from_date": "2026-01-01",
                        "to_date": "2026-12-31",
                        "amount": 1200,
                        "amount_includes_vat": 0,
                        "vat_rate": 22,
                        "billing_cycle": "Annual",
                    }
                ],
            }
        )
        doc.insert()
        self.assertGreaterEqual(doc.annual_amount_current_year, 0)

    def test_last_open_term_is_not_closed_automatically(self):
        doc = frappe.get_doc(
            {
                "doctype": "MPIT Contract",
                "description": "Contract Open Last Term",
                "vendor": self.vendor,
                "cost_center": self.cost_center,
                "terms": [
                    {
                        "doctype": "MPIT Contract Term",
                        "from_date": "2026-01-01",
                        "to_date": None,
                        "amount": 100,
                        "amount_includes_vat": 0,
                        "vat_rate": 22,
                        "billing_cycle": "Monthly",
                    },
                    {
                        "doctype": "MPIT Contract Term",
                        "from_date": "2026-07-01",
                        "to_date": None,
                        "amount": 120,
                        "amount_includes_vat": 0,
                        "vat_rate": 22,
                        "billing_cycle": "Monthly",
                    },
                ],
            }
        )
        doc.insert()

        terms = sorted(doc.terms, key=lambda t: t.from_date)
        self.assertEqual(str(terms[0].to_date), "2026-06-30")
        self.assertIsNone(terms[1].to_date)

    def test_create_actual_from_contract_without_terms_creates_nothing(self):
        contract = frappe.get_doc(
            {
                "doctype": "MPIT Contract",
                "description": "Contract No Terms Actualization",
                "vendor": self.vendor,
                "cost_center": self.cost_center,
                "status": "Active",
            }
        ).insert()

        result = create_actual_from_contract(contract.name, year=self.year_2026)
        self.assertEqual(result["status"], "no_overlap")
        self.assertEqual(result["added_rows"], 0)
        self.assertFalse(result["expense_name"])

        expenses = frappe.get_all(
            "MPIT Expense",
            filters={"contract": contract.name, "year": self.year_2026},
            pluck="name",
        )
        self.assertFalse(expenses)

    def test_create_actual_from_contract_uses_terms_when_present(self):
        contract = frappe.get_doc(
            {
                "doctype": "MPIT Contract",
                "description": "Contract Terms Actualization",
                "vendor": self.vendor,
                "cost_center": self.cost_center,
                "status": "Active",
                "terms": [
                    {
                        "doctype": "MPIT Contract Term",
                        "from_date": "2026-01-01",
                        "to_date": "2026-06-30",
                        "amount": 100,
                        "amount_includes_vat": 0,
                        "vat_rate": 22,
                        "billing_cycle": "Monthly",
                    },
                    {
                        "doctype": "MPIT Contract Term",
                        "from_date": "2026-07-01",
                        "to_date": "2026-12-31",
                        "amount": 200,
                        "amount_includes_vat": 0,
                        "vat_rate": 22,
                        "billing_cycle": "Monthly",
                    },
                ],
            }
        ).insert()

        result = create_actual_from_contract(contract.name, year=self.year_2026)
        self.assertEqual(result["status"], "created")
        self.assertEqual(result["added_rows"], 2)

        expense = frappe.get_doc("MPIT Expense", result["expense_name"])
        self.assertEqual(len(expense.rows), 2)
        references = {row.external_reference for row in expense.rows}
        for term in contract.terms:
            expected_ref = f"MPIT_CONTRACT_ACTUAL::{self.year_2026}::{contract.name}::TERM::{term.name}"
            self.assertIn(expected_ref, references)

    def test_create_actual_from_contract_is_idempotent(self):
        contract = frappe.get_doc(
            {
                "doctype": "MPIT Contract",
                "description": "Contract Idempotent Actualization",
                "vendor": self.vendor,
                "cost_center": self.cost_center,
                "status": "Active",
                "terms": [
                    {
                        "doctype": "MPIT Contract Term",
                        "from_date": "2026-01-01",
                        "to_date": "2026-12-31",
                        "amount": 150,
                        "amount_includes_vat": 0,
                        "vat_rate": 22,
                        "billing_cycle": "Monthly",
                    },
                ],
            }
        ).insert()

        first = create_actual_from_contract(contract.name, year=self.year_2026)
        second = create_actual_from_contract(contract.name, year=self.year_2026)

        self.assertEqual(first["status"], "created")
        self.assertEqual(second["status"], "noop")

        expense = frappe.get_doc("MPIT Expense", first["expense_name"])
        self.assertEqual(len(expense.rows), 1)

    def test_create_actual_from_contract_no_overlap_creates_nothing(self):
        contract = frappe.get_doc(
            {
                "doctype": "MPIT Contract",
                "description": "Contract No Overlap Actualization",
                "vendor": self.vendor,
                "cost_center": self.cost_center,
                "status": "Active",
                "terms": [
                    {
                        "doctype": "MPIT Contract Term",
                        "from_date": "2026-01-01",
                        "to_date": "2026-12-31",
                        "amount": 100,
                        "amount_includes_vat": 0,
                        "vat_rate": 22,
                        "billing_cycle": "Monthly",
                    },
                ],
            }
        ).insert()

        result = create_actual_from_contract(contract.name, year=self.year_2027)
        self.assertEqual(result["status"], "no_overlap")

        expenses = frappe.get_all(
            "MPIT Expense",
            filters={"contract": contract.name, "year": self.year_2027},
            pluck="name",
        )
        self.assertFalse(expenses)

    def test_generated_actual_rows_pass_existing_expense_validation_rules(self):
        contract = frappe.get_doc(
            {
                "doctype": "MPIT Contract",
                "description": "Contract Validation Actualization",
                "vendor": self.vendor,
                "cost_center": self.cost_center,
                "status": "Active",
                "terms": [
                    {
                        "doctype": "MPIT Contract Term",
                        "from_date": "2026-01-01",
                        "to_date": "2026-12-31",
                        "amount": 90,
                        "amount_includes_vat": 0,
                        "vat_rate": 22,
                        "billing_cycle": "Monthly",
                    },
                ],
            }
        ).insert()

        result = create_actual_from_contract(contract.name, year=self.year_2026)
        expense = frappe.get_doc("MPIT Expense", result["expense_name"])
        expense.run_method("validate")
        self.assertEqual(expense.total_actual_net, sum(row.amount_net for row in expense.rows))
        self.assertTrue(all("::TERM::" in row.external_reference for row in expense.rows))
        obsolete_suffix = "::" + "HEADER"
        self.assertFalse(any(row.external_reference.endswith(obsolete_suffix) for row in expense.rows))


def ensure_vendor(name: str) -> str:
    existing = frappe.db.get_value("MPIT Vendor", {"vendor_name": name}, "name")
    if existing:
        return existing

    doc = frappe.get_doc({"doctype": "MPIT Vendor", "vendor_name": name})
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
