"""
FILE: master_plan_it/naming_utils.py
SCOPO: Utility per gestione sequenze naming (sync counter con max esistente).
INPUT: DocType, prefisso serie, digits.
OUTPUT/SIDE EFFECTS: Sincronizza il contatore in tabSeries con il max numerico esistente.

NOTE: La tabella `tabSeries` è una tabella interna di Frappe (NON un DocType).
Ha solo due colonne: `name` (chiave primaria) e `current` (contatore intero).
Non ha le colonne standard dei DocType come `modified`, `owner`, ecc.
Per questo si usa frappe.db.sql invece di frappe.db.get_value/set_value.
"""

from __future__ import annotations

import re

import frappe


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
