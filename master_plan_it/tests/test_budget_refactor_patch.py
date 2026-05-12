from __future__ import annotations

import datetime
from unittest.mock import patch

import frappe
from frappe.tests.utils import FrappeTestCase

from master_plan_it.patches.v1_1 import refactor_budget_model_project_contract_expense as refactor_patch


class TestBudgetRefactorPatch(FrappeTestCase):
    def setUp(self):
        frappe.set_user("Administrator")
        self.cost_center = ensure_cost_center("CC-PATCH-TEST")

    def test_project_legacy_states_are_migrated(self):
        next_year = datetime.date.today().year + 1
        ensure_year(next_year)

        open_project = _make_project("Patch Open", self.cost_center)
        on_hold_project = _make_project("Patch On Hold", self.cost_center)
        completed_project = _make_project("Patch Completed", self.cost_center)
        cancelled_project = _make_project("Patch Cancelled", self.cost_center)

        frappe.db.set_value("MPIT Project", open_project, "workflow_state", "Open", update_modified=False)
        frappe.db.set_value("MPIT Project", on_hold_project, "workflow_state", "On Hold", update_modified=False)
        frappe.db.set_value("MPIT Project", completed_project, "workflow_state", "Completed", update_modified=False)
        frappe.db.set_value("MPIT Project", cancelled_project, "workflow_state", "Cancelled", update_modified=False)

        refactor_patch._migrate_project_states()

        self.assertEqual(frappe.db.get_value("MPIT Project", open_project, "workflow_state"), "Idea")
        self.assertEqual(frappe.db.get_value("MPIT Project", on_hold_project, "workflow_state"), "Idea")
        self.assertEqual(frappe.db.get_value("MPIT Project", completed_project, "workflow_state"), "Approved")
        self.assertEqual(frappe.db.get_value("MPIT Project", cancelled_project, "workflow_state"), "Deferred")
        self.assertEqual(frappe.db.get_value("MPIT Project", cancelled_project, "deferred_to_year"), str(next_year))

    def test_patch_fails_when_cancelled_projects_exist_and_next_year_is_missing(self):
        cancelled_project = _make_project("Patch Cancelled Missing Year", self.cost_center)
        frappe.db.set_value("MPIT Project", cancelled_project, "workflow_state", "Cancelled", update_modified=False)

        class _FixedDate(datetime.date):
            @classmethod
            def today(cls):
                return cls(2998, 6, 1)

        with patch.object(refactor_patch.datetime, "date", _FixedDate):
            with self.assertRaises(frappe.ValidationError):
                refactor_patch._migrate_project_states()


def _make_project(title: str, cost_center: str) -> str:
    doc = frappe.get_doc(
        {
            "doctype": "MPIT Project",
            "title": title,
            "workflow_state": "Proposed",
            "cost_center": cost_center,
        }
    ).insert()
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
