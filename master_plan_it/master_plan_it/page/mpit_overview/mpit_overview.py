from __future__ import annotations

import datetime
from typing import Any

import frappe
from frappe import _
from frappe.utils import cint

from master_plan_it.master_plan_it.financial_engine import (
    get_overview_buildup_dataset,
    get_overview_dataset,
    get_overview_lines_dataset,
)

OVERVIEW_REPORT_NAME = "MPIT Overview"
SECTION_SCOPES = {"All", "Contracts", "Expenses", "Plafond"}
EXPENSE_PHASES = {"All", "Estimate", "Quote", "Actual"}


@frappe.whitelist()
def get_overview_page_data(filters: dict | str | None = None, include_lines: int = 0) -> dict[str, Any]:
    _ensure_overview_access()

    normalized_filters = _normalize_filters(filters)
    year = _resolve_year_name(normalized_filters.get("year"))
    normalized_filters["year"] = year

    overview = get_overview_dataset(year, cost_center=normalized_filters.get("cost_center"))
    buildup = get_overview_buildup_dataset(
        year,
        cost_center=normalized_filters.get("cost_center"),
        section_scope=normalized_filters.get("section_scope"),
        contract=normalized_filters.get("contract"),
        project=normalized_filters.get("project"),
        vendor=normalized_filters.get("vendor"),
        show_zero_rows=bool(normalized_filters.get("show_zero_rows")),
    )

    lines_payload: dict[str, Any] = {
        "rows": [],
        "summary": {"total_lines": 0, "annual_total": 0},
        "project_filter_active": bool(normalized_filters.get("project")),
        "loaded": False,
    }
    if cint(include_lines):
        lines_payload = get_overview_lines_dataset(
            year,
            cost_center=normalized_filters.get("cost_center"),
            section_scope=normalized_filters.get("section_scope"),
            expense_phase=normalized_filters.get("expense_phase"),
            contract=normalized_filters.get("contract"),
            project=normalized_filters.get("project"),
            vendor=normalized_filters.get("vendor"),
            show_zero_rows=bool(normalized_filters.get("show_zero_rows")),
        )
        lines_payload["loaded"] = True

    return {
        "filters": normalized_filters,
        "kpis": _build_kpis(overview.get("summary", {})),
        "overview": overview,
        "buildup": buildup,
        "lines": lines_payload,
        "legacy_report": OVERVIEW_REPORT_NAME,
    }


def _build_kpis(summary: dict[str, Any]) -> list[dict[str, Any]]:
    return [
        {"fieldname": "forecast_total", "label": _("Forecast"), "value": summary.get("forecast_total", 0)},
        {"fieldname": "actual_total", "label": _("Actual"), "value": summary.get("actual_total", 0)},
        {"fieldname": "plafond", "label": _("Plafond"), "value": summary.get("plafond", 0)},
        {"fieldname": "remaining", "label": _("Remaining"), "value": summary.get("remaining", 0)},
        {"fieldname": "over", "label": _("Over"), "value": summary.get("over", 0)},
        {"fieldname": "actual_extra", "label": _("Extra"), "value": summary.get("actual_extra", 0)},
    ]


def _normalize_filters(filters: dict | str | None) -> dict[str, Any]:
    payload: dict[str, Any]
    if isinstance(filters, str):
        payload = frappe.parse_json(filters) or {}
    else:
        payload = dict(filters or {})

    section_scope = str(payload.get("section_scope") or "All").strip()
    if section_scope not in SECTION_SCOPES:
        section_scope = "All"

    expense_phase = str(payload.get("expense_phase") or "All").strip()
    if expense_phase not in EXPENSE_PHASES:
        expense_phase = "All"

    return {
        "year": _sanitize_link(payload.get("year")),
        "cost_center": _sanitize_link(payload.get("cost_center")),
        "section_scope": section_scope,
        "expense_phase": expense_phase,
        "contract": _sanitize_link(payload.get("contract")),
        "project": _sanitize_link(payload.get("project")),
        "vendor": _sanitize_link(payload.get("vendor")),
        "show_zero_rows": cint(payload.get("show_zero_rows")),
    }


def _sanitize_link(value: Any) -> str | None:
    if value is None:
        return None
    cleaned = str(value).strip()
    return cleaned or None


def _ensure_overview_access() -> None:
    if not frappe.has_permission("Report", ptype="read", doc=OVERVIEW_REPORT_NAME):
        frappe.throw(
            _("You are not permitted to access MPIT overview data."),
            frappe.PermissionError,
        )


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

