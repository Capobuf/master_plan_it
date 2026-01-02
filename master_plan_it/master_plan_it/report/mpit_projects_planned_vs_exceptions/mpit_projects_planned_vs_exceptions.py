"""
FILE: master_plan_it/report/mpit_projects_planned_vs_exceptions/mpit_projects_planned_vs_exceptions.py
SCOPO: Report v3 che mostra per Project: Planned Items (amount sum), Actual (Delta Verified).
INPUT: Filtri (year obbligatorio, project/cost_center/status opzionali).
OUTPUT: Righe aggregate per Project con confronto Planned vs Actual (Exceptions).
DESIGN: Uses MPIT Planned Item (v3) instead of legacy MPIT Project Allocation.
        Planned Item date range determines year based on start_date falling within MPIT Year.
"""

from __future__ import annotations

import frappe
from frappe import _
from frappe.utils import flt


def execute(filters=None):
    if isinstance(filters, str):
        filters = frappe.parse_json(filters)
    filters = frappe._dict(filters or {})

    if not filters.get("year"):
        frappe.throw(_("Year is required"))

    columns = _get_columns()
    data = _get_data(filters)
    chart = _build_chart(data)

    return columns, data, None, chart


def _get_columns() -> list[dict]:
    return [
        {"label": _("Project"), "fieldname": "project", "fieldtype": "Link", "options": "MPIT Project", "width": 220},
        {"label": _("Project Status"), "fieldname": "project_status", "fieldtype": "Data", "width": 120},
        {"label": _("Cost Center"), "fieldname": "cost_center", "fieldtype": "Link", "options": "MPIT Cost Center", "width": 180},
        {"label": _("Planned (Net)"), "fieldname": "planned_amount", "fieldtype": "Currency", "width": 140},
        {"label": _("Exceptions (Verified)"), "fieldname": "actual_amount", "fieldtype": "Currency", "width": 150},
        {"label": _("Delta"), "fieldname": "variance", "fieldtype": "Currency", "width": 140},
        {"label": _("% Variance"), "fieldname": "pct_variance", "fieldtype": "Percent", "width": 100},
    ]


def _get_data(filters) -> list[dict]:
    year = filters.year
    project_filter = filters.get("project")
    cost_center_filter = filters.get("cost_center")
    status_filter = filters.get("status")

    # Get MPIT Year date range for filtering Planned Items
    year_doc = frappe.get_cached_doc("MPIT Year", year)
    year_start = year_doc.start_date
    year_end = year_doc.end_date

    # Get all projects with activity for this year (Planned Items or Actuals)
    projects = _get_active_projects(year, year_start, year_end, project_filter, cost_center_filter, status_filter)
    if not projects:
        return []

    # Get Planned Item amounts per project (items whose start_date falls in year)
    planned_amounts = _get_planned_amounts(year_start, year_end, project_filter, cost_center_filter, status_filter)

    # Get Actual amounts per project (Delta + Verified)
    actual_amounts = _get_actual_amounts(year, project_filter, cost_center_filter)

    # Get project info (status, cost_center)
    project_info = _get_project_info([p for p in projects])

    rows = []
    for proj in projects:
        info = project_info.get(proj, {})
        planned = flt(planned_amounts.get(proj, 0), 2)
        actual = flt(actual_amounts.get(proj, 0), 2)
        variance = flt(actual - planned, 2)
        pct_variance = flt((variance / planned * 100), 2) if planned != 0 else 0

        rows.append({
            "project": proj,
            "project_status": info.get("status", ""),
            "cost_center": info.get("cost_center", ""),
            "planned_amount": planned,
            "actual_amount": actual,
            "variance": variance,
            "pct_variance": pct_variance,
        })

    return rows


