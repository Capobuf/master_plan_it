# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

import frappe
from frappe import _
from frappe.model.document import Document
from frappe.utils import getdate


class MPITYear(Document):
	def validate(self):
		self._validate_dates()

	def _validate_dates(self):
		if self.start_date and self.end_date:
			if getdate(self.start_date) > getdate(self.end_date):
				frappe.throw(_("Start Date cannot be after End Date"))
