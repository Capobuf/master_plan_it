# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

import frappe
from frappe import _
from frappe.model.document import Document


class MPITSettings(Document):
	def validate(self) -> None:
		if not self.currency:
			frappe.throw(_("Currency is required. Please set a Currency on MPIT Settings."))
