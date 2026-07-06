from __future__ import annotations

import frappe
from frappe import _

from master_plan_it.master_plan_it.financial_engine import get_budget_variance_dataset


def execute(filters=None):
    filters = frappe._dict(filters or {})
    dataset = get_budget_variance_dataset(filters)
    return _get_columns(filters), dataset.get("rows", []), None, dataset.get("chart"), _get_report_summary(dataset.get("summary", {}))


def _get_columns(filters) -> list[dict]:
    return [
        {"label": _("Group"), "fieldname": "group_label", "fieldtype": "Data", "width": 220},
        {"label": _("Budget"), "fieldname": "budget_amount", "fieldtype": "Currency", "width": 130},
        {"label": _("Actual"), "fieldname": "actual_amount", "fieldtype": "Currency", "width": 130},
        {"label": _("Year-end Forecast"), "fieldname": "forecast_amount", "fieldtype": "Currency", "width": 150},
        {"label": _("Variance Amount"), "fieldname": "variance_amount", "fieldtype": "Currency", "width": 150},
        {"label": _("Variance %"), "fieldname": "variance_percent", "fieldtype": "Percent", "width": 110},
        {"label": _("Main Driver"), "fieldname": "main_driver", "fieldtype": "Data", "width": 130},
        {"label": _("Status"), "fieldname": "status", "fieldtype": "Data", "width": 100},
    ]


def _get_report_summary(summary: dict) -> list[dict]:
    return [
        {"label": _("Budget"), "value": summary.get("budget_amount", 0), "datatype": "Currency", "indicator": "Blue"},
        {"label": _("Forecast"), "value": summary.get("forecast_amount", 0), "datatype": "Currency", "indicator": "Orange"},
        {"label": _("Variance"), "value": summary.get("variance_amount", 0), "datatype": "Currency", "indicator": "Red"},
        {"label": _("Variance Rows"), "value": summary.get("variance_rows", 0), "datatype": "Int", "indicator": "Grey"},
    ]
