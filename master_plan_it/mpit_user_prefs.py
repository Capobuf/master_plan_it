# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

"""
FILE: master_plan_it/mpit_user_prefs.py
SCOPO: Gestisce preferenze utente MPIT (VAT default, naming, print) con getter tipizzati e creazione automatica.
INPUT: user (email) opzionale, year/budget_type per naming budget.
OUTPUT/SIDE EFFECTS: Ritorna o crea MPIT User Preferences, restituisce prefissi/suffissi naming e VAT defaults; può inserire un nuovo record prefs.
"""

from __future__ import annotations

import frappe
from frappe import _
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


def get_budget_series(user: str | None = None, year: str | None = None, budget_type: str = "Live") -> tuple[str, int, str]:
	"""
	Get Budget naming series components for user based on budget type.
	
	Budget name format: {prefix}{year}-{TOKEN}-{NNNN...}
	where TOKEN is LIVE or APP depending on budget type.
	
	Args:
		user: User email. If None, uses frappe.session.user.
		year: Year name (e.g., "2025"). Required for Budget naming.
		budget_type: "Live" or "Snapshot".
	
	Returns:
		tuple: (prefix, digits, middle) where middle is "{year}-{TOKEN}-"
	"""
	settings = frappe.get_single("MPIT Settings")

	# Budget naming is global (per-site), not per-user.
	# Keeping per-user naming here increases the chance of duplicate/mismatched names
	# when jobs/automations run under different users.
	prefix = settings.budget_prefix_default or "BUD-"
	digits = settings.budget_digits_default or 2
	
	if not year:
		frappe.throw(_("Budget naming requires year parameter (Budget.year field)"))
	
	token_map = {"Live": "LIVE", "Snapshot": "APP"}
	token = token_map.get(budget_type) or budget_type
	middle = f"{year}-{token}-"
	
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
	settings = frappe.get_single("MPIT Settings")

	prefix = prefs.project_prefix or settings.project_prefix_default or "PRJ-"
	digits = prefs.project_sequence_digits or settings.project_digits_default or 2

	return prefix, digits


def get_actual_entry_series(user: str | None = None) -> tuple[str, int]:
	"""Get Exceptions/Allowance naming series components for user."""
	prefs = get_or_create(user)
	settings = frappe.get_single("MPIT Settings")

	prefix = prefs.actual_prefix or settings.actual_prefix_default or "AE-"
	digits = prefs.actual_sequence_digits or settings.actual_digits_default or 2

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


@frappe.whitelist()
def get_vat_defaults(user: str | None = None) -> dict:
	"""
	Return VAT defaults for the given user (falls back to session user).
	"""
	prefs = get_or_create(user or frappe.session.user)
	return {
		"default_vat_rate": prefs.default_vat_rate,
		"default_includes_vat": bool(prefs.default_amount_includes_vat),
	}
