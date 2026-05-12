from __future__ import annotations

import frappe


def execute() -> None:
    if not frappe.db.table_exists("MPIT Expense"):
        return

    for column in ("workflow_state", "vendor"):
        if frappe.db.has_column("MPIT Expense", column):
            frappe.db.sql_ddl(f"ALTER TABLE `tabMPIT Expense` DROP COLUMN `{column}`")
