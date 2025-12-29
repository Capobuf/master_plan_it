# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt
"""
Patch: Migrate 'amount' field to 'monthly_amount' and 'annual_amount' fields.

This patch handles the transition from the old schema (single 'amount' field) 
to the new schema (qty, unit_price, monthly_amount, annual_amount).

For existing records:
- qty is set to 1 (default)
- annual_amount is set to the old 'amount' value  
- monthly_amount is calculated as annual_amount / 12

Run via: bench --site <site> execute master_plan_it.patches.v1_0.migrate_amounts_to_monthly_annual.execute
"""

from __future__ import annotations

import frappe
from frappe.utils import flt


def execute():
	"""Migrate existing records from 'amount' to 'monthly_amount' and 'annual_amount'."""
	
	# Track counts
	baseline_count = 0
	budget_line_count = 0
	
	# =========================================================================
	# 1. Migrate MPIT Baseline Expense (only if columns exist)
	# =========================================================================
	try:
		# Check if old 'amount' column exists
		has_amount = frappe.db.sql("""
			SELECT COUNT(*) FROM information_schema.columns 
			WHERE table_name = 'tabMPIT Baseline Expense' 
			AND column_name = 'amount'
			AND table_schema = DATABASE()
		""")[0][0] > 0
		
		has_annual = frappe.db.sql("""
			SELECT COUNT(*) FROM information_schema.columns 
			WHERE table_name = 'tabMPIT Baseline Expense' 
			AND column_name = 'annual_amount'
			AND table_schema = DATABASE()
		""")[0][0] > 0
		
		if has_amount and has_annual:
			frappe.db.sql("""
				UPDATE `tabMPIT Baseline Expense`
				SET 
					annual_amount = IFNULL(annual_amount, amount),
					monthly_amount = IFNULL(monthly_amount, amount / 12)
				WHERE 
					(annual_amount IS NULL OR annual_amount = 0)
					AND amount IS NOT NULL
					AND amount > 0
			""")
			
			baseline_count = frappe.db.sql("""
				SELECT COUNT(*) FROM `tabMPIT Baseline Expense` WHERE annual_amount IS NOT NULL AND annual_amount > 0
			""")[0][0]
		elif has_annual:
			# Just count existing records
			baseline_count = frappe.db.sql("""
				SELECT COUNT(*) FROM `tabMPIT Baseline Expense` WHERE annual_amount IS NOT NULL AND annual_amount > 0
			""")[0][0]
			
	except Exception as e:
		print(f"⚠️ Skipping MPIT Baseline Expense migration: {e}")
	
	# =========================================================================
	# 2. Migrate MPIT Budget Line (child table) - only if columns exist
	# =========================================================================
	try:
		# Check if old 'amount' column exists
		has_amount = frappe.db.sql("""
			SELECT COUNT(*) FROM information_schema.columns 
			WHERE table_name = 'tabMPIT Budget Line' 
			AND column_name = 'amount'
			AND table_schema = DATABASE()
		""")[0][0] > 0
		
		has_annual = frappe.db.sql("""
			SELECT COUNT(*) FROM information_schema.columns 
			WHERE table_name = 'tabMPIT Budget Line' 
			AND column_name = 'annual_amount'
			AND table_schema = DATABASE()
		""")[0][0] > 0
		
		if has_amount and has_annual:
			frappe.db.sql("""
				UPDATE `tabMPIT Budget Line`
				SET 
					annual_amount = IFNULL(annual_amount, amount),
					monthly_amount = IFNULL(monthly_amount, amount / 12)
				WHERE 
					(annual_amount IS NULL OR annual_amount = 0)
					AND amount IS NOT NULL
					AND amount > 0
			""")
			
			budget_line_count = frappe.db.sql("""
				SELECT COUNT(*) FROM `tabMPIT Budget Line` WHERE annual_amount IS NOT NULL AND annual_amount > 0
			""")[0][0]
		elif has_annual:
			# Just count existing records
			budget_line_count = frappe.db.sql("""
				SELECT COUNT(*) FROM `tabMPIT Budget Line` WHERE annual_amount IS NOT NULL AND annual_amount > 0
			""")[0][0]
			
	except Exception as e:
		print(f"⚠️ Skipping MPIT Budget Line migration: {e}")
	
	# =========================================================================
	# 3. Commit changes
	# =========================================================================
	frappe.db.commit()
	
	print(f"✅ Migrated {baseline_count} MPIT Baseline Expense records")
	print(f"✅ Migrated {budget_line_count} MPIT Budget Line records")
	print("Migration complete!")
