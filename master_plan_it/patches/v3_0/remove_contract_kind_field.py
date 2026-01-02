"""
FILE: master_plan_it/patches/v3_0/remove_contract_kind_field.py
SCOPO: Rimuovere il campo contract_kind deprecato da MPIT Contract (non più usato in v3).
INPUT: Nessuno.
OUTPUT/SIDE EFFECTS: Drop colonna contract_kind da `tabMPIT Contract` se esiste.
"""

from __future__ import annotations

import frappe


def execute():
	"""Drop contract_kind column from MPIT Contract (legacy, unused)."""
	if frappe.db.has_column("MPIT Contract", "contract_kind"):
		frappe.db.sql_ddl("ALTER TABLE `tabMPIT Contract` DROP COLUMN `contract_kind`")
		frappe.db.commit()

