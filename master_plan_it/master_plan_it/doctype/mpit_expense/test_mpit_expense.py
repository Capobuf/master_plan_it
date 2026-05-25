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

    def test_ordinary_allows_contract_from_different_cost_center(self):
        suffix = frappe.generate_hash(length=6).upper()
        contract_cost_center = ensure_cost_center(f"CC-CONTRACT-LINK-{suffix}")
        expense_cost_center = ensure_cost_center(f"CC-EXPENSE-LINK-{suffix}")
        contract = ensure_contract(f"Contract Cross Link {suffix}", self.vendor, contract_cost_center)

        doc = base_expense(self.year_name, expense_cost_center, contract=contract)
        doc.append("rows", base_row(phase="Actual", amount=100, spend_date="2026-01-10"))
        doc.insert()

        self.assertEqual(doc.cost_center, expense_cost_center)
        self.assertEqual(doc.contract, contract)
        self.assertEqual(
            frappe.db.get_value("MPIT Contract", contract, "cost_center"),
            contract_cost_center,
        )

    def test_ordinary_rejects_project_and_contract_together(self):
        doc = base_expense(self.year_name, self.cost_center, self.project, self.contract)
        doc.append("rows", base_row(phase="Actual", amount=100, spend_date="2026-01-10"))
        with self.assertRaises(frappe.ValidationError):
            doc.insert()

    def test_standard_ordinary_expense_saves(self):
        doc = base_expense(self.year_name, self.cost_center, self.project)
        doc.uses_plafond = 0
        doc.is_extra = 0
        doc.append("rows", base_row(phase="Actual", amount=100, spend_date="2026-01-10"))
        doc.insert()

        self.assertEqual(doc.total_actual_net, 100)
        self.assertEqual(doc.total_actual_on_plafond_net, 0)
        self.assertEqual(doc.total_actual_extra_net, 0)

    def test_amount_is_computed_from_qty_and_unit_price_when_amount_missing(self):
        doc = base_expense(self.year_name, self.cost_center, self.project)
        row = base_row(phase="Actual", amount=0, spend_date="2026-01-10")
        row.update(
            {
                "qty": 6,
                "unit_price": 20.49,
                "amount_includes_vat": 0,
                "vat_rate": 22,
            }
        )
        doc.append("rows", row)
        doc.insert()

        saved_row = doc.rows[0]
        self.assertEqual(saved_row.amount, 122.94)
        self.assertEqual(saved_row.amount_net, 122.94)
        self.assertEqual(saved_row.amount_vat, 27.05)
        self.assertEqual(saved_row.amount_gross, 149.99)

    def test_amount_is_computed_from_qty_and_unit_price_when_amount_is_absent_field(self):
        doc = base_expense(self.year_name, self.cost_center, self.project)
        row = base_row(phase="Actual", amount=0, spend_date="2026-01-10")
        row.pop("amount", None)
        row.update(
            {
                "qty": 6,
                "unit_price": 20.49,
                "amount_includes_vat": 0,
                "vat_rate": 22,
            }
        )
        doc.append("rows", row)
        doc.insert()

        saved_row = doc.rows[0]
        self.assertEqual(saved_row.amount, 122.94)
        self.assertEqual(saved_row.amount_net, 122.94)
        self.assertEqual(saved_row.amount_vat, 27.05)
        self.assertEqual(saved_row.amount_gross, 149.99)

    def test_amount_is_computed_from_qty_and_unit_price_when_amount_is_empty_string(self):
        doc = base_expense(self.year_name, self.cost_center, self.project)
        row = base_row(phase="Actual", amount=0, spend_date="2026-01-10")
        row["amount"] = ""
        row.update(
            {
                "qty": 6,
                "unit_price": 20.49,
                "amount_includes_vat": 0,
                "vat_rate": 22,
            }
        )
        doc.append("rows", row)
        doc.insert()

        saved_row = doc.rows[0]
        self.assertEqual(saved_row.amount, 122.94)
        self.assertEqual(saved_row.amount_net, 122.94)
        self.assertEqual(saved_row.amount_vat, 27.05)
        self.assertEqual(saved_row.amount_gross, 149.99)

    def test_amount_is_recomputed_from_qty_and_unit_price_when_stale(self):
        doc = base_expense(self.year_name, self.cost_center, self.project)
        row = base_row(phase="Actual", amount=999, spend_date="2026-01-10")
        row.update(
            {
                "qty": 6,
                "unit_price": 20.49,
                "amount_includes_vat": 0,
                "vat_rate": 22,
            }
        )
        doc.append("rows", row)
        doc.insert()

        doc.rows[0].amount = 999
        doc.save()

        saved_row = doc.rows[0]
        self.assertEqual(saved_row.amount, 122.94)
        self.assertNotEqual(saved_row.amount, 999)

    def test_manual_amount_works_when_unit_price_is_empty(self):
        doc = base_expense(self.year_name, self.cost_center, self.project)
        row = base_row(phase="Actual", amount=150, spend_date="2026-01-10")
        row.update(
            {
                "qty": 6,
                "unit_price": 0,
                "amount_includes_vat": 0,
                "vat_rate": 22,
            }
        )
        doc.append("rows", row)
        doc.insert()

        saved_row = doc.rows[0]
        self.assertEqual(saved_row.amount, 150)
        self.assertEqual(saved_row.amount_net, 150)
        self.assertEqual(saved_row.amount_vat, 33)
        self.assertEqual(saved_row.amount_gross, 183)

    def test_qty_zero_is_not_converted_to_one(self):
        doc = base_expense(self.year_name, self.cost_center, self.project)
        row = base_row(phase="Actual", amount=0, spend_date="2026-01-10")
        row.update(
            {
                "qty": 0,
                "unit_price": 20.49,
                "amount_includes_vat": 0,
                "vat_rate": 22,
            }
        )
        doc.append("rows", row)
        doc.insert()

        self.assertEqual(doc.rows[0].amount, 0)

    def test_ordinary_cannot_be_both_on_plafond_and_extra(self):
        doc = base_expense(self.year_name, self.cost_center, self.project)
        doc.uses_plafond = 1
        doc.is_extra = 1
        doc.append("rows", base_row(phase="Actual", amount=100, spend_date="2026-01-10"))
        with self.assertRaises(frappe.ValidationError):
            doc.insert()

    def test_plafond_reference_requires_on_plafond(self):
        plafond = frappe.get_doc(
            {
                "doctype": "MPIT Expense",
                "expense_kind": "Plafond",
                "expense_title": "Plafond Reference Guard",
                "year": self.year_name,
                "cost_center": self.cost_center,
                "rows": [base_row(phase="Actual", amount=1000, spend_date="2026-01-10")],
            }
        )
        plafond.insert()

        doc = base_expense(self.year_name, self.cost_center, self.project)
        doc.uses_plafond = 0
        doc.is_extra = 0
        doc.plafond_expense = plafond.name
        doc.append("rows", base_row(phase="Actual", amount=100, spend_date="2026-02-10"))
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

    def test_ordinary_rows_require_vendor(self):
        doc = base_expense(self.year_name, self.cost_center, self.project)
        doc.append("rows", base_row(phase="Actual", amount=100, spend_date="2026-01-10", vendor=""))
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

    def test_multiple_plafonds_same_year_and_cost_center_are_allowed(self):
        suffix = frappe.generate_hash(length=6).upper()
        year_name = ensure_year(2040)
        cost_center = ensure_cost_center(f"CC-PLAFOND-RULE-{suffix}")

        first = frappe.get_doc(
            {
                "doctype": "MPIT Expense",
                "expense_kind": "Plafond",
                "expense_title": "Main Plafond",
                "year": year_name,
                "cost_center": cost_center,
                "rows": [base_row(phase="Actual", amount=1000, spend_date="2040-01-10")],
            }
        )
        first.insert()

        second = frappe.get_doc(
            {
                "doctype": "MPIT Expense",
                "expense_kind": "Plafond",
                "expense_title": "Second Plafond",
                "year": year_name,
                "cost_center": cost_center,
                "rows": [base_row(phase="Actual", amount=500, spend_date="2040-02-10")],
            }
        )
        second.insert()
        self.assertTrue(first.name)
        self.assertTrue(second.name)

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
        self.assertEqual(doc.rows[0].row_state, "Replaced")

    def test_row_cannot_replace_actual_target(self):
        doc = base_expense(self.year_name, self.cost_center, self.project)
        doc.append("rows", base_row(phase="Actual", amount=100, spend_date="2026-01-10"))
        doc.append("rows", base_row(phase="Estimate", amount=120, spend_date="2026-02-10"))
        doc.insert()

        doc.rows[1].replaces_row_name = doc.rows[0].name
        with self.assertRaises(frappe.ValidationError):
            doc.save()

    def test_row_replacement_cycle_is_rejected(self):
        doc = base_expense(self.year_name, self.cost_center, self.project)
        doc.append("rows", base_row(phase="Estimate", amount=100, spend_date="2026-01-10"))
        doc.append("rows", base_row(phase="Quote", amount=120, spend_date="2026-02-10"))
        doc.insert()

        doc.rows[0].replaces_row_name = doc.rows[1].name
        doc.rows[1].replaces_row_name = doc.rows[0].name
        with self.assertRaises(frappe.ValidationError):
            doc.save()

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

    def test_cross_cost_center_plafond_reference_is_allowed(self):
        year_2030 = ensure_year(2030)
        suffix = frappe.generate_hash(length=6).upper()
        cc_funding = ensure_cost_center(f"CC-FUNDING-{suffix}")
        cc_infra = ensure_cost_center(f"CC-INFRA-{suffix}")

        plafond = frappe.get_doc(
            {
                "doctype": "MPIT Expense",
                "expense_kind": "Plafond",
                "expense_title": f"Plafond Funding {suffix}",
                "year": year_2030,
                "cost_center": cc_funding,
                "rows": [base_row(phase="Actual", amount=1000, spend_date="2030-01-10")],
            }
        ).insert()

        ordinary = base_expense(year_2030, cc_infra)
        ordinary.uses_plafond = 1
        ordinary.is_extra = 0
        ordinary.plafond_expense = plafond.name
        ordinary.append("rows", base_row(phase="Actual", amount=250, spend_date="2030-02-10"))
        ordinary.insert()
        self.assertEqual(ordinary.plafond_expense, plafond.name)

    def test_plafond_reference_must_belong_to_same_year(self):
        year_2030 = ensure_year(2030)
        year_2031 = ensure_year(2031)
        suffix = frappe.generate_hash(length=6).upper()
        cc_funding = ensure_cost_center(f"CC-FUNDING-{suffix}")
        cc_infra = ensure_cost_center(f"CC-INFRA-{suffix}")

        plafond = frappe.get_doc(
            {
                "doctype": "MPIT Expense",
                "expense_kind": "Plafond",
                "expense_title": f"Plafond Year {suffix}",
                "year": year_2030,
                "cost_center": cc_funding,
                "rows": [base_row(phase="Actual", amount=1000, spend_date="2030-01-10")],
            }
        ).insert()

        ordinary = base_expense(year_2031, cc_infra)
        ordinary.uses_plafond = 1
        ordinary.is_extra = 0
        ordinary.plafond_expense = plafond.name
        ordinary.append("rows", base_row(phase="Actual", amount=100, spend_date="2031-03-10"))
        with self.assertRaises(frappe.ValidationError):
            ordinary.insert()

    def test_same_cost_center_plafond_reference_succeeds(self):
        year_2030 = ensure_year(2030)
        suffix = frappe.generate_hash(length=6).upper()
        cc_funding = ensure_cost_center(f"CC-FUNDING-{suffix}")

        plafond = frappe.get_doc(
            {
                "doctype": "MPIT Expense",
                "expense_kind": "Plafond",
                "expense_title": f"Plafond Same CC {suffix}",
                "year": year_2030,
                "cost_center": cc_funding,
                "rows": [base_row(phase="Actual", amount=900, spend_date="2030-01-10")],
            }
        ).insert()

        ordinary = base_expense(year_2030, cc_funding)
        ordinary.uses_plafond = 1
        ordinary.is_extra = 0
        ordinary.plafond_expense = plafond.name
        ordinary.append("rows", base_row(phase="Actual", amount=90, spend_date="2030-02-10"))
        ordinary.insert()
        self.assertTrue(ordinary.name)

    def test_non_plafond_reference_fails(self):
        year_2030 = ensure_year(2030)
        suffix = frappe.generate_hash(length=6).upper()
        cc_infra = ensure_cost_center(f"CC-INFRA-{suffix}")

        ordinary_a = base_expense(year_2030, cc_infra)
        ordinary_a.append("rows", base_row(phase="Actual", amount=120, spend_date="2030-01-10"))
        ordinary_a.insert()

        ordinary_b = base_expense(year_2030, cc_infra)
        ordinary_b.uses_plafond = 1
        ordinary_b.is_extra = 0
        ordinary_b.plafond_expense = ordinary_a.name
        ordinary_b.append("rows", base_row(phase="Actual", amount=40, spend_date="2030-02-10"))
        with self.assertRaises(frappe.ValidationError):
            ordinary_b.insert()


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
    vendor: str | None = None,
):
    row_vendor = ensure_vendor("Vendor Expense Row Default") if vendor is None else vendor
    return {
        "doctype": "MPIT Expense Row",
        "row_description": "Row",
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
            "workflow_state": "Approved",
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
            "terms": [
                {
                    "doctype": "MPIT Contract Term",
                    "from_date": "2026-01-01",
                    "to_date": "2026-12-31",
                    "amount": 100,
                    "amount_includes_vat": 0,
                    "vat_rate": 22,
                    "billing_cycle": "Monthly",
                }
            ],
        }
    )
    doc.insert(ignore_permissions=True)
    return doc.name
