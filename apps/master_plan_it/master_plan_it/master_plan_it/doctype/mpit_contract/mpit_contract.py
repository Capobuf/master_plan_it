# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

from __future__ import annotations

import frappe
from frappe.model.document import Document
from master_plan_it import mpit_user_prefs, tax


class MPITContract(Document):
	def validate(self):
		self._compute_vat_split()
	
	def _compute_vat_split(self):
		"""Compute net/vat/gross for current_amount field with strict VAT validation."""
		# Get user default VAT rate
		default_vat = mpit_user_prefs.get_default_vat_rate(frappe.session.user)
		default_includes = mpit_user_prefs.get_default_includes_vat(frappe.session.user)
		
		# Apply default if field is empty
		if self.vat_rate is None and default_vat is not None:
			self.vat_rate = default_vat
		if not self.current_amount_includes_vat and default_includes:
			self.current_amount_includes_vat = 1
		
		# Strict VAT validation
		final_vat_rate = tax.validate_strict_vat(
			self.current_amount,
			self.vat_rate,
			default_vat,
			field_label="Current Amount"
		)
		
		# Compute split
		net, vat, gross = tax.split_net_vat_gross(
			self.current_amount,
			final_vat_rate,
			bool(self.current_amount_includes_vat)
		)
		
		self.current_amount_net = net
		self.current_amount_vat = vat
		self.current_amount_gross = gross
