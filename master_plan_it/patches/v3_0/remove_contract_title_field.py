"""
FILE: master_plan_it/patches/v3_0/remove_contract_title_field.py
SCOPO: Rimuovere il campo legacy `title` da MPIT Contract dopo l'introduzione della naming series standardizzata.
OUTPUT/SIDE EFFECTS: Drop colonna `title` da `tabMPIT Contract` se esiste.
"""

from __future__ import annotations

import frappe


def execute():
	"""Drop legacy title column from MPIT Contract."""
	if frappe.db.has_column("MPIT Contract", "title"):
		frappe.db.sql_ddl("ALTER TABLE `tabMPIT Contract` DROP COLUMN `title`")
		frappe.db.commit()
