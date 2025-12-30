# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

from __future__ import annotations

import frappe
from frappe import _
from frappe.model.document import Document
from frappe.utils import getdate
from master_plan_it import mpit_user_prefs, tax


class MPITActualEntry(Document):
	def validate(self):
		self._set_year_from_posting_date()
		self._compute_vat_split()
		self._autofill_cost_center()

	def _autofill_cost_center(self) -> None:
		"""Copy cost center from contract or project if missing."""
		if self.cost_center:
			return
		if self.contract:
			self.cost_center = frappe.db.get_value("MPIT Contract", self.contract, "cost_center")
		if not self.cost_center and self.project:
			self.cost_center = frappe.db.get_value("MPIT Project", self.project, "cost_center")
	
	def _compute_vat_split(self):
		"""Compute net/vat/gross for amount field with strict VAT validation."""
		# Get user default VAT rate
		default_vat = mpit_user_prefs.get_default_vat_rate(frappe.session.user)
		
		# Apply default if field is empty
		if self.vat_rate is None and default_vat is not None:
			self.vat_rate = default_vat
		
		# Strict VAT validation
		final_vat_rate = tax.validate_strict_vat(
			self.amount,
			self.vat_rate,
			default_vat,
			field_label=_("Amount")
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

	def _set_year_from_posting_date(self) -> None:
		"""Derive MPIT Year from posting_date (idempotent)."""
		if not self.posting_date:
			frappe.throw(_("Posting Date is required to derive MPIT Year."))

		posting = getdate(self.posting_date)
		year_name = self._lookup_year_for_date(posting)

		if not year_name:
			frappe.throw(
				_("No MPIT Year covers posting date {0}. Create year {1} or set start/end dates that include the date.")
				.format(posting.isoformat(), posting.year)
			)

		# Always override to keep data consistent with the posting date.
		self.year = year_name

	def _lookup_year_for_date(self, posting_date) -> str | None:
		"""Find the MPIT Year covering a date using strict date ranges."""
		# Since start_date and end_date are mandatory in MPIT Year, we can rely on them.
		res = frappe.db.sql(
			"""
			SELECT name
			FROM `tabMPIT Year`
			WHERE start_date <= %(date)s AND end_date >= %(date)s
			ORDER BY start_date DESC
			LIMIT 1
			""",
			{"date": posting_date},
		)
		if res:
			return res[0][0]

		return None
