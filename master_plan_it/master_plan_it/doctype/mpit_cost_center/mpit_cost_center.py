from __future__ import annotations

import datetime
import re

import frappe
from frappe import _
from frappe.model.document import Document
from frappe.utils.nestedset import NestedSet

from master_plan_it.master_plan_it.financial_engine import get_cost_center_financial_summary


class MPITCostCenter(NestedSet, Document):
    def validate(self):
        self._ensure_abbr()

    def _ensure_abbr(self) -> None:
        raw = self.abbr or self.cost_center_name or self.name or ""
        base = self._slugify(raw)
        abbr = base or "CC"

        suffix = 2
        while self._abbr_conflicts(abbr):
            abbr = f"{base}-{suffix}"
            suffix += 1
        self.abbr = abbr

    @staticmethod
    def _slugify(value: str) -> str:
        cleaned = re.sub(r"[^A-Za-z0-9]+", "-", value or "").strip("-").upper()
        if len(cleaned) > 12:
            cleaned = cleaned[:12].rstrip("-") or cleaned[:12]
        return cleaned or "CC"

    def _abbr_conflicts(self, abbr: str) -> bool:
        filters = {"abbr": abbr}
        if self.name:
            filters["name"] = ["!=", self.name]
        return bool(frappe.db.exists("MPIT Cost Center", filters))


@frappe.whitelist()
def get_cost_center_financial_summary_data(cost_center: str, year: str | None = None) -> dict:
    if not cost_center:
        return {}
    if not frappe.db.exists("MPIT Cost Center", cost_center):
        frappe.throw(_("Cost Center {0} does not exist.").format(cost_center))

    year_name = _resolve_year_name(year)
    summary = get_cost_center_financial_summary(year_name, cost_center)
    summary["year"] = year_name
    return summary


def _resolve_year_name(year: str | None) -> str:
    if year:
        return str(year)

    today = datetime.date.today()
    current = frappe.db.get_value(
        "MPIT Year",
        {"start_date": ["<=", today], "end_date": [">=", today]},
        "name",
    )
    if current:
        return str(current)

    fallback = frappe.db.get_value("MPIT Year", {}, "name", order_by="year desc")
    if fallback:
        return str(fallback)

    return str(today.year)
