from __future__ import annotations

import collections
import datetime

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
		{"label": _("Days to Renewal"), "fieldname": "days_to_renewal", "fieldtype": "Int", "width": 110},
		{"label": _("Auto Renew"), "fieldname": "auto_renew", "fieldtype": "Check", "width": 90},
		{"label": _("Status"), "fieldname": "status", "fieldtype": "Data", "width": 100},
		{"label": _("End Date"), "fieldname": "end_date", "fieldtype": "Date", "width": 110},
		{"label": _("Count"), "fieldname": "count", "fieldtype": "Int", "width": 60, "hidden": 1},
		{"label": _("Expired Count"), "fieldname": "expired_count", "fieldtype": "Int", "width": 80, "hidden": 1},
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
	allowed_cost_centers = filters.get("allowed_cost_centers")

	rows = []
	expired_count = 0

	from frappe.query_builder.functions import Coalesce

	Contract = frappe.qb.DocType("MPIT Contract")
	renewal_date = Coalesce(Contract.next_renewal_date, Contract.end_date)

	query = (
		frappe.qb.from_(Contract)
		.select(
			Contract.name,
			Contract.description,
			Contract.vendor,
			Contract.cost_center,
			renewal_date.as_("renewal_date"),
			Contract.auto_renew,
			Contract.status,
			Contract.end_date,
		)
		.where(renewal_date.isnotnull())
	)

	if auto_renew_only:
		query = query.where(Coalesce(Contract.auto_renew, 0) == 1)

	if allowed_cost_centers:
		query = query.where(Contract.cost_center.isin(allowed_cost_centers))

	contracts = query.run(as_dict=True)

	for c in contracts:
		nrd = getdate(c.renewal_date)
		is_expired = 1 if nrd < start_date else 0

		# Skip records outside the forward window
		if nrd > end_date:
			continue
		# Skip expired if caller does not want them
		if is_expired and not include_past:
			continue

		days_to = (nrd - start_date).days
		if is_expired:
			expired_count += 1

		rows.append({
			"contract": c.name,
			"title": c.description or c.name,
			"vendor": c.vendor,
			"next_renewal_date": c.renewal_date,
			"days_to_renewal": days_to,
			"auto_renew": c.auto_renew,
			"status": c.status,
			"end_date": c.end_date,
			"count": 1,
			"expired_count": is_expired,
		})

	summary = [
		{"label": _("Upcoming (<= {0} days)").format(days), "value": len(rows) - expired_count, "indicator": "green"},
		{"label": _("Expired"), "value": expired_count, "indicator": "red"},
	]

	return rows, summary


def _build_chart(rows: list[dict]) -> dict | None:
	if not rows:
		return None

	buckets: dict[str, int] = collections.Counter()
	for r in rows:
		if not r.get("next_renewal_date"):
			continue
		month = getdate(r["next_renewal_date"]).strftime("%Y-%m")
		buckets[month] += 1

	labels = sorted(buckets.keys())
	return {
		"data": {
			"labels": labels,
			"datasets": [
				{"name": _("Renewals"), "values": [buckets.get(m, 0) for m in labels]},
			],
		},
		"type": "bar",
		"axis_options": {"x_axis_mode": "tick", "y_axis_mode": "tick"},
	}


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
