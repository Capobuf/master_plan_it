# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

from __future__ import annotations

import frappe
from frappe import _
from frappe.model.document import Document
from frappe.model.naming import getseries
from frappe.utils import flt
from master_plan_it import annualization, mpit_user_prefs, tax


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
		self._compute_lines_vat_split()
		self._compute_lines_annualization()
		self._compute_totals()
	
	def _compute_lines_vat_split(self):
		"""Compute net/vat/gross for all Budget Lines with strict VAT validation."""
		# Get user defaults once
		default_vat = mpit_user_prefs.get_default_vat_rate(frappe.session.user)
		default_includes = mpit_user_prefs.get_default_includes_vat(frappe.session.user)
		
		for line in self.lines:
			# Skip if no amount is provided
			if not line.amount:
				# Clear calculated fields if no amount
				line.amount_net = 0.0
				line.amount_vat = 0.0
				line.amount_gross = 0.0
				continue
			
			# Use 'amount' as source and calculate net/vat/gross
			# Apply VAT rate default if not specified
			if line.vat_rate is None and default_vat is not None:
				line.vat_rate = default_vat
			
			# Apply includes_vat default if not specified
			if line.amount_includes_vat is None and default_includes:
				line.amount_includes_vat = 1
			
			# Strict VAT validation
			final_vat_rate = tax.validate_strict_vat(
				line.amount,
				line.vat_rate,
				default_vat,
				field_label=f"Line {line.idx} Amount"
			)
			
			# Compute split
			net, vat, gross = tax.split_net_vat_gross(
				line.amount,
				final_vat_rate,
				bool(line.amount_includes_vat)
			)
			
			line.amount_net = net
			line.amount_vat = vat
			line.amount_gross = gross
	
	def _compute_lines_annualization(self):
		"""Compute annual amounts for all Budget Lines based on recurrence rules."""
		# Get fiscal year bounds from year field
		year_start, year_end = annualization.get_year_bounds(self.year)
		
		for line in self.lines:
			# Validate recurrence rule consistency
			annualization.validate_recurrence_rule(
				line.recurrence_rule,
				line.custom_period_months
			)
			
			# Calculate overlap months
			if line.period_start_date and line.period_end_date:
				overlap_months_count = annualization.overlap_months(
					line.period_start_date,
					line.period_end_date,
					year_start,
					year_end
				)
			else:
				# No period specified: treat as full year overlap
				overlap_months_count = 12
			
			# Rule A: Block save if zero overlap
			if line.period_start_date and line.period_end_date and overlap_months_count == 0:
				frappe.throw(
					frappe._(
						"Line {0}: Period ({1} to {2}) has zero overlap with fiscal year {3}. "
						"Cannot save budget line with no temporal overlap."
					).format(line.idx, line.period_start_date, line.period_end_date, self.fiscal_year)
				)
			
			# Calculate annualized amounts
			annual_net = annualization.annualize(
				line.amount_net,
				line.recurrence_rule or "None",
				line.custom_period_months,
				overlap_months_count
			)
			
			# Annual VAT and gross
			if line.vat_rate and annual_net:
				vat_rate_decimal = line.vat_rate / 100.0
				annual_vat = annual_net * vat_rate_decimal
				annual_gross = annual_net + annual_vat
			else:
				annual_vat = 0.0
				annual_gross = annual_net
			
			line.annual_net = annual_net
			line.annual_vat = annual_vat
			line.annual_gross = annual_gross

	def _compute_totals(self):
		total_input = 0.0
		total_net = 0.0
		total_vat = 0.0
		total_gross = 0.0

		for line in (self.lines or []):
			total_input += flt(getattr(line, "amount", 0) or 0, 2)
			total_net += flt(getattr(line, "amount_net", 0) or 0, 2)
			total_vat += flt(getattr(line, "amount_vat", 0) or 0, 2)
			total_gross += flt(getattr(line, "amount_gross", 0) or 0, 2)

		self.total_amount_input = flt(total_input, 2)
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
		"total_amount_input": flt(budget.total_amount_input, 2),
		"total_amount_net": flt(budget.total_amount_net, 2),
		"total_amount_vat": flt(budget.total_amount_vat, 2),
		"total_amount_gross": flt(budget.total_amount_gross, 2),
	}

	frappe.db.set_value("MPIT Budget", budget_name, totals)

