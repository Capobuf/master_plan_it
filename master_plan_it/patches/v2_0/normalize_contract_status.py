# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt
"""
Backfill: keep auto-renew contracts Active (clear stale Pending Renewal).

Run via migrate (patches.txt) or manually:
bench --site <site> execute master_plan_it.patches.v2_0.normalize_contract_status.execute
"""

from __future__ import annotations

import frappe


def execute():
	"""Force auto_renew contracts out of Pending Renewal drift."""
	if not frappe.db.has_column("MPIT Contract", "auto_renew") or not frappe.db.has_column("MPIT Contract", "status"):
		return

	frappe.db.sql(
		"""
		UPDATE `tabMPIT Contract`
		SET status = 'Active'
		WHERE auto_renew = 1 AND status NOT IN ('Active', 'Renewed', 'Cancelled', 'Expired')
		"""
	)
	frappe.db.commit()
