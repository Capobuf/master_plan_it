from __future__ import annotations

import frappe
from frappe import _

from master_plan_it.master_plan_it.financial_engine import get_economic_position_dataset


def execute(filters=None):
    filters = frappe._dict(filters or {})
    dataset = get_economic_position_dataset(filters)
    return _get_columns(filters), dataset.get("rows", []), None, dataset.get("chart"), _get_report_summary(dataset.get("summary", {}))


def _get_columns(filters) -> list[dict]:
    return [
        {"label": _("Group"), "fieldname": "group_label", "fieldtype": "Data", "width": 220},
        {"label": _("Operating Budget"), "fieldname": "operating_budget", "fieldtype": "Currency", "width": 130},
        {"label": _("Plafond"), "fieldname": "plafond_total", "fieldtype": "Currency", "width": 120},
        {"label": _("Actual Standard"), "fieldname": "actual_standard", "fieldtype": "Currency", "width": 130},
        {"label": _("Actual On Plafond"), "fieldname": "actual_on_plafond", "fieldtype": "Currency", "width": 140},
        {"label": _("Extra Budget"), "fieldname": "actual_extra", "fieldtype": "Currency", "width": 120},
        {"label": _("Actual Total"), "fieldname": "actual_total", "fieldtype": "Currency", "width": 130},
        {"label": _("Forecast Remaining"), "fieldname": "forecast_remaining", "fieldtype": "Currency", "width": 150},
        {"label": _("Year-end Forecast"), "fieldname": "year_end_forecast", "fieldtype": "Currency", "width": 150},
        {"label": _("Plafond Remaining"), "fieldname": "plafond_remaining", "fieldtype": "Currency", "width": 150},
        {"label": _("Plafond Over"), "fieldname": "plafond_over", "fieldtype": "Currency", "width": 130},
        {"label": _("Remaining / Over"), "fieldname": "remaining_or_over", "fieldtype": "Currency", "width": 150},
        {"label": _("Usage %"), "fieldname": "usage_percent", "fieldtype": "Percent", "width": 100},
        {"label": _("Status"), "fieldname": "status", "fieldtype": "Data", "width": 100},
    ]


def _get_report_summary(summary: dict) -> list[dict]:
    return [
        {"label": _("Actual Total"), "value": summary.get("actual_total", 0), "datatype": "Currency", "indicator": "Blue"},
        {"label": _("Year-end Forecast"), "value": summary.get("year_end_forecast", 0), "datatype": "Currency", "indicator": "Orange"},
        {"label": _("Remaining / Over"), "value": summary.get("remaining_or_over", 0), "datatype": "Currency", "indicator": "Green"},
        {"label": _("Critical Rows"), "value": summary.get("critical_rows", 0), "datatype": "Int", "indicator": "Red"},
    ]
