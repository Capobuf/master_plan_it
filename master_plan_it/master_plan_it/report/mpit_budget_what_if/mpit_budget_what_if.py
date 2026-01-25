# Copyright (c) 2026, DOT and contributors
# For license information, please see license.txt

"""
MPIT Budget What-If Report

Allows selecting a budget and one or more projects to simulate
what the budget would look like if those projects were approved or removed.

Logic:
- Project NOT Approved selected → ADD to budget (positive delta)
- Project Approved selected → REMOVE from budget (negative delta)
"""

from __future__ import annotations

import calendar
from datetime import date

import frappe
from frappe import _
from frappe.utils import flt, getdate

from master_plan_it import annualization


def execute(filters=None):
    filters = frappe._dict(filters or {})

    budget = filters.get("budget")
    if not budget:
        frappe.msgprint(_("Please select a Budget"))
        return [], [], None, None, [], {}

    # Parse projects from comma-separated string
    projects_str = filters.get("projects") or ""
    selected_projects = [p.strip() for p in projects_str.split(",") if p.strip()]

    # Get budget info
    budget_doc = frappe.get_doc("MPIT Budget", budget)
    year = budget_doc.year

    # Get all projects data
    projects_data = _get_projects_data(selected_projects)

    # Calculate current budget totals
    current_total = flt(budget_doc.total_amount_net or 0, 2)

    # Calculate delta for selected projects
    delta_total, project_details = _calculate_delta(projects_data, budget)

    # Simulated total
    simulated_total = flt(current_total + delta_total, 2)

    # Build columns and data
    columns = _get_columns()
    data = _build_project_rows(project_details)

    # Build cost center breakdown
    cc_breakdown = _build_cost_center_breakdown(budget, project_details)
    data.extend([{"is_separator": 1}])  # Separator
    data.extend(cc_breakdown)

    # Build report summary (KPI cards)
    report_summary = _get_report_summary(current_total, delta_total, simulated_total, len(project_details))

    # Build monthly distribution (via message payload)
    message = _get_monthly_distribution(budget, year, project_details)

    # Primary chart
    chart = _build_chart(current_total, delta_total, simulated_total)

    return columns, data, None, chart, report_summary, message


def _get_projects_data(project_names: list[str]) -> dict:
    """Fetch project data for the given names."""
    if not project_names:
        return {}

    projects = frappe.get_all(
        "MPIT Project",
        filters={"name": ["in", project_names]},
        fields=[
            "name",
            "title",
            "workflow_state",
            "cost_center",
            "planned_total_net",
            "start_date",
            "end_date",
        ],
        limit=None,
    )

    return {p.name: p for p in projects}


def _calculate_delta(projects_data: dict, budget: str) -> tuple[float, list[dict]]:
    """
    Calculate delta for each project.

    - Approved project selected → REMOVE (negative delta)
    - Non-Approved project selected → ADD (positive delta)
    """
    total_delta = 0.0
    details = []

    for name, proj in projects_data.items():
        amount = flt(proj.planned_total_net or 0, 2)
        is_approved = proj.workflow_state == "Approved"

        if is_approved:
            # Project is already in budget, selecting it means REMOVE
            delta = -amount
            action = "remove"
        else:
            # Project is NOT in budget, selecting it means ADD
            delta = amount
            action = "add"

        total_delta += delta

        details.append({
            "project": name,
            "title": proj.title,
            "workflow_state": proj.workflow_state,
            "cost_center": proj.cost_center,
            "planned_net": amount,
            "delta": delta,
            "action": action,
            "start_date": proj.start_date,
            "end_date": proj.end_date,
        })

    return flt(total_delta, 2), details


