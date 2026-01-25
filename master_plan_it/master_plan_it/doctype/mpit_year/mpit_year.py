# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

import frappe
from frappe import _
from frappe.model.document import Document
from frappe.utils import getdate


class MPITYear(Document):
	def validate(self):
		self._validate_dates()
		self._validate_no_overlap()

	def _validate_dates(self):
		if self.start_date and self.end_date:
			if getdate(self.start_date) > getdate(self.end_date):
				frappe.throw(_("Start Date cannot be after End Date"))

	def _validate_no_overlap(self):
		"""Block overlapping year ranges to keep date→year mapping unambiguous."""
		if not self.start_date or not self.end_date:
			return

		year_name = self.name or str(self.year)
		# Overlap condition: start_a <= end_b AND start_b <= end_a
		overlapping = frappe.get_all(
			"MPIT Year",
			filters=[
				["name", "!=", year_name],
				["start_date", "<=", self.end_date],
				["end_date", ">=", self.start_date],
			],
			pluck="name",
			limit=1,
		)

		if overlapping:
			frappe.throw(
				_("MPIT Year dates overlap with existing year: {0}.").format(overlapping[0])
			)
