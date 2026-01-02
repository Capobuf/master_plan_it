"""
FILE: master_plan_it/patches/v3_0/backfill_parent_mpit_cost_center.py
SCOPO: Backfill `parent_mpit_cost_center` from legacy `parent_cost_center` and rebuild tree.
OUTPUT/SIDE EFFECTS:
- Adds the column if missing.
- Copies legacy parent values where the new field is empty.
- Rebuilds Nested Set to keep tree view consistent.
"""

from __future__ import annotations

import frappe
from frappe.utils.nestedset import rebuild_tree


def execute():
	"""Ensure parent_mpit_cost_center is populated and rebuilds the tree."""
	if not frappe.db.exists("DocType", "MPIT Cost Center"):
		return

	if not frappe.db.has_column("MPIT Cost Center", "parent_mpit_cost_center"):
		frappe.db.sql_ddl(
			"""
			ALTER TABLE `tabMPIT Cost Center`
			ADD COLUMN `parent_mpit_cost_center` varchar(140) NULL
			"""
		)

	if not frappe.db.has_column("MPIT Cost Center", "parent_cost_center"):
		return

	needs_backfill = frappe.db.sql(
		"""
		SELECT name
		FROM `tabMPIT Cost Center`
		WHERE (parent_mpit_cost_center IS NULL OR parent_mpit_cost_center = '')
			AND parent_cost_center IS NOT NULL
			AND parent_cost_center != ''
		LIMIT 1
		"""
	)
	if not needs_backfill:
		return

	frappe.db.sql(
		"""
		UPDATE `tabMPIT Cost Center`
		SET parent_mpit_cost_center = parent_cost_center
		WHERE (parent_mpit_cost_center IS NULL OR parent_mpit_cost_center = '')
			AND parent_cost_center IS NOT NULL
			AND parent_cost_center != ''
		"""
	)
	rebuild_tree("MPIT Cost Center")
	frappe.db.commit()
