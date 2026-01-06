# MPIT Contract controller: validates contract monetary splits, naming invariants,
# and keeps renewal/status coherence. Inputs: contract fields (amounts, billing cadence, cost_center).
# Output: normalized contract with net/vat/gross, monthly equivalent, and clean status/renewal dates.
# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

from __future__ import annotations

import frappe
from frappe import _
from frappe.model.document import Document
from frappe.model.naming import make_autoname
from frappe.utils import flt, getdate

from master_plan_it.master_plan_it.doctype.mpit_planned_item import mpit_planned_item
from master_plan_it import mpit_defaults, tax


class MPITContract(Document):
	def autoname(self):
		"""Name contracts using series from settings (no manual titles)."""
		prefix, digits = mpit_defaults.get_contract_series()
		series = f"{prefix}.{'#' * digits}"
		self.name = make_autoname(series)

		if not self.description:
			self.description = self.name

	def on_trash(self):
		"""Clean up linked budget lines and reset series counter.
		
		When a contract is deleted:
		1. Remove all generated budget lines that reference this contract (v3 rule §4.4)
		2. Recompute totals for affected Live budgets
		3. Reset the series counter if this was the last in sequence
		
		This is idempotent: running multiple times has the same effect.
		"""
		self._cleanup_linked_budget_lines()
		
		# Reset series counter
		from master_plan_it.naming_utils import reset_series_on_delete
		prefix, digits = mpit_defaults.get_contract_series()
		reset_series_on_delete(self.name, prefix, digits)
	
	def _cleanup_linked_budget_lines(self) -> None:
		"""Remove generated budget lines that reference this contract.
		
		Only affects Live budgets (Snapshots should remain immutable).
		After removing lines, recomputes budget totals.
		
		This follows v3 design decision §4.4:
		"righe generate non più valide vengono cancellate (delete)"
		"""
		# Find all budget lines linked to this contract
		linked_lines = frappe.db.sql("""
			SELECT 
				bl.name AS line_name,
				bl.parent AS budget_name,
				b.budget_type
			FROM `tabMPIT Budget Line` bl
			JOIN `tabMPIT Budget` b ON bl.parent = b.name
			WHERE bl.contract = %s
			  AND bl.is_generated = 1
		""", (self.name,), as_dict=True)
		
		if not linked_lines:
			return
		
		# Group by budget for efficient processing
		budgets_to_update = {}
		for line in linked_lines:
			# Only delete from Live budgets - Snapshots are immutable
			if line.budget_type == "Live":
				budgets_to_update.setdefault(line.budget_name, []).append(line.line_name)
		
		# Delete lines and recompute affected budgets
		for budget_name, line_names in budgets_to_update.items():
			# Delete the lines directly from database (child table)
			for line_name in line_names:
				frappe.db.sql(
					"""DELETE FROM `tabMPIT Budget Line` WHERE name = %s""",
					(line_name,)
				)
			
			# Reload and recompute totals for the budget
			try:
				budget_doc = frappe.get_doc("MPIT Budget", budget_name)
				budget_doc.reload()
				budget_doc._compute_totals()
				budget_doc.db_update()
				frappe.db.commit()
			except Exception as e:
				frappe.log_error(
					f"Failed to recompute totals for {budget_name} after contract {self.name} deletion: {e}",
					"Contract Deletion Cleanup"
				)


	def validate(self):
		prev = self.get_doc_before_save()
		if not self.cost_center:
			frappe.throw(_("Cost Center is required for contracts."))
		self._compute_vat_split()
		self._compute_monthly_amount()
		self._default_next_renewal_date()
		self._normalize_status()
		self._sync_planned_item_coverage(prev)
	
	def _compute_vat_split(self):
		"""Compute net/vat/gross for current_amount field with strict VAT validation."""
		# Get global default VAT rate
		default_vat = mpit_defaults.get_default_vat_rate()
		
		# Apply default if field is empty
		if self.vat_rate is None and default_vat is not None:
			self.vat_rate = default_vat
		
		# Strict VAT validation
		final_vat_rate = tax.validate_strict_vat(
			self.current_amount,
			self.vat_rate,
			default_vat,
			field_label=frappe._("Current Amount")
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

	def _compute_monthly_amount(self) -> None:
		"""Compute monthly net equivalent based on billing cadence."""
		if self.current_amount_net is None:
			self.monthly_amount_net = None
			return

		billing = self.billing_cycle or "Monthly"
		if billing == "Quarterly":
			self.monthly_amount_net = flt((self.current_amount_net or 0) * 4 / 12, 2)
		elif billing == "Annual":
			self.monthly_amount_net = flt((self.current_amount_net or 0) / 12, 2)
		else:
			# Monthly and "Other" default to same value
			self.monthly_amount_net = flt(self.current_amount_net or 0, 2)

	def _default_next_renewal_date(self) -> None:
		"""Auto-fill next_renewal_date from end_date when possible.
		
		Note: next_renewal_date must not be mandatory client-side because it is auto-filled here.
		"""
		if not self.auto_renew:
			return
		if self.next_renewal_date:
			return
		if self.end_date:
			self.next_renewal_date = self.end_date

	def _normalize_status(self) -> None:
		"""Keep auto-renew contracts coherent without promoting Draft into Active."""
		if not self.auto_renew:
			return
		if self.status in (None, ""):
			self.status = "Active"
		elif self.status == "Pending Renewal":
			self.status = "Active"

	def _sync_planned_item_coverage(self, prev: Document | None) -> None:
		"""Set/clear Planned Item coverage when linked contract is valid/removed."""
		prev_planned = getattr(prev, "planned_item", None) if prev else None
		prev_status = getattr(prev, "status", None) if prev else None

		valid_statuses = {"Active", "Pending Renewal", "Renewed"}
		current_valid = self.status in valid_statuses
		prev_valid = prev_status in valid_statuses

		# Clear previous coverage if unlinked or no longer valid
		if prev_planned and (prev_planned != self.planned_item or (prev_valid and not current_valid)):
			mpit_planned_item.set_coverage(prev_planned, None, None)

		# Set coverage when linked and valid
		if self.planned_item and current_valid:
			mpit_planned_item.set_coverage(self.planned_item, "MPIT Contract", self.name)
