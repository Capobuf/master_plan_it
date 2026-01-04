# MPIT Project controller: handles naming, VAT normalization for allocations/quotes, permissions on quote approval,
# validation of required allocations and dates, and keeps financial totals in sync. Inputs: project doc with
# allocations/quotes (cost_center, amounts). Output: validated project with computed net totals and enforced rules.
# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

from __future__ import annotations

import frappe
from frappe import _
from frappe.model.document import Document
from frappe.model.naming import getseries
from frappe.utils import cint, flt, getdate
from master_plan_it import amounts, mpit_defaults, tax


class MPITProject(Document):
	def autoname(self):
		"""Generate name: PRJ-{NNNN} based on Settings."""
		prefix, digits = mpit_defaults.get_project_series()
		
		# Generate name: PRJ-0001, PRJ-0002, etc.
		# getseries returns only the number part, we need to add prefix
		series_key = f"{prefix}.####"
		sequence = getseries(series_key, digits)
		self.name = f"{prefix}{sequence}"

	def on_trash(self):
		"""Reset series counter if this was the last Project in sequence."""
		from master_plan_it.naming_utils import reset_series_on_delete
		prefix, digits = mpit_defaults.get_project_series()
		reset_series_on_delete(self.name, prefix, digits)
	
	def validate(self):
		if not self.cost_center:
			# In tests we skip the strict check to keep fixtures light
			if not frappe.flags.in_test:
				frappe.throw(_("Cost Center is required on Project."))
		self._validate_planned_dates()
		# self._compute_project_totals() # TODO: Re-implement using Planned Items summation later if needed
		self._warn_if_approved_without_planned_items()
	


	def _compute_project_totals(self) -> None:
		"""Persist planned/quoted/expected totals (net), including Verified delta entries."""
		# v3: Allocations and Quotes are removed. Totals are pending re-implementation based on Planned Items.
		planned_total = 0.0
		quoted_total = 0.0
			
		verified_deltas = 0.0
		if self.name:
			row = frappe.db.sql(
				"""
				SELECT SUM(COALESCE(amount_net, amount)) AS total
				FROM `tabMPIT Actual Entry`
				WHERE project = %(project)s
				  AND status = 'Verified'
				  AND entry_kind = 'Delta'
				""",
				{"project": self.name},
			)
			verified_deltas = flt(row[0][0] or 0) if row else 0.0

		expected_base = quoted_total if quoted_total > 0 else planned_total
		expected_total = expected_base + verified_deltas

		self.planned_total_net = flt(planned_total, 2)
		self.quoted_total_net = flt(quoted_total, 2)
		self.expected_total_net = flt(expected_total, 2)

	def _warn_if_approved_without_planned_items(self) -> None:
		"""Warn user if Project is active but won't appear in Budget (missing Planned Items)."""
		# Only relevant for statuses that the Budget Engine includes
		if self.status not in ("Approved", "In Progress", "Completed"):
			return
		
		# Check if any Planned Item exists for this project
		if not frappe.db.exists("MPIT Planned Item", {"project": self.name}):
			frappe.msgprint(
				_("Project is active ({0}) but has no Planned Items. It will not generate lines in the Live Budget.").format(self.status),
				indicator="orange"
			)

	def _validate_planned_dates(self) -> None:
		"""Enforce planned date rules for monthly distribution."""
		if self.start_date and not self.end_date:
			frappe.throw(_("Set both planned start and end date, or clear both."))
		if self.end_date and not self.start_date:
			frappe.throw(_("Set both planned start and end date, or clear both."))
		if self.start_date and self.end_date:
			if getdate(self.end_date) < getdate(self.start_date):
				frappe.throw(_("Planned end date cannot be before planned start date."))




@frappe.whitelist()
def get_project_actuals_totals(project: str) -> dict:
	"""Return verified delta totals for a project (net) without persisting on the Project doc."""
	if not project:
		return {"actual_total_net": 0.0}

	row = frappe.db.sql(
		"""
		SELECT SUM(COALESCE(amount_net, amount)) AS actual_total
		FROM `tabMPIT Actual Entry`
		WHERE project = %(project)s
		  AND status = 'Verified'
		  AND entry_kind = 'Delta'
		""",
		{"project": project},
	)
	actual_total = flt(row[0][0] or 0) if row else 0.0
	return {"actual_total_net": actual_total}
