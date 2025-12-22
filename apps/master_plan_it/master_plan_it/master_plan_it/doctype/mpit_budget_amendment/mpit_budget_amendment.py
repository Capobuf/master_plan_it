# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

import frappe
from frappe.model.document import Document
from master_plan_it import mpit_user_prefs, tax


class MPITBudgetAmendment(Document):
	def validate(self):
		self._compute_lines_vat_split()
	
	def _compute_lines_vat_split(self):
		"""Compute net/vat/gross for all Amendment Lines with strict VAT validation."""
		default_vat = mpit_user_prefs.get_default_vat_rate(frappe.session.user)
		default_includes = mpit_user_prefs.get_default_includes_vat(frappe.session.user)
		
		for line in self.lines:
			if line.vat_rate is None and default_vat is not None:
				line.vat_rate = default_vat
			if not line.delta_amount_includes_vat and default_includes:
				line.delta_amount_includes_vat = 1
			
			final_vat_rate = tax.validate_strict_vat(
				line.delta_amount,
				line.vat_rate,
				default_vat,
				field_label=f"Line {line.idx} Delta Amount"
			)
			
			net, vat, gross = tax.split_net_vat_gross(
				line.delta_amount,
				final_vat_rate,
				bool(line.delta_amount_includes_vat)
			)
			
			line.delta_amount_net = net
			line.delta_amount_vat = vat
			line.delta_amount_gross = gross

