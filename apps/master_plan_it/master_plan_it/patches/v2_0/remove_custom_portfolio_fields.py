# Copyright (c) 2025, DOT and contributors
"""
Patch: remove legacy custom recurrence/portfolio fields.
"""

from __future__ import annotations

import frappe


def execute():
	"""Drop legacy columns not used in V2."""
	# MPIT Budget Line: custom_period_months, is_portfolio_bucket
	for column in ("custom_period_months", "is_portfolio_bucket"):
		frappe.db.sql(f"ALTER TABLE `tabMPIT Budget Line` DROP COLUMN IF EXISTS `{column}`")

	# MPIT Settings: portfolio_warning_threshold_pct
	frappe.db.sql(
		"ALTER TABLE `tabMPIT Settings` DROP COLUMN IF EXISTS `portfolio_warning_threshold_pct`"
	)
