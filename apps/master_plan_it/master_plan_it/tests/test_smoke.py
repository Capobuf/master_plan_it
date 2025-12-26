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
    assert not missing, f"Missing MPIT DocTypes: {missing}. Run migrate / update the site so Frappe syncs app files."


def test_roles_exist():
    roles = ["vCIO Manager", "Client Editor", "Client Viewer"]

    missing_roles = [r for r in roles if not frappe.db.exists("Role", r)]

    assert not missing_roles, f"Missing MPIT Roles: {missing_roles}. Run migrate / update the site so Frappe syncs app files."
