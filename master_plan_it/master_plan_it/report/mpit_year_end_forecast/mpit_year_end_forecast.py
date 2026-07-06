from __future__ import annotations

import frappe
from frappe import _

from master_plan_it.master_plan_it.financial_engine import get_period_forecast_dataset


def execute(filters=None):
    filters = frappe._dict(filters or {})
    dataset = get_period_forecast_dataset(filters)
    return _get_columns(filters), dataset.get("rows", []), None, dataset.get("chart"), _get_report_summary(dataset.get("summary", {}))


def _get_columns(filters) -> list[dict]:
    return [
        {"label": _("Period"), "fieldname": "period_label", "fieldtype": "Data", "width": 120},
        {"label": _("Actual"), "fieldname": "actual_amount", "fieldtype": "Currency", "width": 130},
        {"label": _("Forecast Remaining"), "fieldname": "forecast_amount", "fieldtype": "Currency", "width": 150},
        {"label": _("Period Total"), "fieldname": "period_total", "fieldtype": "Currency", "width": 130},
        {"label": _("Actual Cumulative"), "fieldname": "actual_cumulative", "fieldtype": "Currency", "width": 150},
        {"label": _("Forecast Cumulative"), "fieldname": "forecast_cumulative", "fieldtype": "Currency", "width": 160},
        {"label": _("Period Delta"), "fieldname": "period_delta", "fieldtype": "Currency", "width": 130},
        {"label": _("Cumulative Delta"), "fieldname": "cumulative_delta", "fieldtype": "Currency", "width": 150},
    ]


def _get_report_summary(summary: dict) -> list[dict]:
    return [
        {"label": _("Actual Total"), "value": summary.get("actual_total", 0), "datatype": "Currency", "indicator": "Blue"},
        {"label": _("Forecast Total"), "value": summary.get("forecast_total", 0), "datatype": "Currency", "indicator": "Orange"},
        {"label": _("Year-end Forecast"), "value": summary.get("year_end_forecast", 0), "datatype": "Currency", "indicator": "Green"},
    ]
