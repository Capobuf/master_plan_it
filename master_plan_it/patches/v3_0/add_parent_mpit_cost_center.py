"""
FILE: master_plan_it/patches/v3_0/add_parent_mpit_cost_center.py
SCOPO: Allineare il Tree MPIT Cost Center al naming Frappe (`parent_<scrubbed_doctype>`).
OUTPUT/SIDE EFFECTS:
- aggiunge la colonna `parent_mpit_cost_center` se assente
- copia i valori esistenti da `parent_cost_center`
NOTE: mantiene la colonna precedente (se presente) per non perdere dati legacy.
"""

from __future__ import annotations

import frappe


def execute():
	"""Ensure parent_mpit_cost_center column exists and is populated."""
	if not frappe.db.has_column("MPIT Cost Center", "parent_mpit_cost_center"):
		frappe.db.sql_ddl(
			"""
			ALTER TABLE `tabMPIT Cost Center`
			ADD COLUMN `parent_mpit_cost_center` varchar(140) NULL
			"""
		)
		frappe.db.sql(
			"""
			UPDATE `tabMPIT Cost Center`
			SET parent_mpit_cost_center = parent_cost_center
			WHERE parent_cost_center IS NOT NULL
			"""
		)
		frappe.db.commit()
