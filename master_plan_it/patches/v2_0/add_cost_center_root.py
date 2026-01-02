# Copyright (c) 2025, DOT and contributors
"""
Patch: seed root Cost Center for existing sites.
"""

from __future__ import annotations

import frappe


def execute():
	if not frappe.db.exists("DocType", "MPIT Cost Center"):
		# DocType not synced yet; patch will be re-run after sync.
		return

	if frappe.db.exists("MPIT Cost Center", "All Cost Centers"):
		return

	doc = frappe.get_doc({
		"doctype": "MPIT Cost Center",
		"cost_center_name": "All Cost Centers",
		"is_group": 1,
	})
	doc.insert(ignore_permissions=True)
