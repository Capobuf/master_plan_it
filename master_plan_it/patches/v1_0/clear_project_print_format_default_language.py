from __future__ import annotations

import frappe


def execute() -> None:
    """Clear persisted default print language to allow user/site language fallback."""
    if not frappe.db.exists("Print Format", "MPIT Project Professional"):
        return

    frappe.db.set_value(
        "Print Format",
        "MPIT Project Professional",
        "default_print_language",
        None,
        update_modified=False,
    )
