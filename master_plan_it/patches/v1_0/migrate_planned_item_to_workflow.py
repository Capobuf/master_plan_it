"""Migrate MPIT Planned Item from docstatus to workflow_state.

This patch is idempotent - safe to run multiple times on any installation.
Converts existing docstatus values to workflow_state and resets docstatus to 0.
"""
import frappe


def execute():
    # Check if workflow_state column exists (migration order check)
    if not frappe.db.has_column("MPIT Planned Item", "workflow_state"):
        # Column doesn't exist yet - Frappe will create it during sync
        # The patch will be re-run after column creation
        return
    
    # Migrate existing records based on docstatus
    # Only update if workflow_state is NULL or empty (idempotent)
    frappe.db.sql("""
        UPDATE `tabMPIT Planned Item`
        SET workflow_state = CASE
            WHEN docstatus = 0 THEN 'Draft'
            WHEN docstatus = 1 THEN 'Submitted'
            WHEN docstatus = 2 THEN 'Cancelled'
            ELSE 'Draft'
        END
        WHERE workflow_state IS NULL OR workflow_state = ''
    """)
    
    # Reset docstatus to 0 for all records (workflow manages state now)
    # This is safe because the workflow uses doc_status: "0" for all states
    frappe.db.sql("""
        UPDATE `tabMPIT Planned Item`
        SET docstatus = 0
        WHERE docstatus != 0
    """)
    
    frappe.db.commit()
    
    # Log migration stats
    total = frappe.db.count("MPIT Planned Item")
    if total > 0:
        frappe.log_error(
            f"Migrated {total} MPIT Planned Items from docstatus to workflow_state",
            "Planned Item Workflow Migration"
        )
