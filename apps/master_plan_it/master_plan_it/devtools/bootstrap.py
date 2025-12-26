# -*- coding: utf-8 -*-
"""MPIT DevTools: bootstrap (tenant defaults)

Install hooks already provision MPIT Settings and MPIT Year (current + next). This helper
remains available if you need to re-apply roles/workspace in an existing site.

ENTRYPOINT
- bench --site <site> execute master_plan_it.devtools.bootstrap.run --kwargs '{"step":"tenant"}'

This is intentionally minimal and idempotent.
"""

from __future__ import annotations

import datetime
from typing import Dict, List

import frappe

WORKSPACE_NAME = "Master Plan IT"
MPIT_ROLES = ["vCIO Manager", "Client Editor", "Client Viewer"]


def _ensure_roles() -> None:
    for role in MPIT_ROLES:
        if frappe.db.exists("Role", role):
            continue
        doc = frappe.get_doc({"doctype": "Role", "role_name": role, "desk_access": 1})
        doc.insert(ignore_permissions=True)


def _ensure_settings() -> None:
    if frappe.db.exists("MPIT Settings", "MPIT Settings"):
        return
    doc = frappe.get_doc({"doctype": "MPIT Settings"})
    doc.insert(ignore_permissions=True)


def _ensure_year(year: int) -> None:
    # We name MPIT Year by the year number to be deterministic.
    if frappe.db.exists("MPIT Year", str(year)):
        return
    doc = frappe.get_doc({"doctype": "MPIT Year", "year": year})
    doc.name = str(year)
    doc.insert(ignore_permissions=True)


def _ensure_workspace() -> None:
    """Create/update Desk workspace for MPIT (restricted to MPIT roles)."""
    shortcuts = [
        {"label": "Budgets", "link_to": "MPIT Budget", "type": "DocType"},
        {"label": "Budget Amendments", "link_to": "MPIT Budget Amendment", "type": "DocType"},
        {"label": "Actual Entries", "link_to": "MPIT Actual Entry", "type": "DocType"},
        {"label": "Contracts", "link_to": "MPIT Contract", "type": "DocType"},
        {"label": "Projects", "link_to": "MPIT Project", "type": "DocType"},
        {"label": "Baseline Expenses", "link_to": "MPIT Baseline Expense", "type": "DocType"},
        {"label": "Categories", "link_to": "MPIT Category", "type": "DocType"},
        {"label": "Vendors", "link_to": "MPIT Vendor", "type": "DocType"},
    ]
    content_blocks = [
        {"type": "header", "data": {"text": "Master Plan IT", "level": 4, "col": 12}},
    ]
    for sc in shortcuts:
        content_blocks.append({"type": "shortcut", "data": {"shortcut_name": sc["label"], "col": 3}})

    workspace_roles = [{"role": r} for r in MPIT_ROLES + ["System Manager"]]

    if frappe.db.exists("Workspace", WORKSPACE_NAME):
        doc = frappe.get_doc("Workspace", WORKSPACE_NAME)
        dirty = False
        if doc.public:
            doc.public = 0
            dirty = True
        existing_roles = {r.role for r in doc.get("roles", [])}
        desired_roles = {r["role"] for r in workspace_roles}
        if existing_roles != desired_roles:
            doc.roles = []
            for r in workspace_roles:
                doc.append("roles", r)
            dirty = True
        if dirty:
            doc.save(ignore_permissions=True)
        return

    doc = frappe.get_doc({
        "doctype": "Workspace",
        "name": WORKSPACE_NAME,
        "label": WORKSPACE_NAME,
        "title": WORKSPACE_NAME,
        "module": WORKSPACE_NAME,
        "public": 0,
        "is_hidden": 0,
        "content": frappe.as_json(content_blocks),
        "shortcuts": shortcuts,
        "roles": workspace_roles,
    })
    doc.insert(ignore_permissions=True)


def run(step: str = "tenant") -> Dict[str, List[str]]:
    out: List[str] = []
    if step in ("tenant", "all"):
        _ensure_roles()
        out.append("roles_ok")

        _ensure_settings()
        out.append("settings_ok")

        now = datetime.date.today()
        _ensure_year(now.year)
        _ensure_year(now.year + 1)
        out.append("years_ok")

        _ensure_workspace()
        out.append("workspace_ok")

    frappe.db.commit()
    return {"done": out}
