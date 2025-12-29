# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

from __future__ import annotations

import frappe
from frappe.model.document import Document
from frappe.utils import flt
from master_plan_it import amounts, annualization, mpit_user_prefs


class MPITBaselineExpense(Document):
	def validate(self):
		self._compute_amounts()
	
	def _compute_amounts(self):
		"""Compute all amounts using bidirectional logic from amounts module."""
		# Get user default VAT rate
		default_vat = mpit_user_prefs.get_default_vat_rate(frappe.session.user)
		
		# Apply default if field is empty
		if self.vat_rate is None and default_vat is not None:
			self.vat_rate = default_vat
		
		# Validate recurrence rule consistency
		annualization.validate_recurrence_rule(
			self.recurrence_rule,
			self.custom_period_months
		)
		
		# Get fiscal year bounds
		year_start, year_end = annualization.get_year_bounds(self.year)
		
		# Calculate overlap months
		if self.period_start_date and self.period_end_date:
			overlap_months_count = annualization.overlap_months(
				self.period_start_date,
				self.period_end_date,
				year_start,
				year_end
			)
			
			# Rule A: Block save if zero overlap
			if overlap_months_count == 0:
				frappe.throw(
					frappe._(
						"Baseline Expense period ({0} to {1}) has zero overlap with fiscal year {2}. Cannot save baseline expense with no temporal overlap."
					).format(self.period_start_date, self.period_end_date, self.year)
				)
		else:
			# No period specified: treat as full year overlap
			overlap_months_count = 12
		
		# Use unified amounts module for all calculations
		result = amounts.compute_line_amounts(
			qty=flt(self.qty) or 1,
			unit_price=flt(self.unit_price),
			monthly_amount=flt(self.monthly_amount),
			annual_amount=flt(self.annual_amount),
			recurrence_rule=self.recurrence_rule or "Monthly",
			custom_period_months=self.custom_period_months,
			vat_rate=flt(self.vat_rate),
			amount_includes_vat=bool(self.amount_includes_vat),
			overlap_months=overlap_months_count
		)
		
		# Update document with calculated values
		self.monthly_amount = result["monthly_amount"]
		self.annual_amount = result["annual_amount"]
		self.amount_net = result["amount_net"]
		self.amount_vat = result["amount_vat"]
		self.amount_gross = result["amount_gross"]
		self.annual_net = result["annual_net"]
		self.annual_vat = result["annual_vat"]
		self.annual_gross = result["annual_gross"]
