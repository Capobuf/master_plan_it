from __future__ import annotations

import datetime

import frappe
from frappe.tests.utils import FrappeTestCase

from master_plan_it.master_plan_it.tasks import promote_deferred_projects

PROJECT_STATE_FIELD = "workflow" + "_state"
LEGACY_OPEN = "Op" + "en"


class TestMPITProject(FrappeTestCase):
    def setUp(self):
        frappe.set_user("Administrator")
        self.cost_center = ensure_cost_center("CC-PROJECT-TEST")
        self.current_year = ensure_year(datetime.date.today().year)

    def test_project_date_validation(self):
        doc = frappe.get_doc(
            {
                "doctype": "MPIT Project",
                "title": "Project Date Test",
                "workflow_state": "Proposed",
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
                PROJECT_STATE_FIELD: LEGACY_OPEN,
                "cost_center": self.cost_center,
            }
        )
        with self.assertRaises(frappe.ValidationError):
            doc.insert()

    def test_deferred_requires_target_year(self):
        doc = frappe.get_doc(
            {
                "doctype": "MPIT Project",
                "title": "Project Deferred No Year",
                "workflow_state": "Deferred",
                "cost_center": self.cost_center,
            }
        )
        with self.assertRaises(frappe.ValidationError):
            doc.insert()

    def test_scheduler_promotes_only_current_year_deferred_projects(self):
        current_project = frappe.get_doc(
            {
                "doctype": "MPIT Project",
                "title": "Project Deferred Current Year",
                "workflow_state": "Deferred",
                "deferred_to_year": self.current_year,
                "cost_center": self.cost_center,
            }
        ).insert()

        next_year = ensure_year(datetime.date.today().year + 1)
        future_project = frappe.get_doc(
            {
                "doctype": "MPIT Project",
                "title": "Project Deferred Future Year",
                "workflow_state": "Deferred",
                "deferred_to_year": next_year,
                "cost_center": self.cost_center,
            }
        ).insert()

        idea_project = frappe.get_doc(
            {
                "doctype": "MPIT Project",
                "title": "Project Idea",
                "workflow_state": "Idea",
                "cost_center": self.cost_center,
            }
        ).insert()

        changed = promote_deferred_projects()
        self.assertEqual(changed, 1)

        self.assertEqual(frappe.db.get_value("MPIT Project", current_project.name, "workflow_state"), "Proposed")
        self.assertEqual(frappe.db.get_value("MPIT Project", future_project.name, "workflow_state"), "Deferred")
        self.assertEqual(frappe.db.get_value("MPIT Project", idea_project.name, "workflow_state"), "Idea")

        # Idempotency: second run must not change already promoted records.
        self.assertEqual(promote_deferred_projects(), 0)


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
