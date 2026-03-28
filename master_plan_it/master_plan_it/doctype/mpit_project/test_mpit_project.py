from __future__ import annotations

import frappe
from frappe.tests.utils import FrappeTestCase


class TestMPITProject(FrappeTestCase):
    def setUp(self):
        frappe.set_user("Administrator")
        self.cost_center = ensure_cost_center("CC-PROJECT-TEST")

    def test_project_date_validation(self):
        doc = frappe.get_doc(
            {
                "doctype": "MPIT Project",
                "title": "Project Date Test",
                "workflow_state": "Open",
                "cost_center": self.cost_center,
                "start_date": "2026-12-31",
                "end_date": "2026-01-01",
            }
        )
        with self.assertRaises(frappe.ValidationError):
            doc.insert()

    def test_project_rejects_legacy_workflow_states(self):
        doc = frappe.get_doc(
            {
                "doctype": "MPIT Project",
                "title": "Project Legacy State Test",
                "workflow_state": "Draft",
                "cost_center": self.cost_center,
            }
        )
        with self.assertRaises(frappe.ValidationError):
            doc.insert()


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
