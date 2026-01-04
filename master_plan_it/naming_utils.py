"""
FILE: master_plan_it/naming_utils.py
SCOPO: Utility per reset sequenza naming quando un documento viene eliminato.
INPUT: Nome documento e prefisso serie.
OUTPUT/SIDE EFFECTS: Decrementa il contatore in tabSeries se il documento era l'ultimo della serie.
"""

from __future__ import annotations

import re

import frappe


def reset_series_on_delete(doc_name: str, series_prefix: str, digits: int = 4) -> None:
    """Reset series counter if deleted doc was the last in sequence.
    
    Args:
        doc_name: The document name being deleted (e.g., "BUD-2025-APP-0003")
        series_prefix: The series prefix (e.g., "BUD-2025-APP-")
        digits: Number of digits in the sequence (default: 4)
    """
    if not doc_name or not series_prefix:
        return
    
    # Escape prefix for regex and match sequence number at end
    pattern = rf"^{re.escape(series_prefix)}(\d+)$"
    match = re.match(pattern, doc_name)
    if not match:
        return
    
    deleted_seq = int(match.group(1))
    
    # Get current counter from tabSeries
    # The series key format used by getseries is: "PREFIX.####" where # count = digits
    series_key = f"{series_prefix}.{'#' * digits}"
    
    current = frappe.db.get_value("Series", series_key, "current")
    if current is None:
        return
    
    current = int(current)
    
    # Only reset if this was the last document in the sequence
    if deleted_seq == current:
        new_value = current - 1
        if new_value < 0:
            new_value = 0
        frappe.db.set_value("Series", series_key, "current", new_value)
