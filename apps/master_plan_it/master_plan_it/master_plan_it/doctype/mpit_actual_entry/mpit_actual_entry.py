# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

from __future__ import annotations

import frappe
from frappe import _
from frappe.model.document import Document
from frappe.utils import getdate


class MPITActualEntry(Document):
	def validate(self):
		self._set_year_from_posting_date()

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
