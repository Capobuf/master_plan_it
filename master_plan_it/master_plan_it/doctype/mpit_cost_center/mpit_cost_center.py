"""
FILE: master_plan_it/doctype/mpit_cost_center/mpit_cost_center.py
SCOPO: Gestisce la gerarchia dei Cost Center e garantisce un'abbreviazione stabile per l'autonaming degli Addendum.
INPUT: Document fields (cost_center_name, abbr, parent_cost_center, ecc.) durante validate/insert/update.
OUTPUT/SIDE EFFECTS: Valida e auto-compila `abbr` univoco (slug) se assente o duplicato; mantiene struttura Nested Set.
"""

from __future__ import annotations

import re

import frappe
from frappe.model.document import Document
from frappe.utils.nestedset import NestedSet


class MPITCostCenter(NestedSet, Document):
	"""Tree DocType for cost centers; auto-generates a short unique abbreviation."""

	def validate(self):
		self._ensure_abbr()

	def _ensure_abbr(self) -> None:
		"""Autopopulate a stable, unique abbreviation used by Addendum naming."""
		raw = self.abbr or self.cost_center_name or self.name or ""
		base = self._slugify(raw)
		abbr = base or "CC"

		suffix = 2
		while self._abbr_conflicts(abbr):
			abbr = f"{base}-{suffix}"
			suffix += 1
		self.abbr = abbr

	@staticmethod
	def _slugify(value: str) -> str:
		"""Return an uppercase short slug (letters/digits with dashes) capped to 12 chars."""
		cleaned = re.sub(r"[^A-Za-z0-9]+", "-", value or "").strip("-").upper()
		if len(cleaned) > 12:
			cleaned = cleaned[:12].rstrip("-") or cleaned[:12]
		return cleaned or "CC"

	def _abbr_conflicts(self, abbr: str) -> bool:
		filters = {"abbr": abbr}
		if self.name:
			filters["name"] = ["!=", self.name]
		return bool(frappe.db.exists("MPIT Cost Center", filters))
