# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

from __future__ import annotations

import frappe
from frappe.model.document import Document
from master_plan_it import annualization, mpit_user_prefs, tax


class MPITBaselineExpense(Document):
	def validate(self):
		self._compute_vat_split()
		self._compute_annualization()
	
	def _compute_vat_split(self):
		"""Compute net/vat/gross for amount field with strict VAT validation."""
		# Get user default VAT rate
		default_vat = mpit_user_prefs.get_default_vat_rate(frappe.session.user)
		default_includes = mpit_user_prefs.get_default_includes_vat(frappe.session.user)
		
		# Apply default if field is empty
		if self.vat_rate is None and default_vat is not None:
			self.vat_rate = default_vat
		if not self.amount_includes_vat and default_includes:
			self.amount_includes_vat = 1
		
		# Strict VAT validation
		final_vat_rate = tax.validate_strict_vat(
			self.amount,
			self.vat_rate,
			default_vat,
			field_label="Amount"
		)
		
		# Compute split
		net, vat, gross = tax.split_net_vat_gross(
			self.amount,
			final_vat_rate,
			bool(self.amount_includes_vat)
		)
		
		self.amount_net = net
		self.amount_vat = vat
		self.amount_gross = gross
	
	def _compute_annualization(self):
		"""Compute annual amounts based on recurrence rule and overlap with fiscal year."""
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
		else:
			# No period specified: treat as full year overlap
			overlap_months_count = 12
		
		# Rule A: Block save if zero overlap
		if self.period_start_date and self.period_end_date and overlap_months_count == 0:
			frappe.throw(
				frappe._(
					"Baseline Expense period ({0} to {1}) has zero overlap with fiscal year {2}. "
					"Cannot save baseline expense with no temporal overlap."
				).format(self.period_start_date, self.period_end_date, self.year)
			)
		
		# Calculate annualized amounts
		annual_net = annualization.annualize(
			self.amount_net,
			self.recurrence_rule or "None",
			self.custom_period_months,
			overlap_months_count
		)
		
		# Annual VAT and gross
		if self.vat_rate and annual_net:
			vat_rate_decimal = self.vat_rate / 100.0
			annual_vat = annual_net * vat_rate_decimal
			annual_gross = annual_net + annual_vat
		else:
			annual_vat = 0.0
			annual_gross = annual_net
		
		self.annual_net = annual_net
		self.annual_vat = annual_vat
		self.annual_gross = annual_gross

