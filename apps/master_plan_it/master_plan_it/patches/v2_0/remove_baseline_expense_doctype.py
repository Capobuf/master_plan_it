# Copyright (c) 2025, DOT and contributors
"""
Patch: remove legacy MPIT Baseline Expense DocType and table.

The V2 model drops Baseline Expense entirely; this patch cleans existing sites.
"""

from __future__ import annotations

import frappe


def execute():
	"""Drop Baseline Expense DocType and table if they still exist."""
	doctype = "MPIT Baseline Expense"

	if frappe.db.exists("DocType", doctype):
		# force delete to remove DocType + child metadata
		frappe.delete_doc("DocType", doctype, ignore_missing=True, force=1)

	# Drop table to avoid stray schema
	frappe.db.sql_ddl(f"DROP TABLE IF EXISTS `tab{doctype}`")
