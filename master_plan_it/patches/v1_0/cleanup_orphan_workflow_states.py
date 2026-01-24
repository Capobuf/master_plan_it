# -*- coding: utf-8 -*-
"""Migrate orphan workflow states to valid states.

This patch ensures idempotency across installations by migrating any
documents with states that are no longer valid in the workflow.
"""
import frappe


def execute():
    """Migrate orphan workflow states to valid states."""
    # Budget: "In Review" → "Proposed" (In Review was removed from workflow)
    migrated = frappe.db.sql(
        """
        UPDATE `tabMPIT Budget`
        SET workflow_state = 'Proposed'
        WHERE workflow_state = 'In Review'
        """
    )

    # Check if any rows were affected
    affected_rows = frappe.db.sql("SELECT ROW_COUNT()")[0][0]
    if affected_rows > 0:
        frappe.logger().info(
            f"Migrated {affected_rows} Budget(s) from 'In Review' to 'Proposed'"
        )

    frappe.db.commit()
