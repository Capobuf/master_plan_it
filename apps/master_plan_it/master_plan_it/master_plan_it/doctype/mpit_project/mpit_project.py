# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

from __future__ import annotations

import frappe
from frappe import _
from frappe.model.document import Document


class MPITProject(Document):
	def validate(self):
		self._require_allocations_for_approval()

	def _require_allocations_for_approval(self) -> None:
		"""Ensure at least one allocation exists before approval or later states."""
		required_for_status = {"Approved", "In Progress", "On Hold", "Completed", "Cancelled"}
		if self.status in required_for_status and not self.allocations:
			frappe.throw(
				_("Add at least one Allocation (year + planned amount) before moving a project to status {0}.").format(
					self.status
				)
			)
