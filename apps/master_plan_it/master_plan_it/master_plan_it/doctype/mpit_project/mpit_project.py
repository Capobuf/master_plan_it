# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

from __future__ import annotations

import frappe
from frappe import _
from frappe.model.document import Document
from frappe.model.naming import getseries
from master_plan_it import mpit_user_prefs, tax


class MPITProject(Document):
	def autoname(self):
		"""Generate name: PRJ-{NNNN} based on user preferences."""
		from master_plan_it import mpit_user_prefs
		
		# Get user preferences for prefix and digits
		prefix, digits = mpit_user_prefs.get_project_series(user=frappe.session.user)
		
		# Generate name: PRJ-0001, PRJ-0002, etc.
		# getseries returns only the number part, we need to add prefix
		series_key = f"{prefix}.####"
		sequence = getseries(series_key, digits)
		self.name = f"{prefix}{sequence}"
	
	def validate(self):
		self._require_allocations_for_approval()
		self._compute_allocations_vat_split()
		self._compute_quotes_vat_split()
	
	def _compute_allocations_vat_split(self):
		"""Compute net/vat/gross for all Project Allocations with strict VAT validation."""
		default_vat = mpit_user_prefs.get_default_vat_rate(frappe.session.user)
		
		for alloc in self.allocations:
			if alloc.vat_rate is None and default_vat is not None:
				alloc.vat_rate = default_vat
			
			final_vat_rate = tax.validate_strict_vat(
				alloc.planned_amount,
				alloc.vat_rate,
				default_vat,
				field_label=_("Allocation {0} Planned Amount").format(alloc.idx)
			)
			
			net, vat, gross = tax.split_net_vat_gross(
				alloc.planned_amount,
				final_vat_rate,
				bool(alloc.planned_amount_includes_vat)
			)
			
			alloc.planned_amount_net = net
			alloc.planned_amount_vat = vat
			alloc.planned_amount_gross = gross
	
	def _compute_quotes_vat_split(self):
		"""Compute net/vat/gross for all Project Quotes with strict VAT validation."""
		default_vat = mpit_user_prefs.get_default_vat_rate(frappe.session.user)
		
		for quote in self.quotes:
			if quote.vat_rate is None and default_vat is not None:
				quote.vat_rate = default_vat
			
			final_vat_rate = tax.validate_strict_vat(
				quote.amount,
				quote.vat_rate,
				default_vat,
				field_label=_("Quote {0} Amount").format(quote.idx)
			)
			
			net, vat, gross = tax.split_net_vat_gross(
				quote.amount,
				final_vat_rate,
				bool(quote.amount_includes_vat)
			)
			
			quote.amount_net = net
			quote.amount_vat = vat
			quote.amount_gross = gross

	def _require_allocations_for_approval(self) -> None:
		"""Ensure at least one allocation exists before approval or later states."""
		required_for_status = {"Approved", "In Progress", "On Hold", "Completed", "Cancelled"}
		if self.status in required_for_status and not self.allocations:
			frappe.throw(
				_("Add at least one Allocation (year + planned amount) before moving a project to status {0}.").format(
					self.status
				)
			)
