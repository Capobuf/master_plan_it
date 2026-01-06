"""
FILE: master_plan_it/report/mpit_plan_vs_cap_vs_actual/mpit_plan_vs_cap_vs_actual.py
SCOPO: Report v3 che mostra per Cost Center: Plan (Live), Cap (Snapshot + Addendum), Actual, Over/Remaining.
INPUT: Filtri (year obbligatorio, cost_center opzionale).
OUTPUT: Righe aggregate per Cost Center con confronto Plan vs Cap vs Actual.
"""

from __future__ import annotations

import datetime

import frappe
from frappe import _
from frappe.utils import cint, flt
from master_plan_it.master_plan_it.utils.dashboard_utils import normalize_dashboard_filters


def execute(filters=None):
    filters = normalize_dashboard_filters(filters)
    filters = frappe._dict(filters or {})

    filters.year = _resolve_year(filters)
    if not filters.year:
        frappe.throw(_("No MPIT Year found. Please create one or set the Year filter."))

    filters.allowed_cost_centers = _resolve_cost_centers(filters.get("cost_center"), cint(filters.get("include_children")))

    columns = _get_columns()
    data = _get_data(filters)
    chart = _build_chart(data)

    return columns, data, None, chart


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
    return [
        {"label": _("Cost Center"), "fieldname": "cost_center", "fieldtype": "Link", "options": "MPIT Cost Center", "width": 200},
        {"label": _("Plan (Live)"), "fieldname": "plan_amount", "fieldtype": "Currency", "width": 140},
        {"label": _("Snapshot (APP)"), "fieldname": "snapshot_amount", "fieldtype": "Currency", "width": 140},
        {"label": _("Addendum Total"), "fieldname": "addendum_total", "fieldtype": "Currency", "width": 140},
        {"label": _("Cap"), "fieldname": "cap_amount", "fieldtype": "Currency", "width": 140},
        {"label": _("Actual"), "fieldname": "actual_amount", "fieldtype": "Currency", "width": 140},
        {"label": _("Remaining"), "fieldname": "remaining", "fieldtype": "Currency", "width": 140},
        {"label": _("Over Cap"), "fieldname": "over_cap", "fieldtype": "Currency", "width": 140},
        {"label": _("% Used"), "fieldname": "pct_used", "fieldtype": "Percent", "width": 100},
    ]


def _get_data(filters) -> list[dict]:
    year = filters.year
    allowed_cost_centers = filters.get("allowed_cost_centers")

    # Get all cost centers with activity for this year
    cost_centers = _get_active_cost_centers(year, allowed_cost_centers)
    if not cost_centers:
        return []

    # Get Live budget amounts per cost center
    live_amounts = _get_live_amounts(year, allowed_cost_centers)

    # Get Snapshot amounts per cost center
    snapshot_amounts = _get_snapshot_amounts(year, allowed_cost_centers)

    # Get Addendum totals per cost center
    addendum_totals = _get_addendum_totals(year, allowed_cost_centers)

    # Get Actual amounts per cost center
    actual_amounts = _get_actual_amounts(year, allowed_cost_centers)

    rows = []
    for cc in cost_centers:
        plan = flt(live_amounts.get(cc, 0), 2)
        snapshot = flt(snapshot_amounts.get(cc, 0), 2)
        addendum = flt(addendum_totals.get(cc, 0), 2)
        cap = flt(snapshot + addendum, 2)
        actual = flt(actual_amounts.get(cc, 0), 2)

        remaining = flt(cap - actual, 2) if cap > actual else 0
        over_cap = flt(actual - cap, 2) if actual > cap else 0
        pct_used = flt((actual / cap * 100), 2) if cap > 0 else 0

        rows.append({
            "cost_center": cc,
            "plan_amount": plan,
            "snapshot_amount": snapshot,
            "addendum_total": addendum,
            "cap_amount": cap,
            "actual_amount": actual,
            "remaining": remaining,
            "over_cap": over_cap,
            "pct_used": pct_used,
        })

    return rows


