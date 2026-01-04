# Copyright (c) 2026, DOT and contributors
# For license information, please see license.txt

"""
MPIT Overview Report

A Script Report that consolidates data from the Overview Dashboard:
- KPIs via report_summary (Number Cards equivalent)
- Main table: Cost Center vs Budget Metrics
- Primary chart: Plan vs Cap vs Actual
- Extra charts via message payload
"""

from __future__ import annotations

import datetime

import frappe
from frappe import _
from frappe.utils import cint, flt


def execute(filters=None):
    filters = frappe._dict(filters or {})
    year = _resolve_year(filters)
    cost_center = filters.get("cost_center")

    columns = get_columns()
    data = get_data(year, cost_center)
    report_summary = get_report_summary(year, cost_center)
    chart = get_chart(data)
    message = get_extra_charts(year, cost_center)

    return columns, data, None, chart, report_summary, message


def _resolve_year(filters) -> str | None:
    """Resolve year from filter or current MPIT Year."""
    if filters.get("year"):
        return filters.get("year")

    today = datetime.date.today()
    year_name = frappe.db.get_value(
        "MPIT Year",
        {"start_date": ["<=", today], "end_date": [">=", today]},
        "name",
    )
    return year_name


def get_columns():
    return [
        {"label": _("Cost Center"), "fieldname": "cost_center", "fieldtype": "Link", "options": "MPIT Cost Center", "width": 200},
        {"label": _("Plan (Live)"), "fieldname": "plan_live", "fieldtype": "Currency", "options": "currency", "width": 120},
        {"label": _("Snapshot"), "fieldname": "snapshot", "fieldtype": "Currency", "options": "currency", "width": 120},
        {"label": _("Addendum"), "fieldname": "addendum", "fieldtype": "Currency", "options": "currency", "width": 120},
        {"label": _("Cap"), "fieldname": "cap", "fieldtype": "Currency", "options": "currency", "width": 120},
        {"label": _("Actual"), "fieldname": "actual", "fieldtype": "Currency", "options": "currency", "width": 120},
        {"label": _("Remaining"), "fieldname": "remaining", "fieldtype": "Currency", "options": "currency", "width": 120},
        {"label": _("Over Cap"), "fieldname": "over_cap", "fieldtype": "Currency", "options": "currency", "width": 120},
    ]


def get_data(year: str | None, cost_center: str | None) -> list[dict]:
    """
    Aggregate Plan, Snapshot, Addendum, Actual per Cost Center.
    """
    if not year:
        return []

    cost_center_filter = {"cost_center": cost_center} if cost_center else {}

    # Get all cost centers used in budgets for this year
    all_cc = set()

    # Plan (Live)
    live_budget = frappe.db.get_value(
        "MPIT Budget",
        {"year": year, "budget_type": "Live", "docstatus": 0},
        "name",
    )
    plan_map = {}
    if live_budget:
        lines = frappe.db.get_all(
            "MPIT Budget Line",
            filters={"parent": live_budget, **({"cost_center": cost_center} if cost_center else {})},
            fields=["cost_center", "sum(annual_net) as total"],
            group_by="cost_center",
        )
        for row in lines:
            if row.cost_center:
                plan_map[row.cost_center] = flt(row.total)
                all_cc.add(row.cost_center)

    # Snapshot (latest submitted)
    snapshot_budget = frappe.db.get_value(
        "MPIT Budget",
        {"year": year, "budget_type": "Snapshot", "docstatus": 1},
        "name",
        order_by="modified desc",
    )
    snapshot_map = {}
    if snapshot_budget:
        lines = frappe.db.get_all(
            "MPIT Budget Line",
            filters={"parent": snapshot_budget, "line_kind": "Allowance", **({"cost_center": cost_center} if cost_center else {})},
            fields=["cost_center", "sum(annual_net) as total"],
            group_by="cost_center",
        )
        for row in lines:
            if row.cost_center:
                snapshot_map[row.cost_center] = flt(row.total)
                all_cc.add(row.cost_center)

    # Addendums
    addendum_filters = {"year": year, "docstatus": 1}
    if cost_center:
        addendum_filters["cost_center"] = cost_center
    addendum_data = frappe.db.get_all(
        "MPIT Budget Addendum",
        filters=addendum_filters,
        fields=["cost_center", "sum(delta_amount) as total"],
        group_by="cost_center",
    )
    addendum_map = {}
    for row in addendum_data:
        if row.cost_center:
            addendum_map[row.cost_center] = flt(row.total)
            all_cc.add(row.cost_center)

    # Actuals (Verified)
    actual_filters = {"year": year, "status": "Verified"}
    if cost_center:
        actual_filters["cost_center"] = cost_center
    actual_data = frappe.db.get_all(
        "MPIT Actual Entry",
        filters=actual_filters,
        fields=["cost_center", "sum(amount_net) as total"],
        group_by="cost_center",
    )
    actual_map = {}
    for row in actual_data:
        if row.cost_center:
            actual_map[row.cost_center] = flt(row.total)
            all_cc.add(row.cost_center)

    # Build rows
    data = []
    for cc in sorted(all_cc):
        plan = flt(plan_map.get(cc, 0), 2)
        snapshot = flt(snapshot_map.get(cc, 0), 2)
        addendum = flt(addendum_map.get(cc, 0), 2)
        cap = flt(snapshot + addendum, 2)
        actual = flt(actual_map.get(cc, 0), 2)
        remaining = flt(max(cap - actual, 0), 2)
        over_cap = flt(max(actual - cap, 0), 2)

        data.append({
            "cost_center": cc,
            "plan_live": plan,
            "snapshot": snapshot,
            "addendum": addendum,
            "cap": cap,
            "actual": actual,
            "remaining": remaining,
            "over_cap": over_cap,
        })

    return data


