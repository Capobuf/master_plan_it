"""
FILE: master_plan_it/doctype/mpit_planned_item/mpit_planned_item.py
SCOPO: Gestisce i Planned Items standalone (per progetto) con date/distribuzione, VAT, e flag di copertura deterministico.
INPUT: Campi documento (project, description, amount, start_date, end_date, spend_date, distribution, covered_by_*, item_type, vendor) in validate/save.
OUTPUT/SIDE EFFECTS: Calcola VAT, sincronizza is_covered da covered_by_*, blocca edit dei campi chiave dopo submit, registra commenti timeline su cambi copertura.
"""

from __future__ import annotations

import frappe
from frappe import _
from frappe.model.document import Document
from frappe.utils import flt, getdate, nowdate

from master_plan_it import mpit_defaults, tax


class MPITPlannedItem(Document):
	def before_save(self):
		prev_doc = None
		if self.name:
			prev_doc = frappe.db.get_value(
				"MPIT Planned Item",
				self.name,
				["is_covered", "covered_by_type", "covered_by_name"],
				as_dict=True,
			)
		prev_is_covered = prev_doc.get("is_covered") if prev_doc else None
		prev_type = prev_doc.get("covered_by_type") if prev_doc else None
		prev_name = prev_doc.get("covered_by_name") if prev_doc else None
		self.flags._prev_coverage = (prev_is_covered, prev_type, prev_name)
		self._sync_coverage_flag()
		self._enforce_horizon_flag()

	def validate(self):
		self._compute_vat_amounts()
		self._validate_dates()
		self._validate_spend_date()
		self._validate_distribution()
		self._validate_coverage_fields()
		self._enforce_submit_immutability()

	def after_save(self):
		self._maybe_log_coverage_change()

	def _validate_dates(self) -> None:
		if not self.start_date or not self.end_date:
			frappe.throw(_("Start Date and End Date are required."))
		start = getdate(self.start_date)
		end = getdate(self.end_date)
		if end < start:
			frappe.throw(_("End Date cannot be before Start Date."))

	def _validate_distribution(self) -> None:
		if self.distribution not in {"all", "start", "end"}:
			frappe.throw(_("Distribution must be one of: all, start, end."))

	def _validate_spend_date(self) -> None:
		"""Enforce spend_date recency and coherence with horizon and period."""
		today = getdate(nowdate())
		horizon_years = {today.year, today.year + 1}

		if self.spend_date:
			spend = getdate(self.spend_date)
			if spend < today:
				frappe.throw(_("Spend Date cannot be in the past."))
			if spend.year not in horizon_years:
				self.out_of_horizon = 1
			else:
				self.out_of_horizon = 0
			# coherence with period
			start = getdate(self.start_date)
			end = getdate(self.end_date)
			if spend < start or spend > end:
				frappe.throw(_("Spend Date must fall between Start Date and End Date."))
		else:
			# Mark horizon based on period overlap with current/next year
			start = getdate(self.start_date)
			end = getdate(self.end_date)
			if start.year not in horizon_years and end.year not in horizon_years:
				self.out_of_horizon = 1
			else:
				self.out_of_horizon = 0

	def _validate_coverage_fields(self) -> None:
		if self.covered_by_type and not self.covered_by_name:
			frappe.throw(_("Covered By requires a linked document."))
		if self.covered_by_name and not self.covered_by_type:
			frappe.throw(_("Covered By Type is required when setting Covered By."))

	def _sync_coverage_flag(self) -> None:
		self.is_covered = 1 if (self.covered_by_type and self.covered_by_name) else 0

	def _compute_vat_amounts(self) -> None:
		"""Compute net/vat/gross from amount using same logic as Contract/Actual Entry."""
		default_vat = mpit_defaults.get_default_vat_rate()

		# Apply default VAT if not set
		if self.vat_rate is None and default_vat is not None:
			self.vat_rate = default_vat

		# Validate and get final VAT rate
		final_vat_rate = tax.validate_strict_vat(
			self.amount,
			self.vat_rate,
			default_vat,
			field_label=_("Planned Item Amount")
		)

		# Compute split
		net, vat, gross = tax.split_net_vat_gross(
			self.amount,
			final_vat_rate,
			bool(self.amount_includes_vat)
		)

		self.amount_net = flt(net, 2)
		self.amount_vat = flt(vat, 2)
		self.amount_gross = flt(gross, 2)

	def _maybe_log_coverage_change(self) -> None:
		prev = getattr(self.flags, "_prev_coverage", (None, None, None))
		prev_is_covered, prev_type, prev_name = prev
		if self.is_covered == prev_is_covered:
			return

		if self.is_covered:
			msg = _("Covered by {0} {1}").format(self.covered_by_type, self.covered_by_name)
		else:
			msg = _("Uncovered (cleared covered_by)")
		self.add_comment("Comment", msg)

	def _enforce_submit_immutability(self) -> None:
		if self.docstatus != 1 or self.is_new():
			return

		immutable_fields = [
			"project",
			"description",
			"amount",
			"amount_includes_vat",
			"vat_rate",
			"start_date",
			"end_date",
			"spend_date",
			"distribution",
			"item_type",
			"vendor",
		]
		for field in immutable_fields:
			if self.has_value_changed(field):
				frappe.throw(_("Submitted Planned Items are read-only (field {0}).").format(field))

	def _enforce_horizon_flag(self) -> None:
		"""Flag items whose spend_date/period are outside current year + 1."""
		today = getdate(nowdate())
		allowed_years = {today.year, today.year + 1}

		if self.spend_date:
			spend = getdate(self.spend_date)
			self.out_of_horizon = 0 if spend.year in allowed_years else 1
			return

		start = getdate(self.start_date)
		end = getdate(self.end_date)
		period_years = {start.year, end.year}
		self.out_of_horizon = 0 if allowed_years & period_years else 1


def set_coverage(planned_item: str, covered_by_type: str | None, covered_by_name: str | None) -> None:
	"""Set or clear coverage for a Planned Item in a controlled way.
	
	Args:
		planned_item: MPIT Planned Item name
		covered_by_type: "Contract" | "Actual" or None to clear
		covered_by_name: linked document name or None to clear
	"""
	if not planned_item:
		return

	doc = frappe.get_doc("MPIT Planned Item", planned_item)

	new_type = covered_by_type or None
	new_name = covered_by_name or None

	# Idempotent guard
	if doc.covered_by_type == new_type and doc.covered_by_name == new_name:
		return

	doc.covered_by_type = new_type
	doc.covered_by_name = new_name
	doc.is_covered = 1 if (new_type and new_name) else 0

	# Save without altering immutable fields
	doc.save(ignore_permissions=True)
