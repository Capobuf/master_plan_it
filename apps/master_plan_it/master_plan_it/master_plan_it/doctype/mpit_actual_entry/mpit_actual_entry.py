# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

from __future__ import annotations

import frappe
from frappe import _
from frappe.model.document import Document
from frappe.utils import flt, getdate
from master_plan_it import mpit_user_prefs, tax


class MPITActualEntry(Document):
	def validate(self):
		self._set_year_from_posting_date()
		self._compute_vat_split()
		self._autofill_cost_center()
		self._enforce_entry_kind_rules()
		self._enforce_status_rules()

	def _autofill_cost_center(self) -> None:
		"""Copy cost center from contract or project if missing."""
		if self.cost_center:
			return
		if self.contract:
			self.cost_center = frappe.db.get_value("MPIT Contract", self.contract, "cost_center")
		if not self.cost_center and self.project:
			self.cost_center = frappe.db.get_value("MPIT Project", self.project, "cost_center")

	def _enforce_entry_kind_rules(self) -> None:
		"""Validate entry_kind semantics (Delta vs Allowance Spend)."""
		has_contract = bool(self.contract)
		has_project = bool(self.project)
		has_link = has_contract or has_project

		# Default entry_kind if not set
		if not self.entry_kind:
			self.entry_kind = "Delta" if has_link else "Allowance Spend"

		if self.entry_kind == "Delta":
			if has_contract and has_project:
				frappe.throw(_("Delta entries must link to contract XOR project."))
			if not has_link:
				frappe.throw(_("Delta entries require a contract or a project."))
		elif self.entry_kind == "Allowance Spend":
			if has_link:
				frappe.throw(_("Allowance Spend cannot link a contract or project."))
			if not self.cost_center:
				frappe.throw(_("Cost Center is required for Allowance Spend."))
			if flt(self.amount) < 0 and not self.description:
				frappe.throw(_("Description is required for negative allowance spend entries."))
		else:
			frappe.throw(_("Entry Kind must be Delta or Allowance Spend."))

	def _enforce_status_rules(self) -> None:
		"""Ensure Verified entries are locked; only vCIO Manager can revert."""
		prev_status = None
		if self.name:
			prev_status = frappe.db.get_value("MPIT Actual Entry", self.name, "status")

		# Verify read-only except status (re-verify)
		if prev_status == "Verified" and self.status == "Verified":
			immutable_fields = [
				"posting_date",
				"year",
				"entry_kind",
				"category",
				"vendor",
				"contract",
				"project",
				"cost_center",
				"amount",
				"amount_includes_vat",
				"vat_rate",
				"description",
			]
			for field in immutable_fields:
				if self.has_value_changed(field):
					frappe.throw(_("Verified entries are read-only (field {0}).").format(field))

		# Revert Verified -> Recorded allowed only for vCIO Manager
		if prev_status == "Verified" and self.status == "Recorded":
			if not frappe.has_role("vCIO Manager"):
				frappe.throw(_("Only vCIO Manager can revert a Verified entry to Recorded."))
	
	def _compute_vat_split(self):
		"""Compute net/vat/gross for amount field with strict VAT validation."""
		# Get user default VAT rate
		default_vat = mpit_user_prefs.get_default_vat_rate(frappe.session.user)
		
		# Apply default if field is empty
		if self.vat_rate is None and default_vat is not None:
			self.vat_rate = default_vat
		
		# Strict VAT validation
		final_vat_rate = tax.validate_strict_vat(
			self.amount,
			self.vat_rate,
			default_vat,
			field_label=_("Amount")
		)
		
		# Compute split
		net, vat, gross = tax.split_net_vat_gross(
			self.amount,
			final_vat_rate,
			bool(self.amount_includes_vat)
		)
		
		self.amount_net = net
		self.amount_vat = vat
		self.amount_gross = gross

	def _set_year_from_posting_date(self) -> None:
		"""Derive MPIT Year from posting_date (idempotent)."""
		if not self.posting_date:
			frappe.throw(_("Posting Date is required to derive MPIT Year."))

		posting = getdate(self.posting_date)
		year_name = self._lookup_year_for_date(posting)

		if not year_name:
			frappe.throw(
				_("No MPIT Year covers posting date {0}. Create year {1} or set start/end dates that include the date.")
				.format(posting.isoformat(), posting.year)
			)

		# Always override to keep data consistent with the posting date.
		self.year = year_name

	def _lookup_year_for_date(self, posting_date) -> str | None:
		"""Find the MPIT Year covering a date using strict date ranges."""
		# Since start_date and end_date are mandatory in MPIT Year, we can rely on them.
		res = frappe.db.sql(
			"""
			SELECT name
			FROM `tabMPIT Year`
			WHERE start_date <= %(date)s AND end_date >= %(date)s
			ORDER BY start_date DESC
			LIMIT 1
			""",
			{"date": posting_date},
		)
		if res:
			return res[0][0]

		return None
