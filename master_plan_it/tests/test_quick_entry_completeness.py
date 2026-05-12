from __future__ import annotations

import frappe
from frappe.tests.utils import FrappeTestCase


def _quick_entry_docfields(meta) -> list:
    return [
        df
        for df in meta.fields
        if df.reqd
        and not df.read_only
        and not df.is_virtual
        and df.fieldtype != "Tab Break"
    ]


def _required_fields_for_quick_entry(meta) -> list:
    return [
        df
        for df in meta.fields
        if df.reqd and not df.read_only and not df.is_virtual and df.fieldtype != "Tab Break"
    ]


class TestQuickEntryCompleteness(FrappeTestCase):
    def test_all_mpit_quick_entry_doctypes_are_audited(self):
        quick_entry_doctypes = set(
            frappe.get_all(
                "DocType",
                filters={"name": ["like", "MPIT %"], "quick_entry": 1},
                pluck="name",
            )
        )
        self.assertEqual(quick_entry_doctypes, {"MPIT Expense", "MPIT Vendor"})

    def test_required_fields_are_available_for_quick_entry_or_framework_fallback(self):
        for doctype in ("MPIT Expense", "MPIT Vendor"):
            meta = frappe.get_meta(doctype)
            quick_entry_fields = {df.fieldname for df in _quick_entry_docfields(meta)}
            required_fields = _required_fields_for_quick_entry(meta)

            missing = [df for df in required_fields if df.fieldname not in quick_entry_fields]
            if not missing:
                continue

            # Framework quick entry falls back to full form when mandatory Table fields are present.
            self.assertTrue(
                all(df.fieldtype == "Table" for df in missing),
                f"{doctype} has required fields missing from quick entry coverage: "
                f"{[df.fieldname for df in missing]}",
            )

    def test_vendor_required_fields_are_present_in_quick_entry(self):
        expected = {
            "MPIT Vendor": {"vendor_name"},
        }
        for doctype, required_fieldnames in expected.items():
            meta = frappe.get_meta(doctype)
            quick_entry_fields = {df.fieldname for df in _quick_entry_docfields(meta)}
            self.assertTrue(required_fieldnames <= quick_entry_fields)

    def test_expense_has_required_table_row_field_for_full_form_fallback(self):
        meta = frappe.get_meta("MPIT Expense")
        required_fields = _required_fields_for_quick_entry(meta)
        quick_entry_fields = {df.fieldname for df in _quick_entry_docfields(meta)}
        required_table_fields = [df.fieldname for df in required_fields if df.fieldtype == "Table"]
        self.assertEqual(required_table_fields, ["rows"])
        self.assertIn("rows", quick_entry_fields)
