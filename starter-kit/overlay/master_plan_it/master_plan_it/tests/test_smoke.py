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
