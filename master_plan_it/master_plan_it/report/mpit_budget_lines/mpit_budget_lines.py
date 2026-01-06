"""
FILE: master_plan_it/report/mpit_budget_lines/mpit_budget_lines.py
SCOPO: Script Report che mostra il dettaglio delle righe budget con aggregazioni, chart e KPI cards.
INPUT: Filtri (year obbligatorio, budget_type, cost_center, line_kind, vendor).
OUTPUT: Columns, Data, Chart (bar per line_kind), Report Summary (KPI totali).
"""

from __future__ import annotations

import datetime

import frappe
from frappe import _
from frappe.utils import cint, flt


def execute(filters=None):
    """Main entry point for Script Report."""
    if isinstance(filters, str):
        filters = frappe.parse_json(filters)
    filters = frappe._dict(filters or {})

    filters.year = _resolve_year(filters)
    if not filters.year:
        frappe.throw(_("No MPIT Year found. Please create one or set the Year filter."))

    filters.allowed_cost_centers = _resolve_cost_centers(
        filters.get("cost_center"), cint(filters.get("include_children"))
    )

    columns = _get_columns()
    data = _get_data(filters)
    report_summary = _get_report_summary(data)
    chart = _get_chart(data, filters)

    return columns, data, None, chart, report_summary


def _resolve_year(filters) -> str | None:
    """Resolve year from filters or the MPIT Year covering today (fallback: latest year)."""
    if filters.get("year"):
        return str(filters.year)

    today = datetime.date.today()
    year_name = frappe.db.get_value(
        "MPIT Year",
        {"start_date": ["<=", today], "end_date": [">=", today]},
        "name",
    )
    if year_name:
        return year_name

    return frappe.db.get_value("MPIT Year", {}, "name", order_by="year desc")


def _resolve_cost_centers(cost_center: str | None, include_children: int = 0) -> list[str] | None:
    """Resolve cost centers, optionally including children in tree."""
    if not cost_center:
        return None
    if not include_children:
        return [cost_center]

    row = frappe.db.get_value("MPIT Cost Center", cost_center, ["lft", "rgt"], as_dict=True)
    if not row or row.lft is None or row.rgt is None:
        frappe.throw(_("Cost Center {0} is missing tree bounds (lft/rgt).").format(cost_center))

    return frappe.db.get_all(
        "MPIT Cost Center",
        filters={"lft": [">=", row.lft], "rgt": ["<=", row.rgt]},
        pluck="name",
    )


def _get_columns() -> list[dict]:
    """Return column definitions for the report."""
    return [
        {"label": _("Budget"), "fieldname": "budget", "fieldtype": "Link", "options": "MPIT Budget", "width": 150},
        {"label": _("Cost Center"), "fieldname": "cost_center", "fieldtype": "Link", "options": "MPIT Cost Center", "width": 180},
        {"label": _("Line Kind"), "fieldname": "line_kind", "fieldtype": "Data", "width": 100},
        {"label": _("Vendor"), "fieldname": "vendor", "fieldtype": "Link", "options": "MPIT Vendor", "width": 150},
        {"label": _("Description"), "fieldname": "description", "fieldtype": "Data", "width": 200},
        {"label": _("Contract"), "fieldname": "contract", "fieldtype": "Link", "options": "MPIT Contract", "width": 120},
        {"label": _("Project"), "fieldname": "project", "fieldtype": "Link", "options": "MPIT Project", "width": 120},
        {"label": _("Monthly Amount"), "fieldname": "monthly_amount", "fieldtype": "Currency", "options": "currency", "width": 120},
        {"label": _("Annual Amount"), "fieldname": "annual_amount", "fieldtype": "Currency", "options": "currency", "width": 120},
        {"label": _("VAT Rate"), "fieldname": "vat_rate", "fieldtype": "Percent", "width": 80},
        {"label": _("Annual Net"), "fieldname": "annual_net", "fieldtype": "Currency", "options": "currency", "width": 120},
        {"label": _("Annual Gross"), "fieldname": "annual_gross", "fieldtype": "Currency", "options": "currency", "width": 120},
        {"label": _("Currency"), "fieldname": "currency", "fieldtype": "Data", "width": 0, "hidden": 1},
    ]


