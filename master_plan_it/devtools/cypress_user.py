# -*- coding: utf-8 -*-
"""Development helper for Cypress authentication."""

from __future__ import annotations

import os
from typing import Dict, List

import frappe
from frappe.utils.password import update_password


DEFAULT_ROLES = ["System Manager", "vCIO Manager"]


def ensure() -> Dict[str, object]:
    email = (os.environ.get("CYPRESS_FRAPPE_USER") or "").strip()
    password = os.environ.get("CYPRESS_FRAPPE_PASSWORD") or ""

    if not email:
        frappe.throw("CYPRESS_FRAPPE_USER is required to provision the Cypress test user.")
    if not password:
        frappe.throw("CYPRESS_FRAPPE_PASSWORD is required to provision the Cypress test user.")

    created = False
    if frappe.db.exists("User", email):
        user = frappe.get_doc("User", email)
    else:
        user = frappe.get_doc(
            {
                "doctype": "User",
                "email": email,
                "first_name": "Cypress",
                "last_name": "Runner",
                "enabled": 1,
                "user_type": "System User",
                "send_welcome_email": 0,
            }
        )
        user.insert(ignore_permissions=True)
        created = True

    user.enabled = 1
    user.user_type = "System User"
    if not user.first_name:
        user.first_name = "Cypress"
    if not user.last_name:
        user.last_name = "Runner"

    existing_roles = {row.role for row in user.get("roles", [])}
    assigned_roles: List[str] = []
    for role in DEFAULT_ROLES:
        if frappe.db.exists("Role", role) and role not in existing_roles:
            user.append("roles", {"role": role})
            assigned_roles.append(role)

    user.flags.ignore_permissions = True
    user.flags.ignore_password_policy = True
    user.save(ignore_permissions=True)
    update_password(user.name, password, logout_all_sessions=True)
    frappe.db.commit()

    return {
        "user": user.name,
        "created": created,
        "enabled": bool(user.enabled),
        "roles_added": assigned_roles,
    }
