from __future__ import annotations

import datetime

import frappe

from master_plan_it.master_plan_it.financial_engine import get_monthly_forecast_vs_actual


def get_config():
    return {
        "method": "master_plan_it.master_plan_it.dashboard_chart_source.mpit_monthly_forecast_vs_actual.mpit_monthly_forecast_vs_actual.get",
        "filters": [],
    }


def get_data(filters=None):
    filters = frappe._dict(filters or {})
    year = _resolve_year(filters)

    dataset = get_monthly_forecast_vs_actual(
        year,
        cost_center=filters.get("cost_center"),
        project=filters.get("project"),
        contract=filters.get("contract"),
    )
    rows = dataset.get("months", [])

    return {
        "labels": [row.get("month") for row in rows],
        "datasets": [
            {"name": "Forecast", "values": [row.get("forecast", 0) for row in rows]},
            {"name": "Actual", "values": [row.get("actual", 0) for row in rows]},
        ],
        "type": "line",
    }


@frappe.whitelist()
def get(**kwargs):
    filters = kwargs.get("filters")
    if isinstance(filters, str):
        filters = frappe.parse_json(filters)
    return get_data(filters)


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
