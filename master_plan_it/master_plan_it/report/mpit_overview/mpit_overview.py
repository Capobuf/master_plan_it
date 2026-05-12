from __future__ import annotations

import datetime

import frappe
from frappe import _
from frappe.utils import flt

from master_plan_it.master_plan_it.financial_engine import (
    get_overview_buildup_dataset,
    get_overview_dataset,
    get_overview_lines_dataset,
)

VIEW_SUMMARY = "Summary"
VIEW_BUILDUP = "Build-up"
VIEW_LINES = "Lines"


def execute(filters=None):
    filters = frappe._dict(filters or {})
    year = _resolve_year(filters)
    view_mode = (filters.get("view_mode") or VIEW_SUMMARY).strip()
    financial_view = (filters.get("financial_view") or "Actual with Estimates and Quotes").strip()

    if view_mode == VIEW_BUILDUP:
        return _execute_buildup(filters, year, financial_view)
    if view_mode == VIEW_LINES:
        return _execute_lines(filters, year)
    return _execute_summary(filters, year, financial_view)


# ---------------------------------------------------------------------------
# Summary mode
# ---------------------------------------------------------------------------

def _execute_summary(filters, year: str, financial_view: str):
    show_zero = bool(filters.get("show_zero_rows"))
    cost_center = filters.get("cost_center")

    dataset = get_overview_dataset(
        year,
        cost_center=cost_center,
        project=filters.get("project"),
        contract=filters.get("contract"),
        vendor=filters.get("vendor"),
    )
    data = dataset.get("rows", [])
    summary = dataset.get("summary", {})
    _apply_financial_view(data, summary, financial_view)

    if not show_zero:
        data = [
            r for r in data
            if r.get("forecast_total") or r.get("actual_total") or r.get("plafond")
        ]

    columns = [
        {
            "label": _("Cost Center"),
            "fieldname": "cost_center",
            "fieldtype": "Link",
            "options": "MPIT Cost Center",
            "width": 240,
        },
        *_overview_metric_columns(),
    ]

    report_summary = _build_report_summary_from_dict(summary)
    return columns, data, None, None, report_summary


# ---------------------------------------------------------------------------
# Build-up mode
# ---------------------------------------------------------------------------

def _execute_buildup(filters, year: str, financial_view: str):
    dataset = get_overview_buildup_dataset(
        year,
        cost_center=filters.get("cost_center"),
        section_scope=filters.get("section_scope"),
        contract=filters.get("contract"),
        project=filters.get("project"),
        vendor=filters.get("vendor"),
        show_zero_rows=bool(filters.get("show_zero_rows")),
    )
    data = dataset.get("rows", [])
    summary = dataset.get("summary", {})
    _apply_financial_view(data, summary, financial_view)

    # Build-up uses a Data (not Link) column so indented block rows display cleanly
    columns = [
        {
            "label": _("Cost Center / Item"),
            "fieldname": "cost_center",
            "fieldtype": "Data",
            "width": 260,
        },
        *_overview_metric_columns(),
    ]

    report_summary = _build_report_summary_from_dict(summary)
    return columns, data, None, None, report_summary


# ---------------------------------------------------------------------------
# Lines mode
# ---------------------------------------------------------------------------

def _execute_lines(filters, year: str):
    dataset = get_overview_lines_dataset(
        year,
        cost_center=filters.get("cost_center"),
        section_scope=filters.get("section_scope"),
        expense_phase=filters.get("expense_phase"),
        contract=filters.get("contract"),
        project=filters.get("project"),
        vendor=filters.get("vendor"),
        show_zero_rows=bool(filters.get("show_zero_rows")),
    )
    data = dataset.get("rows", [])
    s = dataset.get("summary", {})

    columns = [
        {
            "label": _("Cost Center"),
            "fieldname": "cost_center",
            "fieldtype": "Link",
            "options": "MPIT Cost Center",
            "width": 160,
        },
        {
            "label": _("Source Type"),
            "fieldname": "source_type",
            "fieldtype": "Data",
            "width": 130,
        },
        {
            "label": _("Source Document"),
            "fieldname": "source_document",
            "fieldtype": "Data",
            "width": 160,
        },
        {
            "label": _("Source Row"),
            "fieldname": "source_row",
            "fieldtype": "Data",
            "width": 120,
            "hidden": 1,
        },
        {
            "label": _("Contract"),
            "fieldname": "contract",
            "fieldtype": "Link",
            "options": "MPIT Contract",
            "width": 150,
        },
        {
            "label": _("Project"),
            "fieldname": "project",
            "fieldtype": "Link",
            "options": "MPIT Project",
            "width": 140,
        },
        {
            "label": _("Project Bucket"),
            "fieldname": "project_bucket",
            "fieldtype": "Data",
            "width": 130,
        },
        {
            "label": _("Vendor"),
            "fieldname": "vendor",
            "fieldtype": "Link",
            "options": "MPIT Vendor",
            "width": 140,
        },
        {
            "label": _("Phase"),
            "fieldname": "expense_phase",
            "fieldtype": "Data",
            "width": 90,
        },
        {
            "label": _("Funding"),
            "fieldname": "funding",
            "fieldtype": "Data",
            "width": 100,
        },
        {
            "label": _("Period Start"),
            "fieldname": "period_start",
            "fieldtype": "Date",
            "width": 100,
        },
        {
            "label": _("Period End"),
            "fieldname": "period_end",
            "fieldtype": "Date",
            "width": 100,
        },
        {
            "label": _("Spend Date"),
            "fieldname": "spend_date",
            "fieldtype": "Date",
            "width": 100,
        },
        {
            "label": _("Amount Net"),
            "fieldname": "amount_net",
            "fieldtype": "Currency",
            "width": 120,
        },
        {
            "label": _("Annual Contribution"),
            "fieldname": "annual_contribution_net",
            "fieldtype": "Currency",
            "width": 130,
        },
        {
            "label": _("State"),
            "fieldname": "logical_state",
            "fieldtype": "Data",
            "width": 100,
        },
    ]

    report_summary = [
        {
            "label": _("Total Lines"),
            "value": s.get("total_lines", 0),
            "indicator": "Blue",
            "datatype": "Int",
        },
        {
            "label": _("Annual Total"),
            "value": flt(s.get("annual_total", 0), 2),
            "indicator": "Blue",
            "datatype": "Currency",
        },
    ]
    return columns, data, None, None, report_summary


