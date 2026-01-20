# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

"""
Migrate tabSeries keys from hardcoded #### format to dynamic digits format.

This patch fixes a bug where autoname() used hardcoded #### but on_trash()
used dynamic digits from settings, causing series key mismatch and
preventing correct counter reset on document deletion.

Idempotent: if old key doesn't exist or new key already exists, patch is skipped.
"""

from __future__ import annotations

import frappe
from master_plan_it import mpit_defaults


def execute():
    """Migrate series keys for Project and Actual Entry doctypes."""
    _migrate_series("PRJ-", mpit_defaults.get_project_series, "MPIT Project")
    _migrate_series("AE-", mpit_defaults.get_actual_entry_series, "MPIT Actual Entry")
    frappe.db.commit()


def _migrate_series(prefix: str, get_series_fn, doctype: str) -> None:
    """Migrate a single series key from old hardcoded format to new dynamic format.
    
    Handles three cases:
    1. Old key exists → migrate counter to new key
    2. Old key doesn't exist but docs exist → create new key with max(existing IDs)
    3. Neither exists → no action needed (fresh install)
    
    Args:
        prefix: The series prefix (e.g., "PRJ-", "AE-")
        get_series_fn: Function that returns (prefix, digits) tuple from settings
        doctype: DocType name to check for existing documents
    """
    import re
    
    _, digits = get_series_fn()
    
    old_key = f"{prefix}.####"
    new_key = f"{prefix}.{'#' * digits}"
    
    # No migration needed if keys are the same (digits=4)
    if old_key == new_key:
        return
    
    # Idempotency check: skip if new key already exists
    new_exists = frappe.db.sql(
        "SELECT current FROM `tabSeries` WHERE name = %s", (new_key,), as_dict=True
    )
    if new_exists:
        return  # Already migrated
    
    # Check if old key exists in tabSeries
    old_row = frappe.db.sql(
        "SELECT current FROM `tabSeries` WHERE name = %s",
        (old_key,), as_dict=True
    )
    
    if old_row:
        # Case 1: Old key exists → migrate its value
        current_val = old_row[0].current
    else:
        # Case 2: Old key doesn't exist → check for existing documents
        # and set counter to max existing ID to avoid collisions
        existing_names = frappe.db.get_all(doctype, fields=["name"])
        max_num = 0
        # Extract numeric suffix from names like "PRJ-3", "AE-15"
        pattern = rf"^{re.escape(prefix)}(\d+)$"
        for row in existing_names:
            match = re.match(pattern, row.name)
            if match:
                max_num = max(max_num, int(match.group(1)))
        
        if max_num == 0:
            # Case 3: No existing docs → nothing to do
            return
        
        current_val = max_num
    
    # Insert new key with calculated value
    frappe.db.sql(
        "INSERT INTO `tabSeries` (name, current) VALUES (%s, %s)",
        (new_key, current_val)
    )
    
    # Delete old key if it existed
    if old_row:
        frappe.db.sql("DELETE FROM `tabSeries` WHERE name = %s", (old_key,))
