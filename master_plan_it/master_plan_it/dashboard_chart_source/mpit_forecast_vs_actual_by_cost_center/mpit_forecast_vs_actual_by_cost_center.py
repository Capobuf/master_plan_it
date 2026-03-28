from __future__ import annotations

import datetime

import frappe

from master_plan_it.master_plan_it.financial_engine import get_overview_dataset


def get_config():
    return {
        "method": "master_plan_it.master_plan_it.dashboard_chart_source.mpit_forecast_vs_actual_by_cost_center.mpit_forecast_vs_actual_by_cost_center.get",
        "filters": [],
    }


def get_data(filters=None):
    filters = frappe._dict(filters or {})
    year = _resolve_year(filters)

    dataset = get_overview_dataset(year, cost_center=filters.get("cost_center"))
    rows = dataset.get("rows", [])

    labels = [row.get("cost_center") for row in rows]
    forecast = [row.get("forecast_total", 0) for row in rows]
    actual = [row.get("actual_total", 0) for row in rows]

    return {
        "labels": labels,
        "datasets": [
            {"name": "Forecast", "values": forecast},
            {"name": "Actual", "values": actual},
        ],
        "type": "bar",
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
