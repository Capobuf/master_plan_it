from __future__ import annotations

import frappe
from frappe import _

from master_plan_it.master_plan_it.financial_engine import get_what_if_dataset


def execute(filters=None):
    filters = frappe._dict(filters or {})
    dataset = get_what_if_dataset(filters)
    return _get_columns(filters), dataset.get("rows", []), None, dataset.get("chart"), _get_report_summary(dataset.get("summary", {}))


def _get_columns(filters) -> list[dict]:
    if not filters.get("show_detail"):
        return [
            {"label": _("Scenario"), "fieldname": "scenario_label", "fieldtype": "Data", "width": 160},
            {"label": _("Actual Total"), "fieldname": "actual_total", "fieldtype": "Currency", "width": 130},
            {"label": _("Base Forecast"), "fieldname": "base_forecast", "fieldtype": "Currency", "width": 130},
            {"label": _("Scenario Additions"), "fieldname": "scenario_additions", "fieldtype": "Currency", "width": 150},
            {"label": _("Contingency"), "fieldname": "contingency_amount", "fieldtype": "Currency", "width": 130},
            {"label": _("Scenario Total"), "fieldname": "scenario_total", "fieldtype": "Currency", "width": 140},
            {"label": _("Remaining / Over"), "fieldname": "remaining_or_over", "fieldtype": "Currency", "width": 150},
            {"label": _("Status"), "fieldname": "status", "fieldtype": "Data", "width": 100},
        ]

    return [
        {"label": _("Included"), "fieldname": "included", "fieldtype": "Check", "width": 80},
        {"label": _("Component"), "fieldname": "component_type", "fieldtype": "Data", "width": 130},
        {"label": _("Document"), "fieldname": "source_document", "fieldtype": "Data", "width": 170},
        {"label": _("Cost Center"), "fieldname": "cost_center", "fieldtype": "Link", "options": "MPIT Cost Center", "width": 140},
        {"label": _("Project"), "fieldname": "project", "fieldtype": "Link", "options": "MPIT Project", "width": 140},
        {"label": _("Project Stage"), "fieldname": "project_stage", "fieldtype": "Data", "width": 120},
        {"label": _("Vendor"), "fieldname": "vendor", "fieldtype": "Link", "options": "MPIT Vendor", "width": 140},
        {"label": _("Amount"), "fieldname": "amount_net", "fieldtype": "Currency", "width": 120},
        {"label": _("Inclusion Reason"), "fieldname": "inclusion_reason", "fieldtype": "Data", "width": 180},
    ]


def _get_report_summary(summary: dict) -> list[dict]:
    return [
        {"label": _("Scenario Total"), "value": summary.get("scenario_total", 0), "datatype": "Currency", "indicator": "Orange"},
        {"label": _("Actual Total"), "value": summary.get("actual_total", 0), "datatype": "Currency", "indicator": "Blue"},
        {"label": _("Scenario Additions"), "value": summary.get("scenario_additions", 0), "datatype": "Currency", "indicator": "Grey"},
        {"label": _("Remaining / Over"), "value": summary.get("remaining_or_over", 0), "datatype": "Currency", "indicator": "Green"},
    ]
