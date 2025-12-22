# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

"""
Helper for MPIT User Preferences (per-user defaults for VAT, naming, print).

Provides idempotent get_or_create and typed getters for user preferences.
"""

from __future__ import annotations

import frappe
from frappe.model.document import Document


def get_or_create(user: str | None = None) -> Document:
	"""
	Get or create MPIT User Preferences for the given user.
	
	Args:
		user: User email. If None, uses frappe.session.user.
	
	Returns:
		Document: MPIT User Preferences document (may be unsaved if just created).
	"""
	if not user:
		user = frappe.session.user
	
	# Try to get existing
	prefs_name = frappe.db.get_value("MPIT User Preferences", {"user": user}, "name")
	if prefs_name:
		return frappe.get_doc("MPIT User Preferences", prefs_name)
	
	# Create new with defaults
	prefs = frappe.new_doc("MPIT User Preferences")
	prefs.user = user
	prefs.insert(ignore_permissions=True)
	frappe.db.commit()
	
	return prefs


def get_default_vat_rate(user: str | None = None) -> float | None:
	"""
	Get default VAT rate for user (returns None if not set, 0 is valid).
	
	Args:
		user: User email. If None, uses frappe.session.user.
	
	Returns:
		float | None: VAT rate percentage or None.
	"""
	prefs = get_or_create(user)
	return prefs.default_vat_rate if prefs.default_vat_rate is not None else None


def get_default_includes_vat(user: str | None = None) -> bool:
	"""
	Get default "amount includes VAT" setting for user.
	
	Args:
		user: User email. If None, uses frappe.session.user.
	
	Returns:
		bool: True if amounts should include VAT by default.
	"""
	prefs = get_or_create(user)
	return bool(prefs.default_amount_includes_vat)


def get_budget_series(user: str | None = None, year: str | None = None) -> tuple[str, int, str]:
	"""
	Get Budget naming series components for user.
	
	Budget name format: {prefix}{year}-{NNNN...}
	where {year} is the Budget.year field value (MPIT Year name).
	
	Args:
		user: User email. If None, uses frappe.session.user.
		year: Year name (e.g., "2025"). Required for Budget naming.
	
	Returns:
		tuple: (prefix, digits, middle) where middle is "{year}-"
	"""
	prefs = get_or_create(user)
	prefix = prefs.budget_prefix or "BUD-"
	digits = prefs.budget_sequence_digits or 2
	
	if not year:
		frappe.throw("Budget naming requires year parameter (Budget.year field)")
	
	# Budget must include year in the name: BUD-2025-01
	middle = f"{year}-"
	
	return prefix, digits, middle


def get_project_series(user: str | None = None) -> tuple[str, int]:
	"""
	Get Project naming series components for user.
	
	Project name format: {prefix}{NNNN...}
	
	Args:
		user: User email. If None, uses frappe.session.user.
	
	Returns:
		tuple: (prefix, digits)
	"""
	prefs = get_or_create(user)
	prefix = prefs.project_prefix or "PRJ-"
	digits = prefs.project_sequence_digits or 4
	
	return prefix, digits


def get_show_attachments_in_print(user: str | None = None) -> bool:
	"""
	Get user preference for showing attachments in print formats.
	
	Args:
		user: User email. If None, uses frappe.session.user.
	
	Returns:
		bool: True if attachments should be shown in prints.
	"""
	prefs = get_or_create(user)
	return bool(prefs.show_attachments_in_print)
