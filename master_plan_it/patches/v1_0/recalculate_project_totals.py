"""Recalculate all MPIT Project financial totals.

This patch ensures all existing Project documents have their computed
financial fields (planned_total_net, expected_total_net, etc.) correctly
calculated based on their Planned Items and Actual Entries.

Run during migrate to fix any stale data from previous code versions.
"""

import frappe


def execute():
    """Recalculate totals for all MPIT Project documents."""
    projects = frappe.get_all("MPIT Project", pluck="name")

    if not projects:
        return

    frappe.log_error(
        title="Recalculating Project Totals",
        message=f"Starting recalculation for {len(projects)} projects"
    )

    success_count = 0
    error_count = 0

    for name in projects:
        try:
            doc = frappe.get_doc("MPIT Project", name)
            # Call validate to trigger _compute_project_totals
            doc.flags.ignore_permissions = True
            doc.save()
            success_count += 1
        except Exception as e:
            error_count += 1
            frappe.log_error(
                title=f"Project Totals Recalc Error: {name}",
                message=str(e)
            )

    if error_count:
        frappe.log_error(
            title="Project Totals Recalculation Complete",
            message=f"Success: {success_count}, Errors: {error_count}"
        )
