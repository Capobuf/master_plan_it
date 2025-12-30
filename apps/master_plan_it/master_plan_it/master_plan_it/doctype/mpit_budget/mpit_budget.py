# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

from __future__ import annotations

import frappe
from frappe import _
from frappe.model.document import Document
from frappe.model.naming import getseries
from frappe.utils import flt
from master_plan_it import amounts, annualization, mpit_user_prefs


class MPITBudget(Document):
	def autoname(self):
		"""Generate name: BUD-{year}-{NN} based on Budget.year and user preferences."""
		from master_plan_it import mpit_user_prefs
		
		# Budget.year is mandatory for naming
		if not self.year:
			frappe.throw(_("Year is required to generate Budget name"))
		
		# Get user preferences for prefix and digits
		prefix, digits, middle = mpit_user_prefs.get_budget_series(user=frappe.session.user, year=self.year)
		
		# Generate name: BUD-2025-01, BUD-2025-02, etc.
		# getseries returns only the number part, we need to add prefix + middle
		series_key = f"{prefix}{middle}.####"
		sequence = getseries(series_key, digits)
		self.name = f"{prefix}{middle}{sequence}"
	
	def validate(self):
		self._enforce_status_invariants()
		self._compute_lines_amounts()
		self._compute_totals()
	
	def _compute_lines_amounts(self):
		"""Compute all amounts for Budget Lines using bidirectional logic."""
		# Get user defaults once
		default_vat = mpit_user_prefs.get_default_vat_rate(frappe.session.user)
		
		# Get fiscal year bounds from year field
		year_start, year_end = annualization.get_year_bounds(self.year)
		
		for line in self.lines:
			# Apply VAT rate default if not specified
			if line.vat_rate is None and default_vat is not None:
				line.vat_rate = default_vat
			
			# Validate recurrence rule consistency
			annualization.validate_recurrence_rule(line.recurrence_rule)
			
			# Calculate overlap months for annualization
			if line.period_start_date and line.period_end_date:
				overlap_months_count = annualization.overlap_months(
					line.period_start_date,
					line.period_end_date,
					year_start,
					year_end
				)
				
				# Rule A: Block save if zero overlap
				if overlap_months_count == 0:
					frappe.throw(
						frappe._(
							"Line {0}: Period ({1} to {2}) has zero overlap with fiscal year {3}. Cannot save budget line with no temporal overlap."
						).format(line.idx, line.period_start_date, line.period_end_date, self.year)
					)
			else:
				# No period specified: treat as full year overlap
				overlap_months_count = 12
			
			# Use unified amounts module for all calculations
			result = amounts.compute_line_amounts(
				qty=flt(line.qty) or 1,
				unit_price=flt(line.unit_price),
				monthly_amount=flt(line.monthly_amount),
				annual_amount=flt(line.annual_amount),
				recurrence_rule=line.recurrence_rule or "Monthly",
				vat_rate=flt(line.vat_rate),
				amount_includes_vat=bool(line.amount_includes_vat),
				overlap_months=overlap_months_count
			)
			
			# Update line with calculated values
			line.monthly_amount = result["monthly_amount"]
			line.annual_amount = result["annual_amount"]
			line.amount_net = result["amount_net"]
			line.amount_vat = result["amount_vat"]
			line.amount_gross = result["amount_gross"]
			line.annual_net = result["annual_net"]
			line.annual_vat = result["annual_vat"]
			line.annual_gross = result["annual_gross"]

	def _enforce_status_invariants(self) -> None:
		"""Keep workflow_state aligned with docstatus now that it is an editable status label."""
		if not self.workflow_state:
			self.workflow_state = "Draft"

		if self.docstatus == 0 and self.workflow_state == "Approved":
			frappe.throw(_("Draft Budget cannot be set to Approved. Submit the document to approve it."))

		if self.docstatus == 1 and self.workflow_state != "Approved":
			self.workflow_state = "Approved"

	def on_submit(self):
		# Ensure submitted budgets always reflect Approved status
		if self.workflow_state != "Approved":
			self.workflow_state = "Approved"
			self.db_set("workflow_state", "Approved")

	def _compute_totals(self):
		total_monthly = 0.0
		total_annual = 0.0
		total_net = 0.0
		total_vat = 0.0
		total_gross = 0.0

		for line in (self.lines or []):
			total_monthly += flt(getattr(line, "monthly_amount", 0) or 0, 2)
			total_annual += flt(getattr(line, "annual_amount", 0) or 0, 2)
			total_net += flt(getattr(line, "annual_net", 0) or 0, 2)
			total_vat += flt(getattr(line, "annual_vat", 0) or 0, 2)
			total_gross += flt(getattr(line, "annual_gross", 0) or 0, 2)

		self.total_amount_monthly = flt(total_monthly, 2)
		self.total_amount_annual = flt(total_annual, 2)
		self.total_amount_net = flt(total_net, 2)
		self.total_amount_vat = flt(total_vat, 2)
		self.total_amount_gross = flt(total_gross, 2)


def update_budget_totals(budget_name: str) -> None:
	"""Recompute and persist totals for an existing budget without client scripts."""
	if not budget_name:
		return

	budget = frappe.get_doc("MPIT Budget", budget_name)
	budget._compute_totals()

	totals = {
		"total_amount_monthly": flt(budget.total_amount_monthly, 2),
		"total_amount_annual": flt(budget.total_amount_annual, 2),
		"total_amount_net": flt(budget.total_amount_net, 2),
		"total_amount_vat": flt(budget.total_amount_vat, 2),
		"total_amount_gross": flt(budget.total_amount_gross, 2),
	}

	frappe.db.set_value("MPIT Budget", budget_name, totals)
