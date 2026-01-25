# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

from frappe.model.document import Document


class MPITBudgetLine(Document):
	def after_insert(self):
		self._update_parent_totals()

	def on_update(self):
		self._update_parent_totals()

	def on_trash(self):
		self._update_parent_totals()

	def _update_parent_totals(self):
		if self.parent and getattr(self, "parenttype", None) == "MPIT Budget":
			# Late import to avoid circular dependencies during module load.
			from ..mpit_budget.mpit_budget import update_budget_totals
			update_budget_totals(self.parent)
