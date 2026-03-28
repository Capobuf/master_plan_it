from __future__ import annotations

import datetime

import frappe
from frappe import _
from frappe.utils import flt

from master_plan_it.master_plan_it.financial_engine import get_project_financial_summary


def execute(filters=None):
    filters = frappe._dict(filters or {})
    year = _resolve_year(filters)

    project_filters = {}
    if filters.get("cost_center"):
        project_filters["cost_center"] = filters.get("cost_center")

    projects = frappe.get_all(
        "MPIT Project",
        filters=project_filters,
        fields=["name", "title", "cost_center"],
        order_by="title asc",
        limit=None,
    )

    columns = [
        {"label": _("Project"), "fieldname": "project", "fieldtype": "Link", "options": "MPIT Project", "width": 160},
        {"label": _("Title"), "fieldname": "title", "fieldtype": "Data", "width": 200},
        {"label": _("Cost Center"), "fieldname": "cost_center", "fieldtype": "Link", "options": "MPIT Cost Center", "width": 140},
        {"label": _("Forecast"), "fieldname": "forecast", "fieldtype": "Currency", "width": 140},
        {"label": _("Actual"), "fieldname": "actual", "fieldtype": "Currency", "width": 140},
        {"label": _("Variance"), "fieldname": "variance", "fieldtype": "Currency", "width": 140},
        {"label": _("Variance %"), "fieldname": "variance_pct", "fieldtype": "Percent", "width": 110},
    ]

    data = []
    for project in projects:
        summary = get_project_financial_summary(project.name, year=year)
        forecast = flt(summary.get("forecast_total_net"), 2)
        actual = flt(summary.get("actual_total_net"), 2)
        variance = flt(summary.get("variance_net"), 2)
        variance_pct = flt((variance / forecast) * 100, 2) if forecast else 0

        data.append(
            {
                "project": project.name,
                "title": project.title,
                "cost_center": project.cost_center,
                "forecast": forecast,
                "actual": actual,
                "variance": variance,
                "variance_pct": variance_pct,
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
