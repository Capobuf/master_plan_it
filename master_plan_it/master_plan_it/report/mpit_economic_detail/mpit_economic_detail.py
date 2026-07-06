from __future__ import annotations

import frappe
from frappe import _

from master_plan_it.master_plan_it.financial_engine import get_economic_detail_dataset


def execute(filters=None):
    filters = frappe._dict(filters or {})
    dataset = get_economic_detail_dataset(filters)
    return _get_columns(filters), dataset.get("rows", []), None, dataset.get("chart"), _get_report_summary(dataset.get("summary", {}))


def _get_columns(filters) -> list[dict]:
    return [
        {"label": _("Date"), "fieldname": "effective_date", "fieldtype": "Date", "width": 100},
        {"label": _("Cost Center"), "fieldname": "cost_center", "fieldtype": "Link", "options": "MPIT Cost Center", "width": 140},
        {"label": _("Type"), "fieldname": "source_type", "fieldtype": "Data", "width": 90},
        {"label": _("Document"), "fieldname": "source_document", "fieldtype": "Data", "width": 170},
        {"label": _("Row"), "fieldname": "source_row", "fieldtype": "Data", "width": 160},
        {"label": _("Description"), "fieldname": "description", "fieldtype": "Data", "width": 220},
        {"label": _("Vendor"), "fieldname": "vendor", "fieldtype": "Link", "options": "MPIT Vendor", "width": 140},
        {"label": _("Project"), "fieldname": "project", "fieldtype": "Link", "options": "MPIT Project", "width": 140},
        {"label": _("Project Stage"), "fieldname": "project_stage", "fieldtype": "Data", "width": 120},
        {"label": _("Contract"), "fieldname": "contract", "fieldtype": "Link", "options": "MPIT Contract", "width": 140},
        {"label": _("Phase"), "fieldname": "row_phase", "fieldtype": "Data", "width": 90},
        {"label": _("Funding"), "fieldname": "funding", "fieldtype": "Data", "width": 100},
        {"label": _("Net"), "fieldname": "amount_net", "fieldtype": "Currency", "width": 120},
        {"label": _("VAT"), "fieldname": "amount_vat", "fieldtype": "Currency", "width": 120},
        {"label": _("Gross"), "fieldname": "amount_gross", "fieldtype": "Currency", "width": 120},
        {"label": _("Row State"), "fieldname": "row_state", "fieldtype": "Data", "width": 100},
    ]


def _get_report_summary(summary: dict) -> list[dict]:
    return [
        {"label": _("Rows"), "value": summary.get("total_rows", 0), "datatype": "Int", "indicator": "Grey"},
        {"label": _("Net"), "value": summary.get("amount_net", 0), "datatype": "Currency", "indicator": "Blue"},
        {"label": _("VAT"), "value": summary.get("amount_vat", 0), "datatype": "Currency", "indicator": "Orange"},
        {"label": _("Gross"), "value": summary.get("amount_gross", 0), "datatype": "Currency", "indicator": "Green"},
    ]
