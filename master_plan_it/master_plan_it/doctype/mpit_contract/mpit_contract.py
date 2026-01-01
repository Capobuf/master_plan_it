# MPIT Contract controller: validates contract monetary splits, spread vs rate schedule, naming invariants,
# and keeps renewal/status coherence. Inputs: contract fields (amounts, billing cadence, spread/rate rows, cost_center).
# Output: normalized contract with net/vat/gross, monthly equivalent, and clean status/renewal dates.
# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

from __future__ import annotations

from datetime import timedelta

import frappe
from frappe import _
from frappe.model.document import Document
from frappe.utils import add_months, flt, getdate

from master_plan_it import mpit_user_prefs, tax


class MPITContract(Document):
	def validate(self):
		if not self.cost_center:
			frappe.throw(_("Cost Center is required for contracts."))
		self._compute_vat_split()
		self._validate_spread_vs_rate()
		self._compute_spread_end_date()
		self._validate_rate_schedule()
		self._compute_rate_vat()
		self._compute_monthly_amount()
		self._default_next_renewal_date()
		self._normalize_status()
	
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

	def _compute_monthly_amount(self) -> None:
		"""Compute monthly net equivalent based on billing cadence or spread."""
		if self.current_amount_net is None:
			self.monthly_amount_net = None
			return

		# Rate schedules carry their own monthly amounts; avoid flattening
		if self.rate_schedule:
			self.monthly_amount_net = None
			return

		if self.spread_months:
			months = flt(self.spread_months or 0)
			if months <= 0:
				self.monthly_amount_net = None
				return
			self.monthly_amount_net = flt(flt(self.current_amount_net or 0, 2) / months, 2)
			return

		billing = self.billing_cycle or "Monthly"
		if billing == "Quarterly":
			self.monthly_amount_net = flt((self.current_amount_net or 0) * 4 / 12, 2)
		elif billing == "Annual":
			self.monthly_amount_net = flt((self.current_amount_net or 0) / 12, 2)
		else:
			# Monthly and "Other" default to same value
			self.monthly_amount_net = flt(self.current_amount_net or 0, 2)

	def _validate_spread_vs_rate(self) -> None:
		"""Enforce mutual exclusivity between spread and rate schedule."""
		has_spread = bool(self.spread_months)
		has_rate = bool(self.rate_schedule)

		if has_spread and has_rate:
			frappe.throw(_("Spread and rate schedule cannot both be set."))

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
				frappe.throw(_("Rate row is missing effective_from."))
			if i > 0:
				prev = rows[i - 1]
				if getdate(row.effective_from) <= getdate(prev.effective_from):
					frappe.throw(_("Rate schedule must be strictly increasing by effective_from (no duplicates or overlaps)."))

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
				field_label=_("Rate Amount")
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

	def _normalize_status(self) -> None:
		"""Keep auto-renew contracts in Active (avoid drift to Pending Renewal)."""
		if not self.auto_renew:
			return
		if self.status in ("Pending Renewal", "Draft", None, ""):
			self.status = "Active"
