# MPIT Contract controller: validates contract terms, naming invariants,
# and keeps renewal/status coherence. Terms are the single source of truth for pricing.
# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

from __future__ import annotations

from datetime import date

import frappe
from frappe import _
from frappe.model.document import Document
from frappe.model.naming import make_autoname
from frappe.utils import add_days, flt, getdate

from master_plan_it.master_plan_it.doctype.mpit_planned_item import mpit_planned_item
from master_plan_it import mpit_defaults


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
		# NOTE Design Decision: We use raw SQL DELETE because generated lines
		# (is_generated=1) are protected by _enforce_generated_lines_read_only()
		# in mpit_budget.py. The normal Document API would block deletion.
		# Raw SQL bypasses this protection intentionally when the source (contract)
		# is being deleted. Commit per-budget ensures partial progress is saved
		# if one budget fails (best-effort cleanup pattern).
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

		# Terms validation (skip during migration patch)
		if not getattr(self.flags, "skip_terms_validation", False):
			self._validate_terms_required()
			self._validate_terms_no_overlap()
			self._validate_first_term_date()

		# Compute current term and annual summaries
		self._compute_current_term()
		self._compute_annual_summaries()

		# Existing methods
		self._default_next_renewal_date()
		self._normalize_status()
		self._sync_planned_item_coverage(prev)

	# ─────────────────────────────────────────────────────────────────────────────
	# Terms Validation
	# ─────────────────────────────────────────────────────────────────────────────

	def _validate_terms_required(self) -> None:
		"""Ensure at least one pricing term exists."""
		if not self.terms or len(self.terms) == 0:
			frappe.throw(
				_("At least one pricing term is required. Add a term with the contract's initial pricing.")
			)

	def _validate_terms_no_overlap(self) -> None:
		"""Validate that terms do not have overlapping date ranges.

		Two terms overlap if term[i].to_date >= term[i+1].from_date.
		Terms without to_date are handled by the system (auto-end before next term).
		"""
		if not self.terms or len(self.terms) < 2:
			return

		terms_sorted = sorted(
			[t for t in self.terms if t.from_date],
			key=lambda t: getdate(t.from_date)
		)

		for i, term in enumerate(terms_sorted[:-1]):
			next_term = terms_sorted[i + 1]
			term_end = getdate(term.to_date) if term.to_date else None
			next_start = getdate(next_term.from_date)

			# If term has explicit to_date and it overlaps with next term's start
			if term_end and term_end >= next_start:
				frappe.throw(
					_("Term {0} (ending {1}) overlaps with Term {2} (starting {3}). Please fix the date ranges.").format(
						i + 1, term_end, i + 2, next_start
					)
				)

	def _validate_first_term_date(self) -> None:
		"""Warning if first term from_date does not match contract start_date."""
		if not self.terms or not self.start_date:
			return

		first_term = min(
			[t for t in self.terms if t.from_date],
			key=lambda t: getdate(t.from_date),
			default=None
		)

		if first_term and getdate(first_term.from_date) != getdate(self.start_date):
			frappe.msgprint(
				_("Note: First term starts on {0} but contract start date is {1}. Consider aligning them.").format(
					first_term.from_date, self.start_date
				),
				indicator="orange"
			)

	# ─────────────────────────────────────────────────────────────────────────────
	# Current Term Computation
	# ─────────────────────────────────────────────────────────────────────────────

	def _compute_current_term(self) -> None:
		"""Identify and populate fields for the term currently in effect (based on today's date).

		The "current term" is the one where today falls between from_date and the computed end date.
		If no term covers today (contract not started or already ended), fields are set to None.
		"""
		today = date.today()

		# Reset fields
		self.current_term_amount = None
		self.current_term_billing_cycle = None
		self.current_term_monthly_net = None
		self.current_term_from_date = None

		if not self.terms:
			return

		terms_sorted = sorted(
			[t for t in self.terms if t.from_date],
			key=lambda t: getdate(t.from_date)
		)

		if not terms_sorted:
			return

		# Determine contract end date (fallback to far future if open-ended)
		contract_end = getdate(self.end_date) if self.end_date else date(2099, 12, 31)

		for i, term in enumerate(terms_sorted):
			term_start = getdate(term.from_date)

			# Determine term end date:
			# 1. Use explicit to_date if set
			# 2. Otherwise, day before next term starts
			# 3. If last term, use contract end date
			if term.to_date:
				term_end = getdate(term.to_date)
			elif i + 1 < len(terms_sorted):
				term_end = add_days(getdate(terms_sorted[i + 1].from_date), -1)
			else:
				term_end = contract_end

			# Check if today falls within this term's range
			if term_start <= today <= term_end:
				self.current_term_amount = term.amount
				self.current_term_billing_cycle = term.billing_cycle
				self.current_term_monthly_net = term.monthly_amount_net
				self.current_term_from_date = term.from_date
				break

	# ─────────────────────────────────────────────────────────────────────────────
	# Annual Summary Computation
	# ─────────────────────────────────────────────────────────────────────────────

	def _compute_annual_summaries(self) -> None:
		"""Calculate annualized amounts for current and next fiscal year.

		These fields provide a quick view of the contract's total cost impact
		for the current year and next year, accounting for term changes.
		"""
		today = date.today()
		self.current_year_label = str(today.year)
		self.next_year_label = str(today.year + 1)
		self.annual_amount_current_year = self._calculate_annual_for_year(today.year)
		self.annual_amount_next_year = self._calculate_annual_for_year(today.year + 1)

	def _calculate_annual_for_year(self, year: int) -> float:
		"""Calculate total annualized net amount for a specific year.

		This method iterates through all terms and calculates the pro-rata
		contribution of each term to the specified year based on overlap months.

		Args:
			year: The fiscal year to calculate for

		Returns:
			Total annualized net amount for the year
		"""
		from master_plan_it import annualization

		if not self.terms:
			return 0.0

		year_start, year_end = annualization.get_year_bounds(year)
		contract_start = getdate(self.start_date) if self.start_date else year_start
		contract_end = getdate(self.end_date) if self.end_date else year_end

		total = 0.0
		terms_sorted = sorted(
			[t for t in self.terms if t.from_date],
			key=lambda t: getdate(t.from_date)
		)

		for i, term in enumerate(terms_sorted):
			term_start = getdate(term.from_date)

			# Determine term end date (same logic as _compute_current_term)
			if term.to_date:
				term_end = getdate(term.to_date)
			elif i + 1 < len(terms_sorted):
				term_end = add_days(getdate(terms_sorted[i + 1].from_date), -1)
			else:
				term_end = contract_end

			# Clip to year bounds and contract bounds
			period_start = max(term_start, contract_start, year_start)
			period_end = min(term_end, contract_end, year_end)

			if period_start > period_end:
				continue

			months = annualization.overlap_months(period_start, period_end, year_start, year_end)
			if months <= 0:
				continue

			monthly_net = flt(term.monthly_amount_net or 0, 2)
			total += flt(monthly_net * months, 2)

		return flt(total, 2)

	# ─────────────────────────────────────────────────────────────────────────────
	# Existing Methods (unchanged)
	# ─────────────────────────────────────────────────────────────────────────────

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
