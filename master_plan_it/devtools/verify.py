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
    "MPIT Contract",
    "MPIT Expense",
    "MPIT Expense Row",
    "MPIT Cost Center",
    "MPIT Project",
]
REQUIRED_ROLES = ["vCIO Manager", "Client Editor", "Client Viewer"]
WORKSPACE_NAME = "Master Plan IT"
REQUIRED_REPORTS = [
    "MPIT Overview",
    "MPIT Monthly Plan",
    "MPIT Expenses",
    "MPIT Project Forecast vs Actual",
    "MPIT Renewals Window",
]
REQUIRED_DASHBOARD_CHARTS = [
    "MPIT Forecast vs Actual by Cost Center",
    "MPIT Monthly Forecast vs Actual",
    "MPIT Plafond Usage by Cost Center",
    "MPIT Renewals Window (by Month)",
]
REQUIRED_NUMBER_CARDS = [
    "MPIT Forecast Total",
    "MPIT Actual Total",
    "MPIT Plafonds",
    "MPIT Remaining Plafond",
    "Renewals 30d",
    "Renewals 60d",
    "Renewals 90d",
    "Expired Contracts",
]


def run() -> Dict[str, List[str]]:
    missing_doctypes = [dt for dt in REQUIRED_DOCTYPES if not frappe.db.exists("DocType", dt)]
    missing_roles = [r for r in REQUIRED_ROLES if not frappe.db.exists("Role", r)]
    missing_reports = [r for r in REQUIRED_REPORTS if not frappe.db.exists("Report", r)]
    missing_dashboard_charts = [c for c in REQUIRED_DASHBOARD_CHARTS if not frappe.db.exists("Dashboard Chart", c)]
    missing_number_cards = [c for c in REQUIRED_NUMBER_CARDS if not frappe.db.exists("Number Card", c)]

    workspace_missing = not frappe.db.exists("Workspace", WORKSPACE_NAME)
    workspace_not_public = None
    workspace_roles_missing: List[str] = []
    if not workspace_missing:
        ws = frappe.get_doc("Workspace", WORKSPACE_NAME)
        workspace_not_public = not bool(ws.public)
        desired_roles = set(REQUIRED_ROLES + ["System Manager"])
        current_roles = {r.role for r in ws.get("roles", [])}
        workspace_roles_missing = sorted(desired_roles - current_roles)

    ok: List[str] = []

    if not any([
        missing_doctypes,
        missing_roles,
        missing_reports,
        missing_dashboard_charts,
        missing_number_cards,
        workspace_missing,
        workspace_not_public,
        workspace_roles_missing,
    ]):
        ok.append("all_required_entities_present")

    return {
        "missing_doctypes": missing_doctypes,
        "missing_roles": missing_roles,
        "missing_reports": missing_reports,
        "missing_dashboard_charts": missing_dashboard_charts,
        "missing_number_cards": missing_number_cards,
        "workspace_missing": workspace_missing,
        "workspace_not_public": workspace_not_public,
        "workspace_roles_missing": workspace_roles_missing,
        "ok": ok,
    }
