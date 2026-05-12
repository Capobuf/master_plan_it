from __future__ import annotations

import datetime

import frappe
from frappe import _
from frappe.utils import getdate


PROJECT_STATUS_MAP = {
    "Open": "Idea",
    "On Hold": "Idea",
    "Completed": "Approved",
}


def execute() -> None:
    _reload_budget_doctypes()
    _migrate_project_states()
    _migrate_contract_statuses()
    _backfill_row_vendor_from_parent_vendor()


def _reload_budget_doctypes() -> None:
    frappe.reload_doc("master_plan_it", "doctype", "mpit_project", force=True)
    frappe.reload_doc("master_plan_it", "doctype", "mpit_contract", force=True)
    frappe.reload_doc("master_plan_it", "doctype", "mpit_expense", force=True)
    frappe.reload_doc("master_plan_it", "doctype", "mpit_expense_row", force=True)


def _migrate_project_states() -> None:
    for old_status, new_status in PROJECT_STATUS_MAP.items():
        frappe.db.sql(
            """
            UPDATE `tabMPIT Project`
            SET workflow_state = %(new_status)s
            WHERE workflow_state = %(old_status)s
            """,
            {"new_status": new_status, "old_status": old_status},
        )

    cancelled_count = frappe.db.count("MPIT Project", {"workflow_state": "Cancelled"})
    if not cancelled_count:
        return

    next_year_name = str(datetime.date.today().year + 1)
    if not frappe.db.exists("MPIT Year", next_year_name):
        frappe.throw(
            _(
                "Cannot migrate Cancelled projects to Deferred because MPIT Year {0} does not exist."
            ).format(next_year_name)
        )

    frappe.db.sql(
        """
        UPDATE `tabMPIT Project`
        SET workflow_state = 'Deferred',
            deferred_to_year = %(next_year)s
        WHERE workflow_state = 'Cancelled'
        """,
        {"next_year": next_year_name},
    )


def _migrate_contract_statuses() -> None:
    today = getdate()
    contract_names = frappe.get_all("MPIT Contract", pluck="name")
    for contract_name in contract_names:
        contract = frappe.get_doc("MPIT Contract", contract_name)
        status = _calculate_contract_status(contract.terms or [], today=today)
        frappe.db.set_value("MPIT Contract", contract_name, "status", status, update_modified=False)


def _calculate_contract_status(terms, today) -> str:
    valid_terms = [term for term in terms if term.from_date]
    if not valid_terms:
        return "Concluded"

    for term in valid_terms:
        if not term.to_date:
            return "Active"
        if getdate(term.to_date) >= today:
            return "Active"
    return "Concluded"


def _backfill_row_vendor_from_parent_vendor() -> None:
    # Backfill only empty row vendors; existing row-level values remain authoritative.
    frappe.db.sql(
        """
        UPDATE `tabMPIT Expense Row` r
        INNER JOIN `tabMPIT Expense` e
            ON e.name = r.parent
        SET r.vendor = e.vendor
        WHERE r.parenttype = 'MPIT Expense'
          AND r.parentfield = 'rows'
          AND (r.vendor IS NULL OR r.vendor = '')
          AND (e.vendor IS NOT NULL AND e.vendor <> '')
        """
    )
