"""
FILE: master_plan_it/patches/v3_0/remove_contract_spread_rate_fields.py
SCOPO: Rimuove i campi spread/rate deprecati da MPIT Contract (v3 cleanup).
INPUT: Nessuno (patch eseguita automaticamente da bench migrate).
OUTPUT/SIDE EFFECTS: Drop colonne spread_months, spread_start_date, spread_end_date dal DB;
                     svuota la child table MPIT Contract Rate.
"""
import frappe


def execute():
    """Remove deprecated spread/rate fields from MPIT Contract."""
    # Drop columns if they exist
    columns_to_drop = ["spread_months", "spread_start_date", "spread_end_date"]
    
    for col in columns_to_drop:
        if frappe.db.has_column("MPIT Contract", col):
            frappe.db.sql_ddl(f"ALTER TABLE `tabMPIT Contract` DROP COLUMN `{col}`")
    
    # Clear rate_schedule child table data
    if frappe.db.table_exists("tabMPIT Contract Rate"):
        frappe.db.delete("MPIT Contract Rate")
    
    frappe.db.commit()
