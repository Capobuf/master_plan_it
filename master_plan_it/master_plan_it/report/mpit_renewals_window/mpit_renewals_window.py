from __future__ import annotations

import collections

import frappe
from frappe import _
from frappe.utils import add_days, cint, getdate, nowdate

from master_plan_it.master_plan_it.utils.dashboard_utils import normalize_dashboard_filters


def execute(filters=None):
    filters = normalize_dashboard_filters(filters)
    filters = frappe._dict(filters or {})

    filters.allowed_cost_centers = _resolve_cost_centers(filters.get("cost_center"), cint(filters.get("include_children")))
    rows, summary = _get_data(filters)

    columns = [
        {"label": _("Contract"), "fieldname": "contract", "fieldtype": "Link", "options": "MPIT Contract", "width": 160},
        {"label": _("Title"), "fieldname": "title", "fieldtype": "Data", "width": 180},
        {"label": _("Vendor"), "fieldname": "vendor", "fieldtype": "Link", "options": "MPIT Vendor", "width": 150},
        {"label": _("Cost Center"), "fieldname": "cost_center", "fieldtype": "Link", "options": "MPIT Cost Center", "width": 150},
        {"label": _("Next Renewal Date"), "fieldname": "next_renewal_date", "fieldtype": "Date", "width": 120},
        {"label": _("Days To Renewal"), "fieldname": "days_to_renewal", "fieldtype": "Int", "width": 110},
        {"label": _("Auto Renew"), "fieldname": "auto_renew", "fieldtype": "Check", "width": 90},
        {"label": _("Status"), "fieldname": "status", "fieldtype": "Data", "width": 100},
    ]

    chart = _build_chart(rows)
    return columns, rows, None, chart, summary


def _get_data(filters):
    settings = frappe.get_single("MPIT Settings")
    days = cint(filters.get("days") or settings.renewal_window_days or 90)
    include_past = cint(filters.get("include_past") or 0)
    auto_renew_only = cint(filters.get("auto_renew_only") or 0)

    start_date = getdate(filters.get("from_date") or nowdate())
    end_date = add_days(start_date, days)

    contracts_filters = {}
    if auto_renew_only:
        contracts_filters["auto_renew"] = 1

    if filters.allowed_cost_centers:
        contracts_filters["cost_center"] = ["in", filters.allowed_cost_centers]

    contracts = frappe.get_all(
        "MPIT Contract",
        filters=contracts_filters,
        fields=["name", "description", "vendor", "cost_center", "next_renewal_date", "end_date", "auto_renew", "status"],
        order_by="next_renewal_date asc",
        limit=None,
    )

    rows = []
    expired_count = 0

    for contract in contracts:
        renewal_date = contract.next_renewal_date or contract.end_date
        if not renewal_date:
            continue

        renewal_date = getdate(renewal_date)
        if renewal_date > end_date:
            continue

        is_expired = renewal_date < start_date
        if is_expired and not include_past:
            continue

        if is_expired:
            expired_count += 1

        rows.append(
            {
                "contract": contract.name,
                "title": contract.description or contract.name,
                "vendor": contract.vendor,
                "cost_center": contract.cost_center,
                "next_renewal_date": renewal_date,
                "days_to_renewal": (renewal_date - start_date).days,
                "auto_renew": contract.auto_renew,
                "status": contract.status,
            }
        )

    summary = [
        {"label": _("Upcoming (<= {0} days)").format(days), "value": len(rows) - expired_count, "indicator": "green"},
        {"label": _("Expired"), "value": expired_count, "indicator": "red"},
    ]

    return rows, summary


def _build_chart(rows: list[dict]) -> dict | None:
    if not rows:
        return None

    buckets: dict[str, int] = collections.Counter()
    for row in rows:
        month = getdate(row.get("next_renewal_date")).strftime("%Y-%m")
        buckets[month] += 1

    labels = sorted(buckets.keys())
    return {
        "data": {
            "labels": labels,
            "datasets": [{"name": _("Renewals"), "values": [buckets.get(label, 0) for label in labels]}],
        },
        "type": "bar",
    }


def _resolve_cost_centers(cost_center: str | None, include_children: int = 0) -> list[str] | None:
    if not cost_center:
        return None

    if not include_children:
        return [cost_center]

    row = frappe.db.get_value("MPIT Cost Center", cost_center, ["lft", "rgt"], as_dict=True)
    if not row or row.lft is None or row.rgt is None:
        frappe.throw(_("Cost Center {0} is missing tree bounds (lft/rgt)." ).format(cost_center))

    return frappe.db.get_all(
        "MPIT Cost Center",
        filters={"lft": [">=", row.lft], "rgt": ["<=", row.rgt]},
        pluck="name",
    )