def _get_columns() -> list[dict]:
    """Return report columns."""
    return [
        {
            "label": _("Project"),
            "fieldname": "project",
            "fieldtype": "Link",
            "options": "MPIT Project",
            "width": 120,
        },
        {
            "label": _("Title"),
            "fieldname": "title",
            "fieldtype": "Data",
            "width": 200,
        },
        {
            "label": _("Cost Center"),
            "fieldname": "cost_center",
            "fieldtype": "Link",
            "options": "MPIT Cost Center",
            "width": 150,
        },
        {
            "label": _("Status"),
            "fieldname": "workflow_state",
            "fieldtype": "Data",
            "width": 100,
        },
        {
            "label": _("Planned Net"),
            "fieldname": "planned_net",
            "fieldtype": "Currency",
            "width": 120,
        },
        {
            "label": _("Action"),
            "fieldname": "action_label",
            "fieldtype": "Data",
            "width": 100,
        },
        {
            "label": _("Delta"),
            "fieldname": "delta",
            "fieldtype": "Currency",
            "width": 120,
        },
    ]


def _build_project_rows(project_details: list[dict]) -> list[dict]:
    """Build data rows for projects table."""
    rows = []

    for proj in project_details:
        action_label = _("+ Add") if proj["action"] == "add" else _("- Remove")

        rows.append({
            "project": proj["project"],
            "title": proj["title"],
            "cost_center": proj["cost_center"],
            "workflow_state": proj["workflow_state"],
            "planned_net": proj["planned_net"],
            "action_label": action_label,
            "delta": proj["delta"],
            "row_type": "project",
        })

    return rows


def _build_cost_center_breakdown(budget: str, project_details: list[dict]) -> list[dict]:
    """Build cost center breakdown showing current vs simulated amounts."""
    # Get current budget amounts by cost center
    current_by_cc = {}
    lines = frappe.get_all(
        "MPIT Budget Line",
        filters={"parent": budget},
        fields=["cost_center", "annual_net"],
        limit=None,
    )
    for line in lines:
        cc = line.cost_center
        if cc:
            current_by_cc[cc] = current_by_cc.get(cc, 0) + flt(line.annual_net or 0)

    # Calculate deltas by cost center from selected projects
    delta_by_cc = {}
    for proj in project_details:
        cc = proj["cost_center"]
        if cc:
            delta_by_cc[cc] = delta_by_cc.get(cc, 0) + proj["delta"]

    # Merge all cost centers
    all_ccs = set(current_by_cc.keys()) | set(delta_by_cc.keys())

    rows = []
    # Add header row
    rows.append({
        "project": None,
        "title": _("Cost Center Breakdown"),
        "cost_center": None,
        "workflow_state": None,
        "planned_net": None,
        "action_label": None,
        "delta": None,
        "row_type": "header",
        "is_header": 1,
    })

    for cc in sorted(all_ccs):
        current = flt(current_by_cc.get(cc, 0), 2)
        delta = flt(delta_by_cc.get(cc, 0), 2)
        simulated = flt(current + delta, 2)

        rows.append({
            "project": None,
            "title": None,
            "cost_center": cc,
            "workflow_state": None,
            "planned_net": current,  # Using planned_net column for "Current"
            "action_label": _("Current → Simulated"),
            "delta": simulated,  # Using delta column for "Simulated"
            "row_type": "cost_center",
            "cc_delta": delta,
        })

    return rows


def _get_report_summary(
    current_total: float,
    delta_total: float,
    simulated_total: float,
    project_count: int,
) -> list[dict]:
    """Build KPI cards for report summary."""
    # Calculate variance percentage
    variance_pct = 0.0
    if current_total:
        variance_pct = flt((delta_total / current_total) * 100, 1)

    summary = [
        {
            "label": _("Budget Attuale"),
            "value": current_total,
            "datatype": "Currency",
            "indicator": "blue",
        },
        {
            "label": _("Delta Progetti"),
            "value": delta_total,
            "datatype": "Currency",
            "indicator": "orange" if delta_total > 0 else "green" if delta_total < 0 else "gray",
        },
        {
            "label": _("Budget Simulato"),
            "value": simulated_total,
            "datatype": "Currency",
            "indicator": "purple",
        },
        {
            "label": _("Variazione %"),
            "value": f"{variance_pct:+.1f}%",
            "datatype": "Data",
            "indicator": "orange" if variance_pct > 0 else "green" if variance_pct < 0 else "gray",
        },
        {
            "label": _("Progetti Selezionati"),
            "value": project_count,
            "datatype": "Int",
            "indicator": "blue",
        },
    ]

    return summary


