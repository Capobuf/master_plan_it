"""
Dashboard Chart Source: Projects by Status

Counts MPIT Project records grouped by status.
"""

from __future__ import annotations

import frappe
from frappe import _


def get_config():
	return {
		"method": "master_plan_it.master_plan_it.dashboard_chart_source.mpit_projects_by_status.get_data",
		"filters": [],
	}


def get_data(filters=None):
	rows = frappe.db.sql(
		"""
		SELECT status, COUNT(*) AS total
		FROM `tabMPIT Project`
		GROUP BY status
		ORDER BY total DESC
		""",
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
		"type": "bar",
	}
