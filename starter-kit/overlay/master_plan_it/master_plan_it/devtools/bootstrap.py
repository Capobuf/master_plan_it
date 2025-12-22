# -*- coding: utf-8 -*-
"""MPIT DevTools: bootstrap (tenant defaults)

ENTRYPOINT
- bench --site <site> execute master_plan_it.devtools.bootstrap.run --kwargs '{"step":"tenant"}'

This is intentionally minimal and idempotent.
"""

from __future__ import annotations

import datetime
from typing import Dict, List

import frappe


def _ensure_settings() -> None:
    if frappe.db.exists("MPIT Settings", "MPIT Settings"):
        return
    doc = frappe.get_doc({"doctype": "MPIT Settings"})
    doc.insert(ignore_permissions=True)


def _ensure_year(year: int) -> None:
    # We name MPIT Year by the year number to be deterministic.
    if frappe.db.exists("MPIT Year", str(year)):
        return
    doc = frappe.get_doc({"doctype": "MPIT Year", "year": year})
    doc.name = str(year)
    doc.insert(ignore_permissions=True)


def run(step: str = "tenant") -> Dict[str, List[str]]:
    out: List[str] = []
    if step in ("tenant", "all"):
        _ensure_settings()
        out.append("settings_ok")

        now = datetime.date.today()
        _ensure_year(now.year)
        _ensure_year(now.year + 1)
        out.append("years_ok")

    frappe.db.commit()
    return {"done": out}
