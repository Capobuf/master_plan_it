# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

"""
Backfill VAT fields for existing records created before EPIC MPIT-E01 implementation.

Sets default values for all Currency fields that now have VAT normalization:
- vat_rate = 0 (zero-rated, not null)
- includes_vat = 0 (amount is net by default)
- _net = amount (original amount is treated as net)
- _vat = 0 (no VAT for historical records)
- _gross = amount (gross equals net when VAT is 0)

Affected doctypes (7 total):
- MPIT Baseline Expense (parent, amount field)
- MPIT Actual Entry (parent, amount field)
- MPIT Contract (parent, current_amount field)
- MPIT Budget Line (child, amount field)
- MPIT Amendment Line (child, delta_amount field)
- MPIT Project Allocation (child, planned_amount field)
- MPIT Project Quote (child, amount field)

This patch is idempotent - only updates records where vat_rate is NULL.
"""

import frappe


def execute():
	"""Backfill VAT fields for all existing records."""
	
	frappe.reload_doc("master_plan_it", "doctype", "mpit_baseline_expense", force=True)
	frappe.reload_doc("master_plan_it", "doctype", "mpit_actual_entry", force=True)
	frappe.reload_doc("master_plan_it", "doctype", "mpit_contract", force=True)
	frappe.reload_doc("master_plan_it", "doctype", "mpit_budget_line", force=True)
	frappe.reload_doc("master_plan_it", "doctype", "mpit_amendment_line", force=True)
	frappe.reload_doc("master_plan_it", "doctype", "mpit_project_allocation", force=True)
	frappe.reload_doc("master_plan_it", "doctype", "mpit_project_quote", force=True)
	
	# Parent doctypes with single Currency field
	backfill_parent_doctype("MPIT Baseline Expense", "amount")
	backfill_parent_doctype("MPIT Actual Entry", "amount")
	backfill_parent_doctype("MPIT Contract", "current_amount")
	
	# Child table doctypes
	backfill_child_doctype("MPIT Budget Line", "amount")
	backfill_child_doctype("MPIT Amendment Line", "delta_amount")
	backfill_child_doctype("MPIT Project Allocation", "planned_amount")
	backfill_child_doctype("MPIT Project Quote", "amount")
	
	frappe.db.commit()


def backfill_parent_doctype(doctype, amount_field):
	"""
	Backfill VAT fields for a parent doctype with single Currency field.
	
	Args:
		doctype: Name of the doctype (e.g., "MPIT Baseline Expense")
		amount_field: Name of the Currency field (e.g., "amount")
	"""
	table_name = f"tab{doctype}"
	includes_vat_field = f"{amount_field}_includes_vat"
	net_field = f"{amount_field}_net"
	vat_field = f"{amount_field}_vat"
	gross_field = f"{amount_field}_gross"
	
	# Count records that need backfill
	count = frappe.db.sql(f"""
		SELECT COUNT(*) 
		FROM `{table_name}` 
		WHERE vat_rate IS NULL
	""")[0][0]
	
	if count == 0:
		print(f"[{doctype}] No records to backfill (all have vat_rate)")
		return
	
	print(f"[{doctype}] Backfilling {count} records...")
	
	# Update in single query for performance
	# Idempotent: only update WHERE vat_rate IS NULL
	frappe.db.sql(f"""
		UPDATE `{table_name}`
		SET 
			vat_rate = 0,
			{includes_vat_field} = 0,
			{net_field} = {amount_field},
			{vat_field} = 0,
			{gross_field} = {amount_field}
		WHERE vat_rate IS NULL
	""")
	
	print(f"[{doctype}] ✓ Updated {count} records")


def backfill_child_doctype(doctype, amount_field):
	"""
	Backfill VAT fields for a child table doctype.
	
	Args:
		doctype: Name of the child doctype (e.g., "MPIT Budget Line")
		amount_field: Name of the Currency field (e.g., "amount")
	"""
	table_name = f"tab{doctype}"
	includes_vat_field = f"{amount_field}_includes_vat"
	net_field = f"{amount_field}_net"
	vat_field = f"{amount_field}_vat"
	gross_field = f"{amount_field}_gross"
	
	# Count records that need backfill
	count = frappe.db.sql(f"""
		SELECT COUNT(*) 
		FROM `{table_name}` 
		WHERE vat_rate IS NULL
	""")[0][0]
	
	if count == 0:
		print(f"[{doctype}] No records to backfill (all have vat_rate)")
		return
	
	print(f"[{doctype}] Backfilling {count} records...")
	
	# Update in single query for performance
	# Idempotent: only update WHERE vat_rate IS NULL
	frappe.db.sql(f"""
		UPDATE `{table_name}`
		SET 
			vat_rate = 0,
			{includes_vat_field} = 0,
			{net_field} = {amount_field},
			{vat_field} = 0,
			{gross_field} = {amount_field}
		WHERE vat_rate IS NULL
	""")
	
	print(f"[{doctype}] ✓ Updated {count} records")