def _get_data(filters) -> list[dict]:
    """Fetch budget lines matching the filters."""
    year = filters.year
    budget_type = filters.get("budget_type") or "Live"
    allowed_cost_centers = filters.get("allowed_cost_centers")
    line_kind_filter = filters.get("line_kind")
    vendor_filter = filters.get("vendor")

    # Find budget(s) matching criteria
    budget_filters = {"year": year, "budget_type": budget_type}
    if budget_type == "Live":
        budget_filters["docstatus"] = 0
    else:
        # For Snapshot, get the latest approved one
        budget_filters["docstatus"] = 1

    budgets = frappe.get_all(
        "MPIT Budget",
        filters=budget_filters,
        fields=["name"],
        order_by="modified desc",
        limit=10,  # In case multiple snapshots exist
    )
    if not budgets:
        return []

    budget_names = [b.name for b in budgets]

    # Build line filters
    line_filters = {"parent": ["in", budget_names]}
    if allowed_cost_centers:
        line_filters["cost_center"] = ["in", allowed_cost_centers]
    if line_kind_filter:
        line_filters["line_kind"] = line_kind_filter
    if vendor_filter:
        line_filters["vendor"] = vendor_filter

    lines = frappe.get_all(
        "MPIT Budget Line",
        filters=line_filters,
        fields=[
            "parent",
            "cost_center",
            "line_kind",
            "vendor",
            "description",
            "contract",
            "project",
            "monthly_amount",
            "annual_amount",
            "vat_rate",
            "annual_net",
            "annual_gross",
        ],
        order_by="parent, idx",
    )

    # Get default currency from system settings
    default_currency = frappe.db.get_single_value("System Settings", "currency") or "EUR"

    data = []
    for line in lines:
        data.append({
            "budget": line.parent,
            "cost_center": line.cost_center,
            "line_kind": line.line_kind,
            "vendor": line.vendor,
            "description": line.description,
            "contract": line.contract,
            "project": line.project,
            "monthly_amount": flt(line.monthly_amount, 2),
            "annual_amount": flt(line.annual_amount, 2),
            "vat_rate": flt(line.vat_rate, 2),
            "annual_net": flt(line.annual_net, 2),
            "annual_gross": flt(line.annual_gross, 2),
            "currency": default_currency,
        })

    return data


def _get_report_summary(data: list[dict]) -> list[dict]:
    """Build KPI summary cards."""
    if not data:
        return []

    # Get default currency from system settings
    default_currency = frappe.db.get_single_value("System Settings", "currency") or "EUR"

    total_net = sum(flt(row.get("annual_net", 0)) for row in data)
    total_gross = sum(flt(row.get("annual_gross", 0)) for row in data)
    count_contract = sum(1 for row in data if row.get("line_kind") == "Contract")
    count_planned_item = sum(1 for row in data if row.get("line_kind") == "Planned Item")
    count_allowance = sum(1 for row in data if row.get("line_kind") == "Allowance")

    return [
        {
            "value": total_net,
            "label": _("Total Annual Net"),
            "datatype": "Currency",
            "currency": default_currency,
            "indicator": "Blue",
        },
        {
            "value": total_gross,
            "label": _("Total Annual Gross"),
            "datatype": "Currency",
            "currency": default_currency,
            "indicator": "Green",
        },
        {
            "value": count_contract,
            "label": _("Contract Lines"),
            "datatype": "Int",
            "indicator": "Gray",
        },
        {
            "value": count_planned_item,
            "label": _("Planned Item Lines"),
            "datatype": "Int",
            "indicator": "Orange",
        },
        {
            "value": count_allowance,
            "label": _("Allowance Lines"),
            "datatype": "Int",
            "indicator": "Purple",
        },
    ]


def _get_chart(data: list[dict], filters) -> dict:
    """Build donut chart with dynamic grouping based on filter."""
    if not data:
        return {}

    group_by = filters.get("chart_group_by") or "Line Kind"
    
    # Map filter value to data field
    field_map = {
        "Line Kind": "line_kind",
        "Cost Center": "cost_center",
        "Vendor": "vendor",
    }
    field = field_map.get(group_by, "line_kind")

    # Aggregate by selected field
    by_group = {}
    for row in data:
        key = row.get(field) or _("(Not Set)")
        by_group[key] = by_group.get(key, 0) + flt(row.get("annual_net", 0))

    labels = list(by_group.keys())
    values = [flt(by_group[k], 2) for k in labels]

    return {
        "data": {
            "labels": labels,
            "datasets": [
                {"name": _("Annual Net"), "values": values},
            ],
        },
        "type": "donut",
    }
