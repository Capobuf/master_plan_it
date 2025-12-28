from __future__ import annotations

import frappe
from frappe import _


@frappe.whitelist()
def get(
	chart_name=None,
	chart=None,
	no_cache=None,
	filters=None,
	from_date=None,
	to_date=None,
	timespan=None,
	time_interval=None,
	heatmap_year=None,
):
	"""Return amendment deltas per category, NET amounts only.

	Year comes from the linked Budget (not from dates). Include only submitted amendments.
	"""
	if isinstance(filters, str):
		filters = frappe.parse_json(filters)
	filters = frappe._dict(filters or {})

	if not filters.get("year"):
		frappe.throw(_("year is required"))

	top_n = _sanitize_top_n(filters.get("top_n"))
	params = {"year": filters.year, "top_n": top_n}

	conditions = ["ba.docstatus = 1", "b.year = %(year)s"]
	if filters.get("budget"):
		conditions.append("ba.budget = %(budget)s")
		params["budget"] = filters.budget

	where_clause = " AND ".join(conditions)

	rows = frappe.db.sql(
		f"""
		SELECT
			al.category AS category,
			SUM(COALESCE(al.delta_amount_net, al.delta_amount)) AS delta_net
		FROM `tabMPIT Budget Amendment` ba
		JOIN `tabMPIT Budget` b ON b.name = ba.budget
		JOIN `tabMPIT Amendment Line` al ON al.parent = ba.name
		WHERE {where_clause}
		GROUP BY al.category
		ORDER BY ABS(SUM(COALESCE(al.delta_amount_net, al.delta_amount))) DESC
		LIMIT %(top_n)s
		""",
		params,
		as_dict=True,
	)

	labels = []
	values = []
	for row in rows:
		labels.append(row.get("category") or _("(Uncategorized)"))
		values.append(float(row.get("delta_net") or 0))

	return {
		"labels": labels,
		"datasets": [
			{
				"name": _("Amendments Delta (Net)"),
				"values": values,
			}
		],
		"type": "bar",
	}


def _sanitize_top_n(raw_top_n) -> int:
	try:
		value = int(raw_top_n)
	except Exception:
		value = 10

	return max(1, min(value, 50))
