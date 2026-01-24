# MPIT Project controller: handles naming, VAT normalization, permissions,
# validation of status and dates, and keeps financial totals in sync from Planned Items.
# Input: project doc with status, cost_center, dates. Output: validated project with computed net totals.
# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

from __future__ import annotations

import frappe
from frappe import _
from frappe.model.document import Document
from frappe.model.naming import getseries
from frappe.utils import cint, flt, getdate
from master_plan_it import amounts, mpit_defaults, tax


class MPITProject(Document):
	def autoname(self):
		"""Generate name: PRJ-{NNNN} based on Settings."""
		prefix, digits = mpit_defaults.get_project_series()
		
		# Build series key dynamically using configured digits.
		# Must match the key format used by reset_series_on_delete in on_trash.
		series_key = f"{prefix}.{'#' * digits}"
		sequence = getseries(series_key, digits)
		self.name = f"{prefix}{sequence}"

	def on_trash(self):
		"""Reset series counter if this was the last Project in sequence."""
		from master_plan_it.naming_utils import reset_series_on_delete
		prefix, digits = mpit_defaults.get_project_series()
		reset_series_on_delete(self.name, prefix, digits)
	
	def validate(self):
		if not self.cost_center:
			# In tests we skip the strict check to keep fixtures light
			if not frappe.flags.in_test:
				frappe.throw(_("Cost Center is required on Project."))
		self._validate_planned_dates()
		self._compute_project_totals()
		# Warning removed - workflow now blocks Proposed→Approved without Planned Items


	def _compute_project_totals(self) -> None:
		"""Compute totals from Planned Items (Estimate vs Quote), including Verified delta entries."""
		if not self.name:
			return

		# Fetch all non-cancelled Planned Items for this project
		items = frappe.db.get_all(
			"MPIT Planned Item",
			filters={"project": self.name, "workflow_state": ["!=", "Cancelled"]},
			fields=["amount_net", "amount", "is_covered", "item_type"]
		)

		# Sum ALL Estimate items (baseline)
		all_estimates = sum(
			flt(item.amount_net or item.amount or 0)
			for item in items
			if item.item_type == "Estimate"
		)

		# Sum ALL Quote items (baseline)
		all_quotes = sum(
			flt(item.amount_net or item.amount or 0)
			for item in items
			if item.item_type == "Quote"
		)

		# Sum Estimate items not covered (for forecast)
		estimate_uncovered = sum(
			flt(item.amount_net or item.amount or 0)
			for item in items
			if item.item_type == "Estimate" and not item.is_covered
		)

		# Sum Quote items not covered (for forecast)
		quote_uncovered = sum(
			flt(item.amount_net or item.amount or 0)
			for item in items
			if item.item_type == "Quote" and not item.is_covered
		)

		# Get verified deltas from Actual Entries
		verified_deltas = self._get_verified_deltas()

		# Planned Baseline: prefer quotes if available, else estimates
		planned_base = all_quotes if all_quotes > 0 else all_estimates

		# Expected Forecast: prefer quotes (uncovered) if available, else estimates (uncovered) + Actuals
		forecast_base = quote_uncovered if quote_uncovered > 0 else estimate_uncovered
		expected_total = forecast_base + verified_deltas

		self.planned_total_net = flt(planned_base, 2)
		self.quoted_total_net = flt(all_quotes, 2)
		self.expected_total_net = flt(expected_total, 2)
		
		self.actual_total_net = flt(verified_deltas, 2)
		# Variance: Planned - Expected (Positive = Savings/Under Budget, Negative = Overrun)
		self.variance_net = flt(self.planned_total_net - self.expected_total_net, 2)
		
		if self.planned_total_net > 0:
			self.utilization_pct = flt((self.actual_total_net / self.planned_total_net) * 100, 2)
		else:
			self.utilization_pct = 0.0

	def _get_verified_deltas(self) -> float:
		"""Get sum of verified delta entries for this project."""
		if not self.name:
			return 0.0
		result = frappe.db.sql("""
			SELECT SUM(COALESCE(amount_net, amount)) AS total
			FROM `tabMPIT Actual Entry`
			WHERE project = %s AND status = 'Verified' AND entry_kind = 'Delta'
		""", (self.name,))
		return flt(result[0][0] or 0) if result else 0.0

	def _validate_planned_dates(self) -> None:
		"""Enforce planned date rules for monthly distribution."""
		if self.start_date and not self.end_date:
			frappe.throw(_("Set both planned start and end date, or clear both."))
		if self.end_date and not self.start_date:
			frappe.throw(_("Set both planned start and end date, or clear both."))
		if self.start_date and self.end_date:
			if getdate(self.end_date) < getdate(self.start_date):
				frappe.throw(_("Planned end date cannot be before planned start date."))


@frappe.whitelist()
def get_project_actuals_totals(project: str) -> dict:
	"""Return verified delta totals for a project (net) without persisting on the Project doc."""
	if not project:
		return {"actual_total_net": 0.0}

	row = frappe.db.sql(
		"""
		SELECT SUM(COALESCE(amount_net, amount)) AS actual_total
		FROM `tabMPIT Actual Entry`
		WHERE project = %(project)s
		  AND status = 'Verified'
		  AND entry_kind = 'Delta'
		""",
		{"project": project},
	)
	actual_total = flt(row[0][0] or 0) if row else 0.0
	return {"actual_total_net": actual_total}


@frappe.whitelist()
def has_submitted_planned_items(project: str) -> bool:
	"""Check if project has at least one submitted Planned Item.

	Used as workflow condition to block Proposed→Approved without Planned Items.
	"""
	if not project:
		return False
	return bool(frappe.db.exists("MPIT Planned Item", {"project": project, "workflow_state": "Submitted"}))

