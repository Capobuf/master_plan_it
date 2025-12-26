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
		"""Find the MPIT Year covering a date, preferring date ranges then exact year name."""
		res = frappe.db.sql(
			"""
			SELECT name
			FROM `tabMPIT Year`
			WHERE (start_date IS NULL OR start_date <= %(date)s)
			  AND (end_date IS NULL OR end_date >= %(date)s)
			ORDER BY start_date DESC, name DESC
			LIMIT 1
			""",
			{"date": posting_date},
		)
		if res:
			return res[0][0]

		# Fallback to exact year match (name or field)
		for candidate in (str(posting_date.year), posting_date.year):
			found = frappe.db.exists("MPIT Year", candidate) or frappe.db.exists("MPIT Year", {"year": posting_date.year})
			if found:
				return found if isinstance(found, str) else str(found)

		return None
