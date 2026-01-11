# Copyright (c) 2026, DOT and contributors
# For license information, please see license.txt

"""
MPIT Overview Report

A Script Report that serves as Budget Control Center:
- Default mode (no budget filter): Year-level comparison of all cost centers
- Budget mode (budget selected): Detail view of selected budget's lines

KPIs via report_summary, charts via primary chart and message payload.
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
    budget = filters.get("budget")
    vendor = filters.get("vendor")

    # Determine budget type if budget is selected
    budget_type = None
    if budget:
        budget_type = frappe.db.get_value("MPIT Budget", budget, "budget_type")

    # Get mode-appropriate columns
    columns = get_columns(budget_type)

    # Get data based on mode
    if budget:
        # Budget detail mode - show lines from selected budget
        data = get_budget_detail_data(budget, cost_center, vendor)
    else:
        # Default mode - year-level comparison
        data = get_year_comparison_data(year, cost_center)

    report_summary = get_report_summary(year, cost_center, data, budget, budget_type)
    chart = get_chart(data, budget_type)
    message = get_extra_charts(year, cost_center) if not budget else {}

    return columns, data, None, chart, report_summary, message


def _resolve_year(filters) -> str | None:
    """Resolve year from filter or current MPIT Year."""
    if filters.get("year"):
        return filters.get("year")

    # If budget is selected, get year from it
    if filters.get("budget"):
        return frappe.db.get_value("MPIT Budget", filters.get("budget"), "year")

    today = datetime.date.today()
    year_name = frappe.db.get_value(
        "MPIT Year",
        {"start_date": ["<=", today], "end_date": [">=", today]},
        "name",
    )
    return year_name


def get_columns(budget_type: str | None = None):
    """Return columns based on report mode."""
    if budget_type in ("Live", "Snapshot"):
        # Budget detail mode - show line-level details
        return [
            {"label": _("Cost Center"), "fieldname": "cost_center", "fieldtype": "Link", "options": "MPIT Cost Center", "width": 180},
            {"label": _("Vendor"), "fieldname": "vendor", "fieldtype": "Link", "options": "MPIT Vendor", "width": 150},
            {"label": _("Line Kind"), "fieldname": "line_kind", "fieldtype": "Data", "width": 100},
            {"label": _("Description"), "fieldname": "description", "fieldtype": "Data", "width": 250},
            {"label": _("Annual Net"), "fieldname": "annual_net", "fieldtype": "Currency", "options": "currency", "width": 120},
            {"label": _("Monthly"), "fieldname": "monthly_amount", "fieldtype": "Currency", "options": "currency", "width": 100},
        ]
    else:
        # Default mode - year-level comparison
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


def get_budget_detail_data(budget: str, cost_center: str | None = None, vendor: str | None = None) -> list[dict]:
    """
    Return line-level data from a selected budget.
    Shows all lines grouped by cost center and vendor.
    """
    filters = {"parent": budget}
    if cost_center:
        filters["cost_center"] = cost_center
    if vendor:
        filters["vendor"] = vendor

    lines = frappe.db.get_all(
        "MPIT Budget Line",
        filters=filters,
        fields=[
            "cost_center",
            "vendor",
            "line_kind",
            "description",
            "annual_net",
            "monthly_amount",
            "contract",
            "project",
        ],
        order_by="cost_center, vendor, line_kind, description",
    )

    return [
        {
            "cost_center": line.cost_center,
            "vendor": line.vendor,
            "line_kind": line.line_kind,
            "description": line.description or "",
            "annual_net": flt(line.annual_net, 2),
            "monthly_amount": flt(line.monthly_amount, 2),
        }
        for line in lines
    ]


def get_year_comparison_data(year: str | None, cost_center: str | None = None) -> list[dict]:
    """
    Aggregate Plan, Snapshot, Addendum, Actual per Cost Center for a year.
    This is the default view showing cross-budget comparison.
    """
    if not year:
        return []

    # Get all cost centers used in budgets for this year
    all_cc = set()

    # Get Live budget for this year
    live_budget_name = frappe.db.get_value(
        "MPIT Budget",
        {"year": year, "budget_type": "Live", "docstatus": 0},
        "name",
    )

    # Get approved Snapshot for this year
    snapshot_budget_name = frappe.db.get_value(
        "MPIT Budget",
        {"year": year, "budget_type": "Snapshot", "docstatus": 1},
        "name",
        order_by="modified desc",
    )

    # 1. Plan (Live)
    plan_map = {}
    if live_budget_name:
        lines = frappe.db.get_all(
            "MPIT Budget Line",
            filters={"parent": live_budget_name, **({"cost_center": cost_center} if cost_center else {})},
            fields=["cost_center", "sum(annual_net) as total"],
            group_by="cost_center",
        )
        for row in lines:
            if row.cost_center:
                plan_map[row.cost_center] = flt(row.total)
                all_cc.add(row.cost_center)

    # 2. Snapshot - ALL lines (not just Allowance)
    snapshot_map = {}
    if snapshot_budget_name:
        lines = frappe.db.get_all(
            "MPIT Budget Line",
            filters={"parent": snapshot_budget_name, **({"cost_center": cost_center} if cost_center else {})},
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


def get_report_summary(year: str | None, cost_center: str | None, data: list[dict], budget: str | None = None, budget_type: str | None = None) -> list[dict]:
    """
    Generate KPI cards for the report summary.
    Shows different cards based on mode (budget detail vs year comparison).
    """
    summary = []

    if budget_type in ("Live", "Snapshot"):
        # Budget detail mode - show totals from displayed data
        total_annual = sum(flt(row.get("annual_net", 0)) for row in data)
        # Monthly is calculated as Annual / 12 (consistent with MPIT Budget._compute_totals)
        total_monthly = flt(total_annual / 12, 2) if total_annual else 0
        line_count = len(data)

        # Count by line kind
        line_kinds = {}
        for row in data:
            lk = row.get("line_kind", "Other")
            line_kinds[lk] = line_kinds.get(lk, 0) + 1

        summary.append({
            "label": _("Total Annual Net"),
            "value": total_annual,
            "datatype": "Currency",
            "indicator": "blue",
        })

        summary.append({
            "label": _("Total Monthly"),
            "value": total_monthly,
            "datatype": "Currency",
            "indicator": "lightblue",
        })

        summary.append({
            "label": _("Budget Lines"),
            "value": line_count,
            "datatype": "Int",
            "indicator": "gray",
        })

        # Show breakdown by line kind
        for lk, count in sorted(line_kinds.items()):
            summary.append({
                "label": _(lk),
                "value": count,
                "datatype": "Int",
                "indicator": "purple" if lk == "Contract" else "orange" if lk == "Planned Item" else "green",
            })

    else:
        # Year comparison mode - show counts and totals
        year_filter = {"year": year} if year else {}
        cc_filter_direct = {"cost_center": cost_center} if cost_center else {}

        def count_docs(doctype, filters):
            return frappe.db.count(doctype, filters)

        # Addendums (Approved)
        summary.append({
            "label": _("Addendums (Approved)"),
            "value": count_docs("MPIT Budget Addendum", {"docstatus": 1, **year_filter, **cc_filter_direct}),
            "datatype": "Int",
            "indicator": "orange",
        })

        # Actual Entries (Verified)
        summary.append({
            "label": _("Actual Entries (Verified)"),
            "value": count_docs("MPIT Actual Entry", {"status": "Verified", **year_filter, **cc_filter_direct}),
            "datatype": "Int",
            "indicator": "green",
        })

        # Contracts
        summary.append({
            "label": _("Contracts"),
            "value": count_docs("MPIT Contract", cc_filter_direct),
            "datatype": "Int",
            "indicator": "blue",
        })

        # Projects
        summary.append({
            "label": _("Projects"),
            "value": count_docs("MPIT Project", cc_filter_direct),
            "datatype": "Int",
            "indicator": "blue",
        })

        # Plan Live Total
        annual_plan_live = sum(flt(row.get("plan_live", 0)) for row in data)
        summary.append({
            "label": _("Plan Live (Annual)"),
            "value": annual_plan_live,
            "datatype": "Currency",
            "indicator": "blue",
        })

        # Snapshot Total
        annual_snapshot = sum(flt(row.get("snapshot", 0)) for row in data)
        summary.append({
            "label": _("Snapshot (Annual)"),
            "value": annual_snapshot,
            "datatype": "Currency",
            "indicator": "green",
        })

    return summary


def get_chart(data: list[dict], budget_type: str | None = None) -> dict:
    """
    Primary chart based on report mode.
    """
    if not data:
        return {}

    if budget_type in ("Live", "Snapshot"):
        # Budget detail mode - breakdown by Cost Center
        cc_totals = {}
        for row in data:
            cc = row.get("cost_center", _("Unknown"))
            cc_totals[cc] = cc_totals.get(cc, 0) + flt(row.get("annual_net", 0))

        labels = list(cc_totals.keys())
        values = [cc_totals[cc] for cc in labels]

        return {
            "data": {
                "labels": labels,
                "datasets": [
                    {"name": _("Annual Net"), "type": "bar", "values": values},
                ],
            },
            "type": "bar",
            "colors": ["#5e64ff"],
            "fieldtype": "Currency",
        }
    else:
        # Year comparison mode - Plan vs Snapshot vs Actual
        labels = [row["cost_center"] for row in data]
        plan_values = [row["plan_live"] for row in data]
        snapshot_values = [row["snapshot"] for row in data]
        actual_values = [row["actual"] for row in data]

        return {
            "data": {
                "labels": labels,
                "datasets": [
                    {"name": _("Plan (Live)"), "type": "bar", "values": plan_values},
                    {"name": _("Snapshot"), "type": "bar", "values": snapshot_values},
                    {"name": _("Actual"), "type": "line", "values": actual_values},
                ],
            },
            "type": "bar",
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
    actual_filters = {"year": year} if year else {}
    if cost_center:
        actual_filters["cost_center"] = cost_center
    actual_statuses = frappe.db.get_all(
        "MPIT Actual Entry",
        filters=actual_filters,
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
