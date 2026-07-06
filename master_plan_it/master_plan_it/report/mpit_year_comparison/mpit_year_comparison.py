from __future__ import annotations

import frappe
from frappe import _

from master_plan_it.master_plan_it.financial_engine import get_year_comparison_dataset


def execute(filters=None):
    filters = frappe._dict(filters or {})
    dataset = get_year_comparison_dataset(filters)
    return _get_columns(filters), dataset.get("rows", []), None, dataset.get("chart"), _get_report_summary(dataset.get("summary", {}))


def _get_columns(filters) -> list[dict]:
    return [
        {"label": _("Group"), "fieldname": "group_label", "fieldtype": "Data", "width": 220},
        {"label": _("Year A"), "fieldname": "amount_a", "fieldtype": "Currency", "width": 130},
        {"label": _("Year B"), "fieldname": "amount_b", "fieldtype": "Currency", "width": 130},
        {"label": _("Delta Amount"), "fieldname": "delta_amount", "fieldtype": "Currency", "width": 140},
        {"label": _("Delta %"), "fieldname": "delta_percent", "fieldtype": "Percent", "width": 110},
        {"label": _("Change Type"), "fieldname": "change_type", "fieldtype": "Data", "width": 120},
        {"label": _("Driver"), "fieldname": "main_driver", "fieldtype": "Data", "width": 130},
    ]


def _get_report_summary(summary: dict) -> list[dict]:
    return [
        {"label": _("Year A"), "value": summary.get("amount_a", 0), "datatype": "Currency", "indicator": "Blue"},
        {"label": _("Year B"), "value": summary.get("amount_b", 0), "datatype": "Currency", "indicator": "Orange"},
        {"label": _("Delta"), "value": summary.get("delta_amount", 0), "datatype": "Currency", "indicator": "Red"},
        {"label": _("Changed Rows"), "value": summary.get("changed_rows", 0), "datatype": "Int", "indicator": "Grey"},
    ]
