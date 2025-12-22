# -*- coding: utf-8 -*-
"""MPIT DevTools: verify (deterministic checks)

ENTRYPOINT
- bench --site <site> execute master_plan_it.devtools.verify.run
"""

from __future__ import annotations

from typing import Dict, List

import frappe


REQUIRED_DOCTYPES = [
    "MPIT Settings",
    "MPIT Year",
    "MPIT Vendor",
    "MPIT Category",
    "MPIT Contract",
    "MPIT Baseline Expense",
    "MPIT Budget",
    "MPIT Budget Line",
    "MPIT Budget Amendment",
    "MPIT Amendment Line",
    "MPIT Actual Entry",
    "MPIT Project",
    "MPIT Project Allocation",
    "MPIT Project Quote",
    "MPIT Project Milestone",
]


def run() -> Dict[str, List[str]]:
    missing = [dt for dt in REQUIRED_DOCTYPES if not frappe.db.exists("DocType", dt)]
    return {"missing_doctypes": missing, "ok": [] if missing else ["all_required_doctypes_present"]}
