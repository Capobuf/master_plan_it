"""
Tests for MPIT reports.

Run via Docker:
    docker exec -it master_plan_it-frappe-1 bench --site $SITE_NAME run-tests \
        --module master_plan_it.tests.test_reports
"""

from __future__ import annotations

import frappe
from frappe.tests.utils import FrappeTestCase


class TestMpitBudgetDiffReport(FrappeTestCase):
	"""Test the Budget Diff report returns valid structure."""

	def test_execute_returns_columns_and_data(self):
		from master_plan_it.master_plan_it.report.mpit_budget_diff.mpit_budget_diff import execute

		budgets = frappe.get_all("MPIT Budget", limit=2, pluck="name")
		if len(budgets) < 2:
			self.skipTest("Need at least 2 budgets for diff report")

		columns, data, *_ = execute({"budget_a": budgets[0], "budget_b": budgets[1]})

		self.assertIsInstance(columns, list)
		self.assertTrue(len(columns) > 0)
		self.assertTrue(all("fieldname" in c for c in columns))


class TestMpitRenewalsWindowReport(FrappeTestCase):
	"""Test the Renewals Window report returns valid structure."""

	def test_execute_returns_columns_and_data(self):
		from master_plan_it.master_plan_it.report.mpit_renewals_window.mpit_renewals_window import (
			execute,
		)

		columns, data, *_ = execute({})

		self.assertIsInstance(columns, list)
		self.assertTrue(len(columns) > 0)
		self.assertTrue(all("fieldname" in c for c in columns))

	def test_execute_with_filters(self):
		from master_plan_it.master_plan_it.report.mpit_renewals_window.mpit_renewals_window import (
			execute,
		)

		columns, data, *_ = execute({"days": 30, "auto_renew_only": 1})

		self.assertIsInstance(columns, list)
		self.assertIsInstance(data, list)
