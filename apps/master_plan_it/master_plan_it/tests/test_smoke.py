# -*- coding: utf-8 -*-
"""MPIT smoke tests

Intention:
- Fail loudly if core MPIT DocTypes are missing after install/sync.

Run:
  bench --site <site> run-tests --app master_plan_it
"""

import frappe


def test_required_doctypes_exist():
    required = [
        "MPIT Vendor",
        "MPIT Category",
        "MPIT Contract",
        "MPIT Baseline Expense",
        "MPIT Budget",
        "MPIT Budget Line",
        "MPIT Actual Entry",
        "MPIT Project",
    ]
    missing = [dt for dt in required if not frappe.db.exists("DocType", dt)]
    assert not missing, f"Missing MPIT DocTypes: {missing}. Run sync_all then migrate."


def test_roles_and_workflows_exist():
    roles = ["vCIO Manager", "Client Editor", "Client Viewer"]
    workflows = ["MPIT Budget Workflow", "MPIT Budget Amendment Workflow"]

    missing_roles = [r for r in roles if not frappe.db.exists("Role", r)]
    missing_workflows = [w for w in workflows if not frappe.db.exists("Workflow", w)]

    assert not missing_roles, f"Missing MPIT Roles: {missing_roles}. Run sync_all."
    assert not missing_workflows, f"Missing MPIT Workflows: {missing_workflows}. Run sync_all."