def get_report_summary(year: str | None, cost_center: str | None) -> list[dict]:
    """
    Generate KPI cards for the report summary.
    Replicates Number Cards from the Overview Dashboard.
    
    Field availability by DocType:
    - MPIT Budget: year (no cost_center)
    - MPIT Budget Addendum: year, cost_center
    - MPIT Actual Entry: year, cost_center, status
    - MPIT Contract: cost_center, end_date
    - MPIT Project: cost_center
    - MPIT Planned Item: project (link to MPIT Project), docstatus
    """
    summary = []

    # Helper for filtered counts
    def count_docs(doctype, filters):
        return frappe.db.count(doctype, filters)

    year_filter = {"year": year} if year else {}
    
    # DocTypes with direct cost_center field
    cc_filter_direct = {"cost_center": cost_center} if cost_center else {}

    # Budgets (Live) - NO cost_center field on MPIT Budget
    summary.append({
        "label": _("Budgets (Live)"),
        "value": count_docs("MPIT Budget", {"budget_type": "Live", "docstatus": 0, **year_filter}),
        "datatype": "Int",
        "indicator": "blue",
    })

    # Budgets (Snapshot) - NO cost_center field on MPIT Budget
    summary.append({
        "label": _("Budgets (Snapshot)"),
        "value": count_docs("MPIT Budget", {"budget_type": "Snapshot", "docstatus": 1, **year_filter}),
        "datatype": "Int",
        "indicator": "green",
    })

    # Addendums (Approved) - HAS cost_center
    summary.append({
        "label": _("Addendums (Approved)"),
        "value": count_docs("MPIT Budget Addendum", {"docstatus": 1, **year_filter, **cc_filter_direct}),
        "datatype": "Int",
        "indicator": "orange",
    })

    # Actual Entries (Verified) - HAS cost_center
    summary.append({
        "label": _("Actual Entries (Verified)"),
        "value": count_docs("MPIT Actual Entry", {"status": "Verified", **year_filter, **cc_filter_direct}),
        "datatype": "Int",
        "indicator": "green",
    })

    # Contracts - HAS cost_center
    summary.append({
        "label": _("Contracts"),
        "value": count_docs("MPIT Contract", cc_filter_direct),
        "datatype": "Int",
        "indicator": "blue",
    })

    # Planned Items (Submitted) - NO direct cost_center, filter via project
    if cost_center:
        # Get projects with this cost_center, then count planned items for those projects
        projects_with_cc = frappe.get_all(
            "MPIT Project",
            filters={"cost_center": cost_center},
            pluck="name",
        )
        if projects_with_cc:
            planned_count = count_docs("MPIT Planned Item", {"docstatus": 1, "project": ["in", projects_with_cc]})
        else:
            planned_count = 0
    else:
        planned_count = count_docs("MPIT Planned Item", {"docstatus": 1})
    summary.append({
        "label": _("Planned Items (Submitted)"),
        "value": planned_count,
        "datatype": "Int",
        "indicator": "purple",
    })

    # Projects - HAS cost_center
    summary.append({
        "label": _("Projects"),
        "value": count_docs("MPIT Project", cc_filter_direct),
        "datatype": "Int",
        "indicator": "blue",
    })

    # Renewals 30d/60d/90d - MPIT Contract HAS cost_center
    today = datetime.date.today()
    for days in [30, 60, 90]:
        end_date = today + datetime.timedelta(days=days)
        renewal_count = count_docs(
            "MPIT Contract",
            {"end_date": ["between", [today, end_date]], **cc_filter_direct},
        )
        summary.append({
            "label": _("Renewals {0}d").format(days),
            "value": renewal_count,
            "datatype": "Int",
            "indicator": "orange" if days <= 30 else "yellow",
        })

    return summary


