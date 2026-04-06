from __future__ import annotations

import frappe


def execute() -> None:
    """Remove legacy non-standard MPIT navigation records that conflict with app-level metadata."""
    legacy_sidebars = frappe.get_all(
        "Workspace Sidebar",
        filters={"title": "Master Plan IT", "standard": 0},
        pluck="name",
    )
    for sidebar in legacy_sidebars:
        frappe.delete_doc("Workspace Sidebar", sidebar, force=1, ignore_permissions=True)

    legacy_icons = frappe.get_all(
        "Desktop Icon",
        filters={"label": "Master Plan IT", "standard": 0, "link_type": "Workspace Sidebar"},
        pluck="name",
    )
    for icon in legacy_icons:
        frappe.delete_doc("Desktop Icon", icon, force=1, ignore_permissions=True)
