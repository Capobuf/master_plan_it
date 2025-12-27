from __future__ import annotations

import collections
import datetime

import frappe
from frappe import _
from frappe.utils import add_days, cint, getdate, nowdate


def execute(filters=None):
	if isinstance(filters, str):
		filters = frappe.parse_json(filters)
	filters = frappe._dict(filters or {})
	rows, summary = _get_data(filters)

	columns = [
		_("Contract") + ":Link/MPIT Contract:160",
		_("Title") + ":Data:180",
		_("Vendor") + ":Link/MPIT Vendor:150",
		_("Category") + ":Link/MPIT Category:150",
		_("Next Renewal Date") + ":Date:120",
		_("Days to Renewal") + ":Int:110",
		_("Notice Days") + ":Int:100",
		_("Auto Renew") + ":Check:90",
		_("Status") + ":Data:100",
		_("End Date") + ":Date:110",
		_("Count") + ":Int:60",
		_("Expired Count") + ":Int:80",
	]

	chart = _build_chart(rows)

	return columns, rows, None, chart, summary


def _get_data(filters):
	days = cint(filters.get("days") or 90)
	include_past = cint(filters.get("include_past") or 0)
	auto_renew_only = cint(filters.get("auto_renew_only") or 0)
	start_date = getdate(filters.get("from_date") or nowdate())
	end_date = add_days(start_date, days)

	rows = []
	expired_count = 0

	where = ["next_renewal_date IS NOT NULL"]
	if auto_renew_only:
		where.append("COALESCE(auto_renew, 0) = 1")

	contracts = frappe.db.sql(
		f"""
		SELECT
			name,
			title,
			vendor,
			category,
			next_renewal_date,
			notice_days,
			auto_renew,
			status,
			end_date
		FROM `tabMPIT Contract`
		WHERE {" AND ".join(where)}
		""",
		as_dict=True,
	)

	for c in contracts:
		nrd = getdate(c.next_renewal_date)
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
			"title": c.title or c.name,
			"vendor": c.vendor,
			"category": c.category,
			"next_renewal_date": c.next_renewal_date,
			"days_to_renewal": days_to,
			"notice_days": c.notice_days,
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