# ---------------------------------------------------------------------------
# Shared helpers
# ---------------------------------------------------------------------------

def _build_report_summary_from_dict(summary: dict) -> list[dict]:
    return [
        {
            "label": _("Forecast"),
            "value": flt(summary.get("forecast_total"), 2),
            "indicator": "Blue",
            "datatype": "Currency",
        },
        {
            "label": _("Actual"),
            "value": flt(summary.get("actual_total"), 2),
            "indicator": "Orange",
            "datatype": "Currency",
        },
        {
            "label": _("Approved Budget"),
            "value": flt(summary.get("approved_budget"), 2),
            "indicator": "Blue",
            "datatype": "Currency",
        },
        {
            "label": _("Proposals"),
            "value": flt(summary.get("proposals"), 2),
            "indicator": "Orange",
            "datatype": "Currency",
        },
        {
            "label": _("Ideas"),
            "value": flt(summary.get("ideas"), 2),
            "indicator": "Blue",
            "datatype": "Currency",
        },
        {
            "label": _("Standard"),
            "value": flt(summary.get("actual_standard"), 2),
            "indicator": "Blue",
            "datatype": "Currency",
        },
        {
            "label": _("Plafond"),
            "value": flt(summary.get("plafond"), 2),
            "indicator": "Green",
            "datatype": "Currency",
        },
        {
            "label": _("Remaining"),
            "value": flt(summary.get("remaining"), 2),
            "indicator": "Green",
            "datatype": "Currency",
        },
        {
            "label": _("Over Plafond"),
            "value": flt(summary.get("over"), 2),
            "indicator": "Red",
            "datatype": "Currency",
        },
        {
            "label": _("Extra"),
            "value": flt(summary.get("actual_extra"), 2),
            "indicator": "Orange",
            "datatype": "Currency",
        },
    ]


def _apply_financial_view(data: list[dict], summary: dict, financial_view: str) -> None:
    if financial_view == "Actual":
        for row in data:
            _zero_forecast_cells(row)
        _zero_forecast_cells(summary)
        return

    if financial_view == "Forecast":
        for row in data:
            _zero_actual_cells(row)
        _zero_actual_cells(summary)


def _zero_forecast_cells(row: dict) -> None:
    for fieldname in (
        "forecast_estimate",
        "forecast_quote",
        "forecast_total",
        "approved_budget",
        "proposals",
        "ideas",
    ):
        row[fieldname] = 0


def _zero_actual_cells(row: dict) -> None:
    for fieldname in (
        "actual_standard",
        "actual_on_plafond",
        "actual_extra",
        "actual_total",
    ):
        row[fieldname] = 0


def _overview_metric_columns() -> list[dict]:
    return [
        {
            "label": _("Forecast Estimate"),
            "fieldname": "forecast_estimate",
            "fieldtype": "Currency",
            "width": 150,
        },
        {
            "label": _("Forecast Quote"),
            "fieldname": "forecast_quote",
            "fieldtype": "Currency",
            "width": 140,
        },
        {
            "label": _("Forecast Total"),
            "fieldname": "forecast_total",
            "fieldtype": "Currency",
            "width": 150,
        },
        {
            "label": _("Approved Budget"),
            "fieldname": "approved_budget",
            "fieldtype": "Currency",
            "width": 150,
        },
        {
            "label": _("Proposals"),
            "fieldname": "proposals",
            "fieldtype": "Currency",
            "width": 130,
        },
        {
            "label": _("Ideas"),
            "fieldname": "ideas",
            "fieldtype": "Currency",
            "width": 120,
        },
        {
            "label": _("Actual Standard"),
            "fieldname": "actual_standard",
            "fieldtype": "Currency",
            "width": 140,
        },
        {
            "label": _("Actual On Plafond"),
            "fieldname": "actual_on_plafond",
            "fieldtype": "Currency",
            "width": 150,
        },
        {
            "label": _("Actual Extra"),
            "fieldname": "actual_extra",
            "fieldtype": "Currency",
            "width": 130,
        },
        {
            "label": _("Actual Total"),
            "fieldname": "actual_total",
            "fieldtype": "Currency",
            "width": 140,
        },
        {
            "label": _("Plafond"),
            "fieldname": "plafond",
            "fieldtype": "Currency",
            "width": 130,
        },
        {
            "label": _("Plafond Consumed"),
            "fieldname": "plafond_consumed",
            "fieldtype": "Currency",
            "width": 150,
        },
        {
            "label": _("Remaining"),
            "fieldname": "remaining",
            "fieldtype": "Currency",
            "width": 130,
        },
        {
            "label": _("Over"),
            "fieldname": "over",
            "fieldtype": "Currency",
            "width": 110,
        },
    ]


def _resolve_year(filters) -> str:
    if filters.get("year"):
        return str(filters.get("year"))

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
