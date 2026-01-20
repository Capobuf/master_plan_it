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
    _migrate_series("PRJ-", mpit_defaults.get_project_series)
    _migrate_series("AE-", mpit_defaults.get_actual_entry_series)
    frappe.db.commit()


def _migrate_series(prefix: str, get_series_fn) -> None:
    """Migrate a single series key from old hardcoded format to new dynamic format.
    
    Args:
        prefix: The series prefix (e.g., "PRJ-", "AE-")
        get_series_fn: Function that returns (prefix, digits) tuple from settings
    """
    _, digits = get_series_fn()
    
    old_key = f"{prefix}.####"
    new_key = f"{prefix}.{'#' * digits}"
    
    # No migration needed if keys are the same (digits=4)
    if old_key == new_key:
        return
    
    # Check if old key exists in tabSeries
    old_row = frappe.db.sql(
        "SELECT current FROM `tabSeries` WHERE name = %s",
        (old_key,), as_dict=True
    )
    if not old_row:
        return  # Nothing to migrate
    
    # Idempotency check: skip if new key already exists
    new_exists = frappe.db.sql(
        "SELECT 1 FROM `tabSeries` WHERE name = %s", (new_key,)
    )
    if new_exists:
        return  # Already migrated
    
    # Migrate: insert new key with current counter value, then delete old key
    current_val = old_row[0].current
    frappe.db.sql(
        "INSERT INTO `tabSeries` (name, current) VALUES (%s, %s)",
        (new_key, current_val)
    )
    frappe.db.sql("DELETE FROM `tabSeries` WHERE name = %s", (old_key,))
