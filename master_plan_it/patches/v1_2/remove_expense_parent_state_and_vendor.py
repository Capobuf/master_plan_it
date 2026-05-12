from __future__ import annotations

import frappe

LEGACY_STATE_FIELD = "workflow" + "_state"
LEGACY_CANCELLED_STATE = "Cancel" + "led"


def execute() -> None:
    if not frappe.db.table_exists("MPIT Expense"):
        return

    _backfill_row_vendor_from_parent_vendor()
    _delete_legacy_cancelled_expenses()


def _backfill_row_vendor_from_parent_vendor() -> None:
    if not (
        frappe.db.table_exists("MPIT Expense Row")
        and frappe.db.has_column("MPIT Expense", "vendor")
    ):
        return

    frappe.db.sql(
        """
        UPDATE `tabMPIT Expense Row` r
        INNER JOIN `tabMPIT Expense` e
            ON e.name = r.parent
        SET r.vendor = e.vendor
        WHERE r.parenttype = 'MPIT Expense'
          AND r.parentfield = 'rows'
          AND COALESCE(e.vendor, '') <> ''
          AND COALESCE(r.vendor, '') = ''
        """
    )


def _delete_legacy_cancelled_expenses() -> None:
    if not frappe.db.has_column("MPIT Expense", LEGACY_STATE_FIELD):
        return

    expense_names = frappe.get_all(
        "MPIT Expense",
        filters={LEGACY_STATE_FIELD: LEGACY_CANCELLED_STATE},
        pluck="name",
        limit=None,
    )
    for expense_name in expense_names:
        frappe.delete_doc(
            "MPIT Expense",
            expense_name,
            force=True,
            ignore_permissions=True,
            ignore_missing=True,
            delete_permanently=True,
        )
