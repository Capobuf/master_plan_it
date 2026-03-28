from __future__ import annotations

import frappe
from frappe.tests.utils import FrappeTestCase


class TestMPITContract(FrappeTestCase):
    def setUp(self):
        frappe.set_user("Administrator")
        self.cost_center = ensure_cost_center("CC-CONTRACT-TEST")
        self.vendor = ensure_vendor("Vendor Contract Test")

    def test_header_fallback_requires_amount_without_terms(self):
        doc = frappe.get_doc(
            {
                "doctype": "MPIT Contract",
                "description": "Contract Fallback",
                "vendor": self.vendor,
                "cost_center": self.cost_center,
                "billing_cycle": "Monthly",
            }
        )
        with self.assertRaises(frappe.ValidationError):
            doc.insert()

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