def _get_active_cost_centers(year: str, allowed_cost_centers: list[str] | None) -> list[str]:
    """Get all cost centers that have any activity (budget lines, actual, or addendum) for this year."""
    ccs = set()

    # From Live budget lines
    live_budget = frappe.db.get_value(
        "MPIT Budget",
        filters={"year": year, "budget_type": "Live", "docstatus": 0},
        fieldname="name",
    )
    if live_budget:
        budget_ccs = frappe.get_all(
            "MPIT Budget Line",
            filters={"parent": live_budget, "cost_center": ["is", "set"]},
            pluck="cost_center",
            distinct=True,
        )
        ccs.update(budget_ccs)

    # From Snapshot budget lines
    snapshot_budget = frappe.db.get_value(
        "MPIT Budget",
        filters={"year": year, "budget_type": "Snapshot", "docstatus": 1},
        fieldname="name",
        order_by="modified desc",
    )
    if snapshot_budget:
        snapshot_ccs = frappe.get_all(
            "MPIT Budget Line",
            filters={"parent": snapshot_budget, "cost_center": ["is", "set"]},
            pluck="cost_center",
            distinct=True,
        )
        ccs.update(snapshot_ccs)

    # From Addendums
    addendum_ccs = frappe.get_all(
        "MPIT Budget Addendum",
        filters={"year": year, "docstatus": 1},
        pluck="cost_center",
        distinct=True,
    )
    ccs.update(addendum_ccs)

    # From Actual Entries
    actual_ccs = frappe.get_all(
        "MPIT Actual Entry",
        filters={"year": year, "status": "Verified", "cost_center": ["is", "set"]},
        pluck="cost_center",
        distinct=True,
    )
    ccs.update(actual_ccs)

    result = sorted(list(ccs))

    if allowed_cost_centers:
        allowed = set(allowed_cost_centers)
        result = [cc for cc in result if cc in allowed]

    return result


def _get_live_amounts(year: str, allowed_cost_centers: list[str] | None) -> dict[str, float]:
    """Get sum of annual_net per cost center from Live budget."""
    live_budget = frappe.db.get_value(
        "MPIT Budget",
        filters={"year": year, "budget_type": "Live", "docstatus": 0},
        fieldname="name",
    )
    if not live_budget:
        return {}

    filters = {"parent": live_budget}
    if allowed_cost_centers:
        filters["cost_center"] = ["in", allowed_cost_centers]

    lines = frappe.get_all(
        "MPIT Budget Line",
        filters=filters,
        fields=["cost_center", "annual_net"],
    )

    amounts = {}
    for line in lines:
        cc = line.cost_center
        if cc:
            amounts[cc] = amounts.get(cc, 0) + flt(line.annual_net or 0)
    return amounts


def _get_snapshot_amounts(year: str, allowed_cost_centers: list[str] | None) -> dict[str, float]:
    """Get sum of annual_net per cost center from approved Snapshot (Allowance lines only for Cap)."""
    snapshot_budget = frappe.db.get_value(
        "MPIT Budget",
        filters={"year": year, "budget_type": "Snapshot", "docstatus": 1},
        fieldname="name",
        order_by="modified desc",
    )
    if not snapshot_budget:
        return {}

    filters = {"parent": snapshot_budget, "line_kind": "Allowance"}
    if allowed_cost_centers:
        filters["cost_center"] = ["in", allowed_cost_centers]

    lines = frappe.get_all(
        "MPIT Budget Line",
        filters=filters,
        fields=["cost_center", "annual_net"],
    )

    amounts = {}
    for line in lines:
        cc = line.cost_center
        if cc:
            amounts[cc] = amounts.get(cc, 0) + flt(line.annual_net or 0)
    return amounts


def _get_addendum_totals(year: str, allowed_cost_centers: list[str] | None) -> dict[str, float]:
    """Get sum of delta_amount per cost center from approved Addendums."""
    filters = {"year": year, "docstatus": 1}
    if allowed_cost_centers:
        filters["cost_center"] = ["in", allowed_cost_centers]

    addendums = frappe.get_all(
        "MPIT Budget Addendum",
        filters=filters,
        fields=["cost_center", "delta_amount"],
    )

    amounts = {}
    for add in addendums:
        cc = add.cost_center
        if cc:
            amounts[cc] = amounts.get(cc, 0) + flt(add.delta_amount or 0)
    return amounts


def _get_actual_amounts(year: str, allowed_cost_centers: list[str] | None) -> dict[str, float]:
    """Get sum of amount_net per cost center from Verified Actual Entries."""
    filters = {"year": year, "status": "Verified"}
    if allowed_cost_centers:
        filters["cost_center"] = ["in", allowed_cost_centers]

    actuals = frappe.get_all(
        "MPIT Actual Entry",
        filters=filters,
        fields=["cost_center", "amount_net"],
    )

    amounts = {}
    for act in actuals:
        cc = act.cost_center
        if cc:
            amounts[cc] = amounts.get(cc, 0) + flt(act.amount_net or 0)
    return amounts


def _build_chart(data: list[dict]) -> dict:
    """Build bar chart comparing Plan, Cap, Actual per Cost Center."""
    if not data:
        return {}

    labels = [row["cost_center"] for row in data]
    plan_values = [row["plan_amount"] for row in data]
    cap_values = [row["cap_amount"] for row in data]
    actual_values = [row["actual_amount"] for row in data]

    return {
        "data": {
            "labels": labels,
            "datasets": [
                {"name": _("Plan (Live)"), "values": plan_values},
                {"name": _("Cap"), "values": cap_values},
                {"name": _("Actual"), "values": actual_values},
            ],
        },
        "type": "bar",
        "barOptions": {"stacked": False},
    }