def _build_chart(current: float, delta: float, simulated: float) -> dict:
    """Build primary bar chart."""
    return {
        "data": {
            "labels": [_("Attuale"), _("Delta"), _("Simulato")],
            "datasets": [
                {
                    "name": _("Budget"),
                    "values": [current, abs(delta), simulated],
                },
            ],
        },
        "type": "bar",
        "colors": ["#5e64ff", "#ffa00a" if delta >= 0 else "#28a745", "#7c3aed"],
        "fieldtype": "Currency",
    }


def _get_monthly_distribution(budget: str, year: str, project_details: list[dict]) -> dict:
    """
    Build monthly distribution data for the what-if scenario.
    Returns via message payload for JS rendering.
    """
    if not year:
        return {}

    year_start, year_end = annualization.get_year_bounds(year)

    # Current budget monthly distribution
    current_monthly = _get_budget_monthly(budget, year_start, year_end)

    # Project delta monthly distribution
    delta_monthly = _get_projects_monthly(project_details, year_start, year_end)

    # Simulated monthly (current + delta)
    simulated_monthly = {}
    all_months = set(current_monthly.keys()) | set(delta_monthly.keys())
    for m in all_months:
        simulated_monthly[m] = flt(current_monthly.get(m, 0) + delta_monthly.get(m, 0), 2)

    # Build chart data
    month_names = ["Gen", "Feb", "Mar", "Apr", "Mag", "Giu", "Lug", "Ago", "Set", "Ott", "Nov", "Dic"]
    current_values = [flt(current_monthly.get(m, 0), 2) for m in range(1, 13)]
    simulated_values = [flt(simulated_monthly.get(m, 0), 2) for m in range(1, 13)]

    return {
        "monthly_chart": {
            "title": _("Distribuzione Mensile What-If"),
            "type": "bar",
            "data": {
                "labels": month_names,
                "datasets": [
                    {"name": _("Attuale"), "values": current_values},
                    {"name": _("Simulato"), "values": simulated_values},
                ],
            },
            "colors": ["#5e64ff", "#7c3aed"],
        },
    }


def _get_budget_monthly(budget: str, year_start: date, year_end: date) -> dict[int, float]:
    """Get monthly distribution from current budget."""
    lines = frappe.get_all(
        "MPIT Budget Line",
        filters={"parent": budget},
        fields=["monthly_amount", "period_start_date", "period_end_date"],
        limit=None,
    )

    result = {m: 0.0 for m in range(1, 13)}

    for line in lines:
        start = getdate(line.period_start_date) if line.period_start_date else year_start
        end = getdate(line.period_end_date) if line.period_end_date else year_end
        monthly = flt(line.monthly_amount or 0)

        for month in range(1, 13):
            month_start = date(year_start.year, month, 1)
            month_end = date(year_start.year, month, calendar.monthrange(year_start.year, month)[1])

            if start <= month_end and end >= month_start:
                result[month] += monthly

    return result


def _get_projects_monthly(project_details: list[dict], year_start: date, year_end: date) -> dict[int, float]:
    """Get monthly distribution for project deltas."""
    result = {m: 0.0 for m in range(1, 13)}

    for proj in project_details:
        amount = proj["delta"]  # Already signed (+ for add, - for remove)
        if amount == 0:
            continue

        start = getdate(proj["start_date"]) if proj.get("start_date") else year_start
        end = getdate(proj["end_date"]) if proj.get("end_date") else year_end

        # Clamp to year
        period_start = max(start, year_start)
        period_end = min(end, year_end)

        if period_end < period_start:
            continue

        # Get overlapping months
        overlap_months = []
        for month in range(1, 13):
            month_start = date(year_start.year, month, 1)
            month_end = date(year_start.year, month, calendar.monthrange(year_start.year, month)[1])

            if period_start <= month_end and period_end >= month_start:
                overlap_months.append(month)

        if not overlap_months:
            continue

        # Distribute evenly across months
        monthly_amount = amount / len(overlap_months)
        for m in overlap_months:
            result[m] += monthly_amount

    return result
