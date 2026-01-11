# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

from __future__ import annotations

from frappe.model.document import Document


class MPITVendor(Document):
	def autoname(self):
		"""Ensure quick entry (__newname) populates vendor_name before naming."""
		newname = self.get("__newname")
		if not getattr(self, "vendor_name", None) and newname:
			self.vendor_name = newname
		# default autoname = field:vendor_name
		self.name = self.vendor_name

	def validate(self):
		# Safety net if validate runs without autoname (e.g., programmatic insert with __newname)
		newname = self.get("__newname")
		if not self.vendor_name and newname:
			self.vendor_name = newname