def _get_active_projects(
    year: str,
    year_start,
    year_end,
    project_filter: str | None,
    cost_center_filter: str | None,
    status_filter: str | None,
) -> list[str]:
    """Get all projects that have Planned Items or Actuals for this year."""
    projects = set()

    # Build project filters for cost center and status
    project_filters = {}
    if cost_center_filter:
        project_filters["cost_center"] = cost_center_filter
    if status_filter:
        project_filters["status"] = status_filter
    if project_filter:
        project_filters["name"] = project_filter

    # Get matching projects
    matching_projects = frappe.get_all(
        "MPIT Project",
        filters=project_filters,
        pluck="name",
    ) if project_filters else None

    # From Planned Items (submitted, start_date in year range)
    pi_filters = {
        "docstatus": 1,
        "start_date": ["between", [year_start, year_end]],
    }
    if matching_projects is not None:
        pi_filters["project"] = ["in", matching_projects]

    planned_projects = frappe.get_all(
        "MPIT Planned Item",
        filters=pi_filters,
        pluck="project",
        distinct=True,
    )
    projects.update(planned_projects)

    # From Actual Entries (Delta, Verified)
    actual_filters = {
        "year": year,
        "status": "Verified",
        "entry_kind": "Delta",
        "project": ["is", "set"],
    }
    if matching_projects is not None:
        actual_filters["project"] = ["in", matching_projects]

    actual_projects = frappe.get_all(
        "MPIT Actual Entry",
        filters=actual_filters,
        pluck="project",
        distinct=True,
    )
    projects.update(actual_projects)

    return sorted(list(projects))


def _get_planned_amounts(
    year_start,
    year_end,
    project_filter: str | None,
    cost_center_filter: str | None,
    status_filter: str | None,
) -> dict[str, float]:
    """Get total planned amount per project from MPIT Planned Items."""
    # Build filters for projects if cost_center or status filter provided
    project_filters = {}
    if cost_center_filter:
        project_filters["cost_center"] = cost_center_filter
    if status_filter:
        project_filters["status"] = status_filter
    if project_filter:
        project_filters["name"] = project_filter

    # Get project names that match filters
    project_names = None
    if project_filters:
        project_names = frappe.get_all("MPIT Project", filters=project_filters, pluck="name")
        if not project_names:
            return {}

    # Get Planned Items for year range
    pi_filters = {
        "docstatus": 1,
        "start_date": ["between", [year_start, year_end]],
    }
    if project_names:
        pi_filters["project"] = ["in", project_names]

    planned_items = frappe.get_all(
        "MPIT Planned Item",
        filters=pi_filters,
        fields=["project", "amount"],
    )

    # Aggregate by project
    amounts = {}
    for item in planned_items:
        proj = item.get("project")
        amt = flt(item.get("amount"), 2)
        amounts[proj] = amounts.get(proj, 0) + amt

    return amounts


def _get_actual_amounts(year: str, project_filter: str | None, cost_center_filter: str | None) -> dict[str, float]:
    """Get total actual (Delta, Verified) amount per project."""
    filters = {
        "year": year,
        "status": "Verified",
        "entry_kind": "Delta",
        "project": ["is", "set"],
    }
    if project_filter:
        filters["project"] = project_filter

    # If cost_center filter, get matching projects first
    if cost_center_filter:
        proj_names = frappe.get_all(
            "MPIT Project",
            filters={"cost_center": cost_center_filter},
            pluck="name",
        )
        if not proj_names:
            return {}
        filters["project"] = ["in", proj_names]

    actuals = frappe.get_all(
        "MPIT Actual Entry",
        filters=filters,
        fields=["project", "amount_net"],
    )

    # Aggregate by project
    amounts = {}
    for entry in actuals:
        proj = entry.get("project")
        amt = flt(entry.get("amount_net"), 2)
        amounts[proj] = amounts.get(proj, 0) + amt

    return amounts


def _get_project_info(projects: list[str]) -> dict[str, dict]:
    """Get project status and cost_center."""
    if not projects:
        return {}

    info_list = frappe.get_all(
        "MPIT Project",
        filters={"name": ["in", projects]},
        fields=["name", "status", "cost_center"],
    )

    return {i["name"]: {"status": i.get("status"), "cost_center": i.get("cost_center")} for i in info_list}


def _build_chart(rows: list[dict]) -> dict | None:
    if not rows:
        return None

    planned_data = {}
    actual_data = {}
    for r in rows:
        proj = r["project"]
        planned_data[proj] = flt(r.get("planned_amount") or 0, 2)
        actual_data[proj] = flt(r.get("actual_amount") or 0, 2)

    labels = sorted(planned_data.keys())
    return {
        "data": {
            "labels": labels,
            "datasets": [
                {"name": _("Planned"), "values": [planned_data.get(p, 0) for p in labels]},
                {"name": _("Exceptions (Verified)"), "values": [actual_data.get(p, 0) for p in labels]},
            ],
        },
        "type": "bar",
        "axis_options": {"x_axis_mode": "tick", "y_axis_mode": "tick"},
    }
