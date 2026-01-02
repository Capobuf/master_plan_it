"""
FILE: master_plan_it/patches/v3_0/remove_mpit_settings_currency.py
SCOPO: Rimuove il campo currency deprecato da MPIT Settings.
INPUT: Nessuno (patch eseguita automaticamente da bench migrate).
OUTPUT/SIDE EFFECTS: Drop colonna currency da tabMPIT Settings se esiste.
NOTE: Currency is now managed at Frappe site level (Global Defaults / System Settings).
"""
import frappe


def execute():
    """Remove deprecated currency field from MPIT Settings."""
    if not frappe.db.table_exists("tabMPIT Settings"):
        return
    if frappe.db.has_column("MPIT Settings", "currency"):
        frappe.db.sql_ddl("ALTER TABLE `tabMPIT Settings` DROP COLUMN `currency`")
        frappe.db.commit()
