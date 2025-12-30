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
    "MPIT Budget",
    "MPIT Budget Line",
    "MPIT Actual Entry",
    "MPIT Project",
    "MPIT Project Allocation",
    "MPIT Project Quote",
    "MPIT Project Milestone",
]
REQUIRED_ROLES = ["vCIO Manager", "Client Editor", "Client Viewer"]
WORKSPACE_NAME = "Master Plan IT"
REQUIRED_REPORTS = [
    "MPIT Approved Budget vs Actual",
    "MPIT Current Budget vs Actual",
    "MPIT Renewals Window",
    "MPIT Projects Planned vs Actual",
]
REQUIRED_DASHBOARD = "Master Plan IT Overview"
REQUIRED_DASHBOARD_CHARTS = [
    "MPIT Approved Budget vs Actual",
    "MPIT Current Budget vs Actual",
    "MPIT Renewals Window (by Month)",
    "MPIT Projects Planned vs Actual",
]
REQUIRED_NUMBER_CARDS = [
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
    workspace_public = None
    workspace_roles_missing: List[str] = []
    if not workspace_missing:
        ws = frappe.get_doc("Workspace", WORKSPACE_NAME)
        workspace_public = bool(ws.public)
        desired_roles = set(REQUIRED_ROLES + ["System Manager"])
        current_roles = {r.role for r in ws.get("roles", [])}
        workspace_roles_missing = sorted(desired_roles - current_roles)

    ok: List[str] = []
    dashboard_missing = not frappe.db.exists("Dashboard", REQUIRED_DASHBOARD)

    if not any([
        missing_doctypes,
        missing_roles,
        missing_reports,
        missing_dashboard_charts,
        missing_number_cards,
        workspace_missing,
        workspace_public,
        workspace_roles_missing,
        dashboard_missing,
    ]):
        ok.append("all_required_entities_present")

    return {
        "missing_doctypes": missing_doctypes,
        "missing_roles": missing_roles,
        "missing_reports": missing_reports,
        "missing_dashboard_charts": missing_dashboard_charts,
        "missing_number_cards": missing_number_cards,
        "workspace_missing": workspace_missing,
        "workspace_public": workspace_public,
        "workspace_roles_missing": workspace_roles_missing,
        "dashboard_missing": dashboard_missing,
        "ok": ok,
    }
