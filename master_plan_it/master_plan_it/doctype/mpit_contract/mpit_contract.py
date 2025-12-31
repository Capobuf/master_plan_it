# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

from __future__ import annotations

import frappe
from frappe.model.document import Document
from datetime import timedelta

from frappe.utils import add_months, getdate
from master_plan_it import mpit_user_prefs, tax


class MPITContract(Document):
	def validate(self):
		self._compute_vat_split()
		self._validate_spread_vs_rate()
		self._compute_spread_end_date()
		self._validate_rate_schedule()
		self._compute_rate_vat()
		self._default_next_renewal_date()
	
	def _compute_vat_split(self):
		"""Compute net/vat/gross for current_amount field with strict VAT validation."""
		# Get user default VAT rate
		default_vat = mpit_user_prefs.get_default_vat_rate(frappe.session.user)
		
		# Apply default if field is empty
		if self.vat_rate is None and default_vat is not None:
			self.vat_rate = default_vat
		
		# Strict VAT validation
		final_vat_rate = tax.validate_strict_vat(
			self.current_amount,
			self.vat_rate,
			default_vat,
			field_label=frappe._("Current Amount")
		)
		
		# Compute split
		net, vat, gross = tax.split_net_vat_gross(
			self.current_amount,
			final_vat_rate,
			bool(self.current_amount_includes_vat)
		)
		
		self.current_amount_net = net
		self.current_amount_vat = vat
		self.current_amount_gross = gross

	def _validate_spread_vs_rate(self) -> None:
		"""Enforce mutual exclusivity between spread and rate schedule."""
		has_spread = bool(self.spread_months)
		has_rate = bool(self.rate_schedule)

		if has_spread and has_rate:
			frappe.throw("Spread and rate schedule cannot both be set.")

	def _compute_spread_end_date(self) -> None:
		"""Compute spread_end_date if spread is defined."""
		if not self.spread_months or not self.spread_start_date:
			self.spread_end_date = None
			return
		start = getdate(self.spread_start_date)
		months = int(self.spread_months)
		# End of spread = last day of the month that starts spread_months after spread_start_date
		end_month_start = add_months(start, months)
		self.spread_end_date = end_month_start - timedelta(days=1)

	def _validate_rate_schedule(self) -> None:
		"""Validate rate schedule ordering and overlap."""
		if not self.rate_schedule:
			return

		# Sort rows by effective_from
		rows = sorted(self.rate_schedule, key=lambda r: getdate(r.effective_from) if r.effective_from else getdate("1900-01-01"))
		for i, row in enumerate(rows):
			if not row.effective_from:
				frappe.throw("Rate row is missing effective_from.")
			if i > 0:
				prev = rows[i - 1]
				if getdate(row.effective_from) <= getdate(prev.effective_from):
					frappe.throw("Rate schedule must be strictly increasing by effective_from (no duplicates or overlaps).")

	def _compute_rate_vat(self) -> None:
		"""Compute VAT split for rate rows."""
		if not self.rate_schedule:
			return
		default_vat = mpit_user_prefs.get_default_vat_rate(frappe.session.user)
		for row in self.rate_schedule:
			if row.vat_rate is None and default_vat is not None:
				row.vat_rate = default_vat
			final_vat_rate = tax.validate_strict_vat(
				row.amount,
				row.vat_rate,
				default_vat,
				field_label="Rate Amount"
			)
			net, vat, gross = tax.split_net_vat_gross(
				row.amount,
				final_vat_rate,
				bool(row.amount_includes_vat)
			)
			row.amount_net = net
			row.amount_vat = vat
			row.amount_gross = gross

	def _default_next_renewal_date(self) -> None:
		"""Ensure next_renewal_date is present for auto-renew contracts."""
		if not self.auto_renew:
			return
		if self.next_renewal_date:
			return
		if self.end_date:
			self.next_renewal_date = self.end_date