def get_chart(data: list[dict]) -> dict:
    """
    Primary chart: Plan vs Cap vs Actual by Cost Center.
    """
    if not data:
        return {}

    labels = [row["cost_center"] for row in data]
    plan_values = [row["plan_live"] for row in data]
    cap_values = [row["cap"] for row in data]
    actual_values = [row["actual"] for row in data]

    return {
        "data": {
            "labels": labels,
            "datasets": [
                {"name": _("Plan (Live)"), "type": "bar", "values": plan_values},
                {"name": _("Cap"), "type": "bar", "values": cap_values},
                {"name": _("Actual"), "type": "line", "values": actual_values},
            ],
        },
        "type": "axis-mixed",
        "colors": ["#7cd6fd", "#5e64ff", "#ffa00a"],
        "fieldtype": "Currency",
    }


def get_extra_charts(year: str | None, cost_center: str | None) -> dict:
    """
    Additional charts returned via message payload.
    Rendered by JS using frappe.Chart.
    """
    charts = {}

    # Budgets by Type (Pie)
    budget_types = frappe.db.get_all(
        "MPIT Budget",
        filters={"year": year} if year else {},
        fields=["budget_type", "count(name) as total"],
        group_by="budget_type",
    )
    if budget_types:
        charts["budgets_by_type"] = {
            "title": _("Budgets by Type"),
            "type": "pie",
            "data": {
                "labels": [row.budget_type or _("Unknown") for row in budget_types],
                "datasets": [{"values": [cint(row.total) for row in budget_types]}],
            },
        }

    # Contracts by Status (Pie)
    contract_statuses = frappe.db.get_all(
        "MPIT Contract",
        filters={"cost_center": cost_center} if cost_center else {},
        fields=["status", "count(name) as total"],
        group_by="status",
    )
    if contract_statuses:
        charts["contracts_by_status"] = {
            "title": _("Contracts by Status"),
            "type": "pie",
            "data": {
                "labels": [row.status or _("Unknown") for row in contract_statuses],
                "datasets": [{"values": [cint(row.total) for row in contract_statuses]}],
            },
        }

    # Projects by Status (Pie)
    project_statuses = frappe.db.get_all(
        "MPIT Project",
        filters={"cost_center": cost_center} if cost_center else {},
        fields=["status", "count(name) as total"],
        group_by="status",
    )
    if project_statuses:
        charts["projects_by_status"] = {
            "title": _("Projects by Status"),
            "type": "pie",
            "data": {
                "labels": [row.status or _("Unknown") for row in project_statuses],
                "datasets": [{"values": [cint(row.total) for row in project_statuses]}],
            },
        }

    # Actual Entries by Status (Percentage)
    actual_statuses = frappe.db.get_all(
        "MPIT Actual Entry",
        filters={"year": year, **({"cost_center": cost_center} if cost_center else {})} if year else {},
        fields=["status", "count(name) as total"],
        group_by="status",
    )
    if actual_statuses:
        charts["actual_entries_by_status"] = {
            "title": _("Actual Entries by Status"),
            "type": "percentage",
            "data": {
                "labels": [row.status or _("Unknown") for row in actual_statuses],
                "datasets": [{"values": [cint(row.total) for row in actual_statuses]}],
            },
        }

    return {"charts": charts}
