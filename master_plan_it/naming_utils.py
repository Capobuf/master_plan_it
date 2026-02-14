"""
FILE: master_plan_it/naming_utils.py
SCOPO: Utility per reset sequenza naming quando un documento viene eliminato.
INPUT: Nome documento e prefisso serie.
OUTPUT/SIDE EFFECTS: Decrementa il contatore in tabSeries se il documento era l'ultimo della serie.

NOTE: La tabella `tabSeries` è una tabella interna di Frappe (NON un DocType).
Ha solo due colonne: `name` (chiave primaria) e `current` (contatore intero).
Non ha le colonne standard dei DocType come `modified`, `owner`, ecc.
Per questo si usa frappe.db.sql invece di frappe.db.get_value/set_value.
"""

from __future__ import annotations

import re

import frappe


def reset_series_on_delete(doc_name: str, series_prefix: str, digits: int = 4) -> None:
    """Reset series counter if deleted doc was the last in sequence.
    
    This function is idempotent: calling it multiple times with the same
    arguments produces the same result without side effects.
    
    Args:
        doc_name: The document name being deleted (e.g., "BUD-2025-APP-0003")
        series_prefix: The series prefix (e.g., "BUD-2025-APP-")
        digits: Number of digits in the sequence (default: 4)
    
    Example:
        >>> reset_series_on_delete("BUD-2025-APP-0003", "BUD-2025-APP-", 4)
        # If current counter is 3, decrements to 2
        # If current counter is not 3 (someone else created a doc), does nothing
    """
    if not doc_name or not series_prefix:
        return
    
    # Escape prefix for regex and match sequence number at end
    pattern = rf"^{re.escape(series_prefix)}(\d+)$"
    match = re.match(pattern, doc_name)
    if not match:
        return
    
    deleted_seq = int(match.group(1))
    
    # Build the series key in the format used by frappe.model.naming.getseries
    # The key format is: "PREFIX.####" where # count = digits
    series_key = f"{series_prefix}.{'#' * digits}"
    
    # Query tabSeries directly using SQL because it's not a DocType
    # (doesn't have standard columns like 'modified', 'owner', etc.)
    result = frappe.db.sql(
        """SELECT current FROM `tabSeries` WHERE name = %s""",
        (series_key,),
        as_dict=True
    )
    
    if not result:
        # Series key doesn't exist in table - nothing to reset
        return
    
    current = int(result[0].current)
    
    # Only reset if this was the last document in the sequence
    # This prevents decrementing when older documents are deleted
    if deleted_seq == current:
        new_value = max(current - 1, 0)
        frappe.db.sql(
            """UPDATE `tabSeries` SET current = %s WHERE name = %s""",
            (new_value, series_key)
        )


def sync_series_to_max(doctype: str, series_prefix: str, digits: int, name_field: str = "name") -> int:
    """Ensure the Series counter is >= max numeric suffix for the given prefix.

    This prevents collisions when docs were inserted/renamed manually and the
    Series counter was not updated. It is idempotent and safe to call on every
    autoname for doctypes that follow PREFIX + numeric suffix naming.
    """
    if not doctype or not series_prefix:
        return 0

    max_existing = _get_max_numeric_suffix(doctype, series_prefix, name_field)
    if max_existing <= 0:
        return 0

    from frappe.model.naming import NamingSeries

    series_key = f"{series_prefix}.{'#' * digits}"
    naming_series = NamingSeries(series_key)
    current = int(naming_series.get_current_value() or 0)

    if current < max_existing:
        naming_series.update_counter(max_existing)

    return max_existing


def _get_max_numeric_suffix(doctype: str, series_prefix: str, name_field: str) -> int:
    """Return max numeric suffix for names like PREFIX123 (ignores non-numeric)."""
    prefix_len = len(series_prefix)
    like_pattern = f"{series_prefix}%"
    regex_pattern = rf"^{re.escape(series_prefix)}[0-9]+$"

    row = frappe.db.sql(
        f"""
        SELECT MAX(CAST(SUBSTRING(`{name_field}`, %s) AS UNSIGNED)) AS max_num
        FROM `tab{doctype}`
        WHERE `{name_field}` LIKE %s AND `{name_field}` REGEXP %s
        """,
        (prefix_len + 1, like_pattern, regex_pattern),
        as_dict=True,
    )

    if not row or row[0].max_num is None:
        return 0

    return int(row[0].max_num)
