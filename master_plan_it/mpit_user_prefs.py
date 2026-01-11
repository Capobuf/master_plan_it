"""Helper for MPIT User Preferences.

Provides safe, idempotent accessors and get_or_create.
"""
from __future__ import annotations

from typing import Optional, Dict
import frappe


def get_user_prefs(user: Optional[str] = None) -> Optional[Dict]:
    """Return preferences row as dict or None if missing.

    Does not raise if missing.
    """
    user = user or frappe.session.user
    row = frappe.db.get_value("MPIT User Preferences", {"user": user}, "*", as_dict=True)
    return row


def get_default_vat_rate(user: Optional[str] = None) -> Optional[float]:
    prefs = get_user_prefs(user)
    if not prefs:
        return None
    return prefs.get("default_vat_rate")


def get_default_includes_vat(user: Optional[str] = None) -> Optional[int]:
    prefs = get_user_prefs(user)
    if not prefs:
        return None
    return prefs.get("default_amount_includes_vat")


def get_series_settings(user: Optional[str] = None, target: str = "budget") -> Dict[str, Optional[int]]:
    prefs = get_user_prefs(user)
    if not prefs:
        return {"prefix": None, "digits": None}
    if target == "budget":
        return {"prefix": prefs.get("budget_prefix"), "digits": prefs.get("budget_sequence_digits")}
    return {"prefix": prefs.get("project_prefix"), "digits": prefs.get("project_sequence_digits")}


def get_or_create(user: str):
    """Idempotent get or create for MPIT User Preferences.

    If a record for `user` exists, returns it; otherwise inserts and returns the new doc.
    """
    if not user:
        raise ValueError("user is required")

    exists = frappe.db.exists("MPIT User Preferences", {"user": user})
    if exists:
        return frappe.get_doc("MPIT User Preferences", exists)

    doc = frappe.get_doc({"doctype": "MPIT User Preferences", "user": user})
    doc.insert(ignore_permissions=False)
    return doc
