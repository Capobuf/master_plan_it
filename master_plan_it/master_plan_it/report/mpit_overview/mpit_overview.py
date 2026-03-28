from __future__ import annotations

import datetime

import frappe
from frappe import _
from frappe.utils import flt

from master_plan_it.master_plan_it.financial_engine import get_overview_dataset


def execute(filters=None):
    filters = frappe._dict(filters or {})
    year = _resolve_year(filters)
    cost_center = filters.get("cost_center")

    dataset = get_overview_dataset(year, cost_center=cost_center)
    data = dataset.get("rows", [])
    summary = dataset.get("summary", {})

    columns = [
        {"label": _("Cost Center"), "fieldname": "cost_center", "fieldtype": "Link", "options": "MPIT Cost Center", "width": 180},
        {"label": _("Forecast Contracts"), "fieldname": "forecast_contracts", "fieldtype": "Currency", "width": 130},
        {"label": _("Forecast Estimate"), "fieldname": "forecast_estimate", "fieldtype": "Currency", "width": 130},
        {"label": _("Forecast Quote"), "fieldname": "forecast_quote", "fieldtype": "Currency", "width": 130},
        {"label": _("Forecast Total"), "fieldname": "forecast_total", "fieldtype": "Currency", "width": 130},
        {"label": _("Actual On Plafond"), "fieldname": "actual_on_plafond", "fieldtype": "Currency", "width": 130},
        {"label": _("Actual Extra"), "fieldname": "actual_extra", "fieldtype": "Currency", "width": 120},
        {"label": _("Actual Total"), "fieldname": "actual_total", "fieldtype": "Currency", "width": 120},
        {"label": _("Plafond"), "fieldname": "plafond", "fieldtype": "Currency", "width": 120},
        {"label": _("Remaining"), "fieldname": "remaining", "fieldtype": "Currency", "width": 120},
        {"label": _("Over"), "fieldname": "over", "fieldtype": "Currency", "width": 100},
    ]

    report_summary = [
        {"label": _("Forecast"), "value": flt(summary.get("forecast_total"), 2), "indicator": "Blue"},
        {"label": _("Actual"), "value": flt(summary.get("actual_total"), 2), "indicator": "Orange"},
        {"label": _("Plafond"), "value": flt(summary.get("plafond"), 2), "indicator": "Green"},
        {"label": _("Remaining"), "value": flt(summary.get("remaining"), 2), "indicator": "Green"},
        {"label": _("Over Plafond"), "value": flt(summary.get("over"), 2), "indicator": "Red"},
        {"label": _("Extra"), "value": flt(summary.get("actual_extra"), 2), "indicator": "Orange"},
    ]

    chart = _build_chart(data)
    return columns, data, None, chart, report_summary


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


def _build_chart(rows: list[dict]) -> dict | None:
    if not rows:
        return None

    labels = [row.get("cost_center") for row in rows]
    forecast = [flt(row.get("forecast_total"), 2) for row in rows]
    actual = [flt(row.get("actual_total"), 2) for row in rows]

    return {
        "data": {
            "labels": labels,
            "datasets": [
                {"name": _("Forecast"), "values": forecast},
                {"name": _("Actual"), "values": actual},
            ],
        },
        "type": "bar",
        "axis_options": {"x_axis_mode": "tick", "y_axis_mode": "tick"},
    }
