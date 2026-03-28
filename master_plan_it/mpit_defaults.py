# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

from __future__ import annotations

import frappe


def _get_settings():
    return frappe.get_single("MPIT Settings")


def get_default_vat_rate() -> float | None:
    settings = _get_settings()
    return settings.default_vat_rate if settings.default_vat_rate is not None else None


def get_default_includes_vat() -> bool:
    settings = _get_settings()
    return bool(settings.default_amount_includes_vat)


def get_project_series() -> tuple[str, int]:
    settings = _get_settings()
    prefix = settings.project_prefix_default or "PRJ-"
    digits = settings.project_digits_default or 2
    return prefix, digits


def get_contract_series() -> tuple[str, int]:
    settings = _get_settings()
    prefix = settings.contract_prefix_default or "CONTR-"
    digits = settings.contract_digits_default or 2
    return prefix, digits


def get_expense_series() -> tuple[str, int]:
    settings = _get_settings()
    prefix = settings.expense_prefix_default or "EXP-"
    digits = settings.expense_digits_default or 2
    return prefix, digits


@frappe.whitelist()
def get_vat_defaults() -> dict:
    return {
        "default_vat_rate": get_default_vat_rate(),
        "default_includes_vat": get_default_includes_vat(),
    }
