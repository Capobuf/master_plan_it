from __future__ import annotations

import datetime

import frappe
from frappe import _
from frappe.utils import flt


def execute(filters=None):
    filters = frappe._dict(filters or {})
    year = _resolve_year(filters)

    expense_filters = {"year": year}
    for key in ("cost_center", "expense_kind", "workflow_state", "project", "contract"):
        value = filters.get(key)
        if value:
            expense_filters[key] = value

    expenses = frappe.get_all(
        "MPIT Expense",
        filters=expense_filters,
        fields=[
            "name",
            "expense_title",
            "expense_kind",
            "year",
            "cost_center",
            "project",
            "contract",
            "uses_plafond",
            "is_extra",
            "total_forecast_net",
            "total_actual_net",
            "workflow_state",
        ],
        order_by="modified desc",
        limit=None,
    )

    columns = [
        {"label": _("Expense"), "fieldname": "expense", "fieldtype": "Link", "options": "MPIT Expense", "width": 160},
        {"label": _("Title"), "fieldname": "title", "fieldtype": "Data", "width": 180},
        {"label": _("Kind"), "fieldname": "kind", "fieldtype": "Data", "width": 100},
        {"label": _("Year"), "fieldname": "year", "fieldtype": "Link", "options": "MPIT Year", "width": 90},
        {"label": _("Cost Center"), "fieldname": "cost_center", "fieldtype": "Link", "options": "MPIT Cost Center", "width": 140},
        {"label": _("Project"), "fieldname": "project", "fieldtype": "Link", "options": "MPIT Project", "width": 140},
        {"label": _("Contract"), "fieldname": "contract", "fieldtype": "Link", "options": "MPIT Contract", "width": 140},
        {"label": _("Funding"), "fieldname": "funding", "fieldtype": "Data", "width": 120},
        {"label": _("Forecast"), "fieldname": "forecast", "fieldtype": "Currency", "width": 120},
        {"label": _("Actual"), "fieldname": "actual", "fieldtype": "Currency", "width": 120},
        {"label": _("Workflow State"), "fieldname": "workflow_state", "fieldtype": "Data", "width": 120},
    ]

    data = []
    for expense in expenses:
        funding = "-"
        if expense.expense_kind == "Ordinary":
            if expense.uses_plafond:
                funding = _("On Plafond")
            elif expense.is_extra:
                funding = _("Extra")

        data.append(
            {
                "expense": expense.name,
                "title": expense.expense_title,
                "kind": expense.expense_kind,
                "year": expense.year,
                "cost_center": expense.cost_center,
                "project": expense.project,
                "contract": expense.contract,
                "funding": funding,
                "forecast": flt(expense.total_forecast_net, 2),
                "actual": flt(expense.total_actual_net, 2),
                "workflow_state": expense.workflow_state,
            }
        )

    return columns, data


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
