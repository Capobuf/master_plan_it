# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

"""
FILE: master_plan_it/mpit_defaults.py
SCOPO: Getter per default globali da MPIT Settings (VAT, naming, print).
INPUT: Opzionalmente year/budget_type per naming Budget.
OUTPUT: Valori default da Settings singleton; nessun side effect.
"""

from __future__ import annotations

import frappe
from frappe import _


def _get_settings():
    """Get cached MPIT Settings singleton."""
    return frappe.get_single("MPIT Settings")


# =============================================================================
# VAT Defaults
# =============================================================================

def get_default_vat_rate() -> float | None:
    """
    Get default VAT rate from Settings (returns None if not set, 0 is valid).
    
    Returns:
        float | None: VAT rate percentage or None.
    """
    settings = _get_settings()
    return settings.default_vat_rate if settings.default_vat_rate is not None else None


def get_default_includes_vat() -> bool:
    """
    Get default "amount includes VAT" setting.
    
    Returns:
        bool: True if amounts should include VAT by default.
    """
    settings = _get_settings()
    return bool(settings.default_amount_includes_vat)


# =============================================================================
# Naming Series
# =============================================================================

def get_budget_series(year: str | None = None, budget_type: str = "Live") -> tuple[str, int, str]:
    """
    Get Budget naming series components.
    
    Budget name format: {prefix}{year}-{TOKEN}-{NNNN...}
    where TOKEN is LIVE or APP depending on budget type.
    
    Args:
        year: Year name (e.g., "2025"). Required for Budget naming.
        budget_type: "Live" or "Snapshot".
    
    Returns:
        tuple: (prefix, digits, middle) where middle is "{year}-{TOKEN}-"
    """
    settings = _get_settings()
    
    prefix = settings.budget_prefix_default or "BUD-"
    digits = settings.budget_digits_default or 2
    
    if not year:
        frappe.throw(_("Budget naming requires year parameter (Budget.year field)"))
    
    token_map = {"Live": "LIVE", "Snapshot": "APP"}
    token = token_map.get(budget_type) or budget_type
    middle = f"{year}-{token}-"
    
    return prefix, digits, middle


def get_project_series() -> tuple[str, int]:
    """
    Get Project naming series components.
    
    Project name format: {prefix}{NNNN...}
    
    Returns:
        tuple: (prefix, digits)
    """
    settings = _get_settings()
    
    prefix = settings.project_prefix_default or "PRJ-"
    digits = settings.project_digits_default or 2
    
    return prefix, digits


def get_actual_entry_series() -> tuple[str, int]:
    """
    Get Exceptions/Allowance naming series components.
    
    Returns:
        tuple: (prefix, digits)
    """
    settings = _get_settings()
    
    prefix = settings.actual_prefix_default or "AE-"
    digits = settings.actual_digits_default or 2
    
    return prefix, digits


def get_contract_series() -> tuple[str, int]:
    """
    Get Contract naming series components.
    
    Returns:
        tuple: (prefix, digits)
    """
    settings = _get_settings()

    prefix = settings.contract_prefix_default or "CONTR-"
    digits = settings.contract_digits_default or 2

    return prefix, digits


# =============================================================================
# Print Settings
# =============================================================================

def get_show_attachments_in_print() -> bool:
    """
    Get setting for showing attachments in print formats.
    
    Returns:
        bool: True if attachments should be shown in prints.
    """
    settings = _get_settings()
    return bool(settings.show_attachments_in_print)


# =============================================================================
# Whitelisted API for JS
# =============================================================================

@frappe.whitelist()
def get_vat_defaults() -> dict:
    """
    Return VAT defaults for use in client-side JS.
    Whitelisted method called by form scripts.
    """
    return {
        "default_vat_rate": get_default_vat_rate(),
        "default_includes_vat": get_default_includes_vat(),
    }
