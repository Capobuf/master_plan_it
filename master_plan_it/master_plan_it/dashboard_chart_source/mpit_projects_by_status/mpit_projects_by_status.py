"""
Dashboard Chart Source: Projects by Status

Counts MPIT Project records grouped by status.
"""

from __future__ import annotations

import frappe
from frappe import _


def get_config():
	return {
		"method": "master_plan_it.master_plan_it.dashboard_chart_source.mpit_projects_by_status.mpit_projects_by_status.get",
		"filters": [],
	}


def get_data(filters=None):
	filters = frappe._dict(filters or {})
	cost_centers = filters.get("cost_centers") or None
	if cost_centers:
		cost_centers = tuple(cost_centers)
		if not cost_centers:
			return {"labels": [], "datasets": [], "type": "percentage"}
	cc_clause = " WHERE cost_center IN %(cost_centers)s" if cost_centers else ""
	params = {"cost_centers": cost_centers} if cost_centers else {}

	rows = frappe.db.sql(
		f"""
		SELECT status, COUNT(*) AS total
		FROM `tabMPIT Project`
		{cc_clause}
		GROUP BY status
		ORDER BY total DESC
		""",
		params,
		as_dict=True,
	)

	labels = []
	values = []
	for row in rows:
		label = row.status or _("Unknown")
		labels.append(label)
		values.append(int(row.total or 0))

	return {
		"labels": labels,
		"datasets": [{"name": _("Projects"), "values": values}],
		"type": "percentage",
	}

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
	# Normalizza filters (puo arrivare dict o JSON-string)
	if isinstance(filters, str):
		filters = frappe.parse_json(filters)

	filters = frappe._dict(filters or {})

	# Compatibilita: filtro UI usa cost_center singolo; i tuoi get_data usano cost_centers lista
	if filters.get("cost_center") and not filters.get("cost_centers"):
		filters.cost_centers = [filters.cost_center]

	return get_data(filters)
