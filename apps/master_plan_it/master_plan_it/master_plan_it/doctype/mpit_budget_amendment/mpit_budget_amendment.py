# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

import frappe
from frappe import _
from frappe.model.document import Document
from master_plan_it import mpit_user_prefs, tax


class MPITBudgetAmendment(Document):
	def validate(self):
		self._enforce_status_invariants()
		self._compute_lines_vat_split()
	
	def _compute_lines_vat_split(self):
		"""Compute net/vat/gross for all Amendment Lines with strict VAT validation."""
		default_vat = mpit_user_prefs.get_default_vat_rate(frappe.session.user)
		
		for line in self.lines:
			if line.vat_rate is None and default_vat is not None:
				line.vat_rate = default_vat
			
			final_vat_rate = tax.validate_strict_vat(
				line.delta_amount,
				line.vat_rate,
				default_vat,
				field_label=frappe._("Line {0} Delta Amount").format(line.idx)
			)
			
			net, vat, gross = tax.split_net_vat_gross(
				line.delta_amount,
				final_vat_rate,
				bool(line.delta_amount_includes_vat)
			)
			
			line.delta_amount_net = net
			line.delta_amount_vat = vat
			line.delta_amount_gross = gross

	def _enforce_status_invariants(self) -> None:
		"""Keep workflow_state aligned with docstatus now that it is an editable status label."""
		if not self.workflow_state:
			self.workflow_state = "Draft"

		if self.docstatus == 0 and self.workflow_state == "Approved":
			frappe.throw(_("Draft Budget Amendment cannot be set to Approved. Submit the document to approve it."))

		if self.docstatus == 1 and self.workflow_state != "Approved":
			self.workflow_state = "Approved"

	def on_submit(self):
		# Ensure submitted amendments always reflect Approved status
		if self.workflow_state != "Approved":
			self.workflow_state = "Approved"
			self.db_set("workflow_state", "Approved")
