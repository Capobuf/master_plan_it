"""
FILE: master_plan_it/doctype/mpit_budget_addendum/mpit_budget_addendum.py
SCOPO: Gestisce gli Addendum di budget (delta cap per year+cost_center) assicurando naming deterministico e vincoli su Snapshot/Allowance.
INPUT: Campi documento (year, cost_center, delta_amount, reason, reference_snapshot) in validate/autoname/before_submit.
OUTPUT/SIDE EFFECTS: Genera nome `ADD-{year}-{abbr}-{####}`, valida esistenza Snapshot APP/Baseline con riga Allowance per il cost center; blocca submit se mancano i prerequisiti.
"""

from __future__ import annotations

import re

import frappe
from frappe import _
from frappe.model.document import Document
from frappe.model.naming import getseries


class MPITBudgetAddendum(Document):
	def autoname(self):
		"""Naming: ADD-{year}-{cost_center.abbr}-{####} (abbr slugged)."""
		if not self.year or not self.cost_center:
			frappe.throw(_("Year and Cost Center are required to name the Addendum."))

		abbr = self._get_cost_center_abbr()
		series_key = f"ADD-{self.year}-{abbr}-" + ".####"
		seq = getseries(series_key, 4)
		self.name = f"ADD-{self.year}-{abbr}-{seq}"

	def on_trash(self):
		"""Reset series counter if this was the last Addendum in sequence."""
		from master_plan_it.naming_utils import reset_series_on_delete
		abbr = self._get_cost_center_abbr()
		series_prefix = f"ADD-{self.year}-{abbr}-"
		reset_series_on_delete(self.name, series_prefix, 4)

	def validate(self):
		if not self.reason:
			frappe.throw(_("Reason is required."))

	def before_submit(self):
		self._validate_reference_snapshot()
		self._validate_allowance_exists()

	def _validate_reference_snapshot(self) -> None:
		"""Ensure reference_snapshot is an approved Snapshot/Baseline for the same year."""
		if not self.reference_snapshot or not self.year:
			frappe.throw(_("Reference Snapshot and Year are required."))

		conditions = ["name = %(name)s", "year = %(year)s", "docstatus = 1"]
		params = {"name": self.reference_snapshot, "year": self.year}

		if frappe.db.has_column("MPIT Budget", "budget_type"):
			conditions.append("budget_type = 'Snapshot'")
		elif frappe.db.has_column("MPIT Budget", "budget_kind"):
			conditions.append("budget_kind = 'Baseline'")

		if not frappe.db.sql(
			f"select name from `tabMPIT Budget` where {' and '.join(conditions)} limit 1",
			params,
		):
			frappe.throw(
				_("Reference Snapshot must be an approved Snapshot for year {0}.").format(self.year)
			)

	def _validate_allowance_exists(self) -> None:
		"""Check that the reference snapshot has an allowance line for the cost center."""
		if not frappe.db.exists(
			"MPIT Budget Line",
			{"parent": self.reference_snapshot, "cost_center": self.cost_center, "line_kind": "Allowance"},
		):
			frappe.throw(
				_("Reference Snapshot has no Allowance line for Cost Center {0}.").format(self.cost_center)
			)

	def _get_cost_center_abbr(self) -> str:
		abbr = frappe.db.get_value("MPIT Cost Center", self.cost_center, "abbr") or ""
		if abbr:
			return abbr
		name = frappe.db.get_value("MPIT Cost Center", self.cost_center, "cost_center_name") or self.cost_center
		return self._slugify(name)

	@staticmethod
	def _slugify(value: str) -> str:
		cleaned = re.sub(r"[^A-Za-z0-9]+", "-", value or "").strip("-").upper()
		if len(cleaned) > 12:
			cleaned = cleaned[:12].rstrip("-") or cleaned[:12]
		return cleaned or "CC"
