from __future__ import annotations

import frappe
from frappe import _

from master_plan_it.master_plan_it.financial_engine import get_renewals_commitments_dataset


def execute(filters=None):
    filters = frappe._dict(filters or {})
    dataset = get_renewals_commitments_dataset(filters)
    return _get_columns(filters), dataset.get("rows", []), None, dataset.get("chart"), _get_report_summary(dataset.get("summary", {}))


def _get_columns(filters) -> list[dict]:
    return [
        {"label": _("Contract"), "fieldname": "contract", "fieldtype": "Link", "options": "MPIT Contract", "width": 160},
        {"label": _("Title"), "fieldname": "title", "fieldtype": "Data", "width": 220},
        {"label": _("Vendor"), "fieldname": "vendor", "fieldtype": "Link", "options": "MPIT Vendor", "width": 140},
        {"label": _("Cost Center"), "fieldname": "cost_center", "fieldtype": "Link", "options": "MPIT Cost Center", "width": 140},
        {"label": _("Renewal Date"), "fieldname": "next_renewal_date", "fieldtype": "Date", "width": 120},
        {"label": _("Days"), "fieldname": "days_to_renewal", "fieldtype": "Int", "width": 80},
        {"label": _("Current Annual Amount"), "fieldname": "annual_amount_current_year", "fieldtype": "Currency", "width": 170},
        {"label": _("Next Annual Amount"), "fieldname": "annual_amount_next_year", "fieldtype": "Currency", "width": 160},
        {"label": _("Auto Renew"), "fieldname": "auto_renew", "fieldtype": "Check", "width": 100},
        {"label": _("Action"), "fieldname": "recommended_action", "fieldtype": "Data", "width": 120},
        {"label": _("Status"), "fieldname": "status", "fieldtype": "Data", "width": 100},
    ]


def _get_report_summary(summary: dict) -> list[dict]:
    return [
        {"label": _("Renewals"), "value": summary.get("renewal_count", 0), "datatype": "Int", "indicator": "Grey"},
        {"label": _("Current Annual"), "value": summary.get("current_annual_amount", 0), "datatype": "Currency", "indicator": "Blue"},
        {"label": _("Next Annual"), "value": summary.get("next_annual_amount", 0), "datatype": "Currency", "indicator": "Orange"},
        {"label": _("Expired"), "value": summary.get("expired_count", 0), "datatype": "Int", "indicator": "Red"},
    ]
