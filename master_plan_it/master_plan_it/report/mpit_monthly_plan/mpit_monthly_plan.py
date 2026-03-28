from __future__ import annotations

import datetime

import frappe
from frappe import _
from frappe.utils import flt

from master_plan_it.master_plan_it.financial_engine import get_monthly_forecast_vs_actual


def execute(filters=None):
    filters = frappe._dict(filters or {})
    year = _resolve_year(filters)

    dataset = get_monthly_forecast_vs_actual(
        year,
        cost_center=filters.get("cost_center"),
        project=filters.get("project"),
        contract=filters.get("contract"),
    )

    rows = dataset.get("months", [])
    totals = dataset.get("totals", {})

    columns = [
        {"label": _("Month"), "fieldname": "month", "fieldtype": "Data", "width": 120},
        {"label": _("Forecast"), "fieldname": "forecast", "fieldtype": "Currency", "width": 160},
        {"label": _("Actual"), "fieldname": "actual", "fieldtype": "Currency", "width": 160},
        {"label": _("Delta"), "fieldname": "delta", "fieldtype": "Currency", "width": 160},
    ]

    chart = {
        "data": {
            "labels": [row.get("month") for row in rows],
            "datasets": [
                {"name": _("Forecast"), "values": [flt(row.get("forecast"), 2) for row in rows]},
                {"name": _("Actual"), "values": [flt(row.get("actual"), 2) for row in rows]},
            ],
        },
        "type": "line",
        "axis_options": {"x_axis_mode": "tick", "y_axis_mode": "tick"},
    }

    report_summary = [
        {"label": _("Forecast"), "value": flt(totals.get("forecast_total"), 2), "indicator": "Blue"},
        {"label": _("Actual"), "value": flt(totals.get("actual_total"), 2), "indicator": "Orange"},
        {"label": _("Delta"), "value": flt(totals.get("delta_total"), 2), "indicator": "Green"},
    ]

    return columns, rows, None, chart, report_summary


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
