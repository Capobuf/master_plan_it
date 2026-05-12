from __future__ import annotations

import datetime

import frappe
from frappe.tests.utils import FrappeTestCase

from master_plan_it.master_plan_it.services.contract_expense_sync import sync_contract_expenses


class TestMPITContract(FrappeTestCase):
    def setUp(self):
        frappe.set_user("Administrator")
        self.cost_center = ensure_cost_center("CC-CONTRACT-TEST")
        self.vendor = ensure_vendor("Vendor Contract Test")

    def test_contract_requires_at_least_one_term(self):
        doc = frappe.get_doc(
            {
                "doctype": "MPIT Contract",
                "description": "Contract No Terms",
                "vendor": self.vendor,
                "cost_center": self.cost_center,
            }
        )
        with self.assertRaises(frappe.ValidationError):
            doc.insert()

    def test_contract_status_is_calculated_active_for_open_or_current_terms(self):
        today = datetime.date.today()
        doc = frappe.get_doc(
            {
                "doctype": "MPIT Contract",
                "description": "Contract Active Status",
                "vendor": self.vendor,
                "cost_center": self.cost_center,
                "terms": [
                    {
                        "doctype": "MPIT Contract Term",
                        "from_date": str(today.replace(month=1, day=1)),
                        "to_date": None,
                        "amount": 100,
                        "amount_includes_vat": 0,
                        "vat_rate": 22,
                        "billing_cycle": "Monthly",
                    }
                ],
            }
        ).insert()
        self.assertEqual(doc.status, "Active")

    def test_contract_status_is_calculated_concluded_when_all_terms_ended(self):
        today = datetime.date.today()
        past_year = today.year - 2
        doc = frappe.get_doc(
            {
                "doctype": "MPIT Contract",
                "description": "Contract Concluded Status",
                "vendor": self.vendor,
                "cost_center": self.cost_center,
                "terms": [
                    {
                        "doctype": "MPIT Contract Term",
                        "from_date": f"{past_year}-01-01",
                        "to_date": f"{past_year}-12-31",
                        "amount": 100,
                        "amount_includes_vat": 0,
                        "vat_rate": 22,
                        "billing_cycle": "Monthly",
                    }
                ],
            }
        ).insert()
        self.assertEqual(doc.status, "Concluded")

    def test_auto_renew_creates_successor_once_and_no_duplicates(self):
        current_year = datetime.date.today().year
        ensure_year(current_year)
        next_year = ensure_year(current_year + 1)

        doc = frappe.get_doc(
            {
                "doctype": "MPIT Contract",
                "description": "Contract Auto Renew",
                "vendor": self.vendor,
                "cost_center": self.cost_center,
                "auto_renew": 1,
                "terms": [
                    {
                        "doctype": "MPIT Contract Term",
                        "from_date": f"{current_year}-01-01",
                        "to_date": f"{current_year}-12-31",
                        "amount": 100,
                        "amount_includes_vat": 0,
                        "vat_rate": 22,
                        "billing_cycle": "Monthly",
                    }
                ],
            }
        ).insert()

        # First save on insert creates one successor to cover next existing MPIT Year.
        self.assertEqual(len(doc.terms), 2)
        manual_terms = [term for term in doc.terms if not term.is_auto_renewed]
        generated_terms = [term for term in doc.terms if term.is_auto_renewed]
        self.assertEqual(len(manual_terms), 1)
        self.assertEqual(len(generated_terms), 1)
        self.assertEqual(generated_terms[0].renewed_from_term, manual_terms[0].name)

        from_dates = [str(term.from_date) for term in doc.terms]
        self.assertIn(f"{current_year + 1}-01-01", from_dates)

        for _ in range(3):
            doc.save()
            doc.reload()

        self.assertEqual(len(doc.terms), 2)
        self.assertEqual(len(set(str(term.from_date) for term in doc.terms)), 2)
        manual_terms = [term for term in doc.terms if not term.is_auto_renewed]
        generated_terms = [term for term in doc.terms if term.is_auto_renewed]
        self.assertEqual(len(manual_terms), 1)
        self.assertEqual(len(generated_terms), 1)
        self.assertEqual(generated_terms[0].renewed_from_term, manual_terms[0].name)
        self.assertEqual(str(generated_terms[0].to_date), f"{current_year + 1}-12-31")
        self.assertEqual(next_year, str(current_year + 1))

    def test_auto_renew_does_not_create_successor_for_open_ended_last_term(self):
        current_year = datetime.date.today().year
        ensure_year(current_year)
        ensure_year(current_year + 1)

        doc = frappe.get_doc(
            {
                "doctype": "MPIT Contract",
                "description": "Contract Auto Renew Open End",
                "vendor": self.vendor,
                "cost_center": self.cost_center,
                "auto_renew": 1,
                "terms": [
                    {
                        "doctype": "MPIT Contract Term",
                        "from_date": f"{current_year}-01-01",
                        "to_date": None,
                        "amount": 100,
                        "amount_includes_vat": 0,
                        "vat_rate": 22,
                        "billing_cycle": "Monthly",
                    }
                ],
            }
        ).insert()
        self.assertEqual(len(doc.terms), 1)

    def test_auto_renew_does_not_create_successor_when_target_year_is_missing(self):
        year = _pick_future_year_without_next()
        ensure_year(year)

        doc = frappe.get_doc(
            {
                "doctype": "MPIT Contract",
                "description": "Contract Auto Renew Missing Year",
                "vendor": self.vendor,
                "cost_center": self.cost_center,
                "auto_renew": 1,
                "terms": [
                    {
                        "doctype": "MPIT Contract Term",
                        "from_date": f"{year}-01-01",
                        "to_date": f"{year}-12-31",
                        "amount": 100,
                        "amount_includes_vat": 0,
                        "vat_rate": 22,
                        "billing_cycle": "Monthly",
                    }
                ],
            }
        ).insert()

        self.assertEqual(len(doc.terms), 1)
        self.assertFalse(frappe.db.exists("MPIT Year", str(year + 1)))

    def test_contract_sync_generates_closed_expenses_only_for_existing_years(self):
        year = _pick_future_year_without_next()
        ensure_year(year)

        contract = frappe.get_doc(
            {
                "doctype": "MPIT Contract",
                "description": "Contract Year Sync Existing Years",
                "vendor": self.vendor,
                "cost_center": self.cost_center,
                "auto_renew": 0,
                "terms": [
                    {
                        "doctype": "MPIT Contract Term",
                        "from_date": f"{year}-01-01",
                        "to_date": f"{year + 1}-12-31",
                        "amount": 120,
                        "amount_includes_vat": 0,
                        "vat_rate": 22,
                        "billing_cycle": "Monthly",
                    }
                ],
            }
        ).insert()

        expenses = frappe.get_all(
            "MPIT Expense",
            filters={"contract": contract.name, "expense_kind": "Ordinary", "workflow_state": ["!=", "Cancelled"]},
            fields=["name", "year", "workflow_state"],
            order_by="year asc",
        )
        self.assertEqual(len(expenses), 1)
        self.assertEqual(str(expenses[0].year), str(year))
        self.assertEqual(expenses[0].workflow_state, "Closed")

        expense_doc = frappe.get_doc("MPIT Expense", expenses[0].name)
        self.assertTrue(expense_doc.rows)
        self.assertTrue(all(row.vendor == contract.vendor for row in expense_doc.rows if row.row_state == "Active"))

    def test_contract_sync_is_idempotent_and_updates_rows_without_duplicates(self):
        year = datetime.date.today().year + 4
        ensure_year(year)
        ensure_year(year + 1)

        contract = frappe.get_doc(
            {
                "doctype": "MPIT Contract",
                "description": "Contract Sync Idempotent",
                "vendor": self.vendor,
                "cost_center": self.cost_center,
                "auto_renew": 0,
                "terms": [
                    {
                        "doctype": "MPIT Contract Term",
                        "from_date": f"{year}-01-01",
                        "to_date": f"{year}-12-31",
                        "amount": 100,
                        "amount_includes_vat": 0,
                        "vat_rate": 22,
                        "billing_cycle": "Monthly",
                    }
                ],
            }
        ).insert()

        first_sync = sync_contract_expenses(contract.name)
        second_sync = sync_contract_expenses(contract.name)
        self.assertEqual(second_sync["rows_added"], 0)
        self.assertEqual(second_sync["rows_cancelled"], 0)
        self.assertGreaterEqual(first_sync["years_processed"], 1)

        expenses = frappe.get_all(
            "MPIT Expense",
            filters={"contract": contract.name, "year": str(year), "expense_kind": "Ordinary", "workflow_state": ["!=", "Cancelled"]},
            fields=["name"],
        )
        self.assertEqual(len(expenses), 1)
        expense = frappe.get_doc("MPIT Expense", expenses[0].name)
        active_generated = [row for row in expense.rows if row.row_state == "Active" and row.external_reference]
        self.assertEqual(len(active_generated), 1)

        # Update contract amount and sync again: existing generated row must be updated, not duplicated.
        contract.terms[0].amount = 150
        contract.save()
        expense.reload()
        refreshed_rows = [row for row in expense.rows if row.row_state == "Active" and row.external_reference]
        self.assertEqual(len(refreshed_rows), 1)
        self.assertEqual(refreshed_rows[0].amount, 1800)


def _pick_future_year_without_next() -> int:
    year = 2090
    while frappe.db.exists("MPIT Year", str(year + 1)):
        year += 2
    return year


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
