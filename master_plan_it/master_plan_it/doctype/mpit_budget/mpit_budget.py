"""
FILE: master_plan_it/doctype/mpit_budget/mpit_budget.py
SCOPO: Gestisce Budget Live/Snapshot (naming LIVE/APP, refresh da sorgenti, protezione righe generate) e calcoli totali.
INPUT: Document fields (year, budget_type, lines, workflow_state) durante eventi Frappe e chiamate whitelisted.
OUTPUT/SIDE EFFECTS: Genera nomi deterministici, applica invarianti (Approved solo Snapshot), refresha righe da sorgenti, calcola totali e blocca edit sulle righe generate.
"""

from __future__ import annotations

from datetime import date
import calendar

import frappe
from frappe import _
from frappe.model.document import Document
from frappe.model.naming import getseries
from frappe.utils import add_days, cint, flt, getdate as _getdate, nowdate
from frappe.query_builder.functions import Coalesce, Sum
from master_plan_it import amounts, annualization, mpit_defaults


class MPITBudget(Document):
	def autoname(self):
		"""Generate name:
		
		- Live: `{prefix}{year}-LIVE` (single Live per year)
		- Snapshot: `{prefix}{year}-APP-{NN}`
		
		Prefix/digits are global (MPIT Settings).
		"""
		if not self.year:
			frappe.throw(_("Year and Budget Type are required to generate Budget name"))
		budget_type = self.budget_type or "Live"
		self.budget_type = budget_type

		prefix, digits, middle = mpit_defaults.get_budget_series(
			year=self.year, budget_type=budget_type
		)

		if budget_type == "Live":
			# Deterministic name (no sequence): one Live budget per year.
			existing = frappe.db.get_value("MPIT Budget", {"year": self.year, "budget_type": "Live"}, "name")
			if existing:
				frappe.throw(_("Live budget for year {0} already exists: {1}.").format(self.year, existing))
			self.name = f"{prefix}{self.year}-LIVE"
			return

		series_key = f"{prefix}{middle}.{'#' * digits}"
		sequence = getseries(series_key, digits)
		self.name = f"{prefix}{middle}{sequence}"
	
	def before_validate(self):
		"""Auto-set values before validation runs."""
		self._autofill_cost_centers()

	def validate(self):
		self._enforce_budget_type_rules()
		self._enforce_status_invariants()
		self._enforce_live_no_manual_lines()
		self._enforce_snapshot_manual_line_rules()
		self._compute_lines_amounts()
		self._compute_totals()
		if not getattr(self.flags, "skip_generated_guard", False):
			self._enforce_generated_lines_read_only()

	def _autofill_cost_centers(self) -> None:
		"""Fill cost_center on lines from contract or project if empty."""
		for line in self.lines:
			if line.cost_center:
				continue
			if line.contract:
				line.cost_center = frappe.db.get_value("MPIT Contract", line.contract, "cost_center")
			if not line.cost_center and line.project:
				line.cost_center = frappe.db.get_value("MPIT Project", line.project, "cost_center")

	def _enforce_budget_type_rules(self) -> None:
		"""Validate Live/Snapshot semantics."""
		if not self.budget_type:
			self.budget_type = "Live"

		if self.budget_type == "Live":
			if self.docstatus == 1:
				frappe.throw(_("Live budgets cannot be submitted. Create a Snapshot instead."))
			if self.workflow_state == "Approved":
				frappe.throw(_("Approved status is reserved for Snapshot budgets."))
		elif self.budget_type != "Snapshot":
			frappe.throw(_("Unsupported Budget Type: {0}").format(self.budget_type))
	
	def _enforce_live_no_manual_lines(self) -> None:
		"""Live budgets are system-managed: block manual lines."""
		if self.budget_type != "Live":
			return
		if frappe.flags.in_test and getattr(frappe.flags, "allow_live_manual_lines", False):
			return
		for line in self.lines:
			if not getattr(line, "is_generated", 0):
				frappe.throw(
					_("Live budgets are system-managed. Remove manual line at position {0}.").format(line.idx)
				)

	def _enforce_snapshot_manual_line_rules(self) -> None:
		"""Snapshot budgets allow manual lines only for Allowance while in Draft."""
		if self.budget_type != "Snapshot":
			return
		for line in self.lines:
			if getattr(line, "is_generated", 0):
				continue
			if line.line_kind != "Allowance":
				frappe.throw(
					_("Snapshot budgets allow only Allowance manual lines (row {0}).").format(line.idx)
				)
			if not line.cost_center:
				frappe.throw(_("Snapshot budget line {0}: Cost Center is required.").format(line.idx))

	@frappe.whitelist()
	def refresh_from_sources(self, is_manual: int = 0, reason: str | None = None) -> None:
		"""Generate/refresh Live budget lines from contracts/projects (idempotent).

		Args:
			is_manual: 1 if triggered by user action (allows refresh on closed years)
			reason: optional reason provided by the user for manual refresh
		"""
		if self.budget_type != "Live":
			frappe.throw(_("Only Live budgets can be refreshed."))

		# Reload to ensure we have the latest document version before modifying
		# (avoids TimestampMismatchError when called shortly after other modifications)
		self.reload()

		year_start, year_end = annualization.get_year_bounds(self.year)
		year_closed = self._is_year_closed(year_end)
		manual = bool(cint(is_manual))

		if year_closed and not manual:
			self._add_timeline_comment(_("Auto-refresh skipped: year {0} is closed.").format(self.year))
			return

		if not self._within_horizon():
			self._add_timeline_comment(_("Refresh on out-of-horizon year (manual only): proceed with caution."))

		if year_closed and manual:
			note = reason or _("No reason provided.")
			self._add_timeline_comment(
				_("Manual refresh on closed year by {0}. Reason: {1}").format(frappe.session.user, note)
			)

		generated_lines: list[dict] = []

		generated_lines.extend(self._generate_contract_lines(year_start, year_end))
		generated_lines.extend(self._generate_planned_item_lines(year_start, year_end))

		self._upsert_generated_lines(generated_lines)
		self.flags.skip_generated_guard = True

		# Reload again just before save to get absolute latest version
		# This minimizes the window for concurrent modification errors
		current_lines = list(self.lines)  # Preserve our computed lines
		self.reload()
		self.lines = current_lines  # Restore the lines we just computed

		self.save(ignore_permissions=True)
		self._add_timeline_comment(_("Budget refreshed from sources."))

	def _within_horizon(self) -> bool:
		today = _getdate(nowdate())
		allowed_years = {today.year, today.year + 1}
		try:
			return int(self.year) in allowed_years
		except Exception:
			frappe.log_error(frappe.get_traceback(), f"MPIT Budget _within_horizon: year parse failed for {self.name}")
			return False

	def _is_year_closed(self, year_end: date) -> bool:
		return _getdate(nowdate()) > year_end

	def _add_timeline_comment(self, message: str) -> None:
		try:
			self.add_comment("Comment", message)
		except Exception:
			frappe.log_error(frappe.get_traceback(), "MPIT Budget refresh timeline comment failed")

	def _generate_contract_lines(self, year_start: date, year_end: date) -> list[dict]:
		"""Generate budget lines from contracts with valid status.

		Terms are the single source of truth for pricing. Contracts without terms
		are logged and skipped (should not happen after migration).
		"""
		lines: list[dict] = []
		allowed_status = {"Active", "Pending Renewal", "Renewed"}

		contracts = frappe.get_all(
			"MPIT Contract",
			filters={"status": ["in", list(allowed_status)]},
			fields=[
				"name",
				"status",
				"cost_center",
				"start_date",
				"end_date",
				"vendor",
				"description",
			],
		)

		# Batch-fetch all terms for these contracts to avoid N+1 queries
		contract_names = [c.name for c in contracts]
		all_terms = {}
		if contract_names:
			terms_data = frappe.get_all(
				"MPIT Contract Term",
				filters={"parent": ["in", contract_names]},
				fields=[
					"name", "parent", "from_date", "to_date",
					"amount_net", "monthly_amount_net", "billing_cycle",
					"amount_includes_vat", "vat_rate"
				],
				order_by="parent, from_date asc"
			)
			for term in terms_data:
				all_terms.setdefault(term.parent, []).append(term)

		for contract in contracts:
			if not contract.cost_center:
				frappe.throw(
					_("Contract {0} is missing Cost Center.").format(contract.name)
				)

			terms = all_terms.get(contract.name, [])
			if not terms:
				# After migration this should not happen; log and skip
				frappe.log_error(
					f"Contract {contract.name} has no terms - skipping budget generation",
					"Budget Engine Warning"
				)
				continue

			term_lines = self._generate_contract_term_lines(contract, terms, year_start, year_end)
			lines.extend(term_lines)

		return lines

	def _generate_contract_term_lines(self, contract, terms: list, year_start: date, year_end: date) -> list[dict]:
		"""Generate budget lines for each contract term overlapping the year.

		Terms are the single source of truth for pricing and dates.
		Each term defines its own period; we only clip to fiscal year bounds.
		"""
		lines = []

		for i, term in enumerate(terms):
			term_start = _getdate(term.from_date)

			# Determine term end: use to_date, or next term start - 1, or open-ended (year_end)
			if term.to_date:
				term_end = _getdate(term.to_date)
			elif i + 1 < len(terms):
				next_start = _getdate(terms[i + 1].from_date)
				term_end = add_days(next_start, -1)
			else:
				# Open-ended term: use year_end as upper bound
				term_end = year_end

			# Clip to year bounds only (terms define their own periods)
			period_start = max(term_start, year_start)
			period_end = min(term_end, year_end)

			months = annualization.overlap_months(period_start, period_end, year_start, year_end)
			if months <= 0:
				continue

			monthly_amount = flt(term.monthly_amount_net or term.amount_net or 0, 6)
			billing = term.billing_cycle or "Monthly"
			recurrence_rule = "Monthly" if billing == "Monthly" else billing

			source_key = f"CONTRACT::{contract.name}::TERM::{term.name}"
			lines.append(
				self._build_line_payload(
					contract=contract,
					term=term,
					period_start=period_start,
					period_end=period_end,
					monthly_amount=monthly_amount,
					unit_price=flt(term.amount_net or 0, 6),
					recurrence_rule=recurrence_rule,
					source_key=source_key,
				)
			)
		return lines

	def _generate_planned_item_lines(self, year_start: date, year_end: date) -> list[dict]:
		lines: list[dict] = []
		items = frappe.get_all(
			"MPIT Planned Item",
			filters={"docstatus": 1, "is_covered": 0},
			fields=[
				"name",
				"project",
				"description",
				"amount",
				"amount_net",
				"vat_rate",
				"start_date",
				"end_date",
				"spend_date",
			],
		)
		if not items:
			return lines

		project_map = {
			p.name: p
			for p in frappe.get_all(
				"MPIT Project",
				filters={"name": ["in", [i.project for i in items if i.project]]},
				fields=["name", "title", "workflow_state", "cost_center"],
			)
		}

		# v3 inclusion rules updated for workflow:
		# - workflow_state == "Approved": included (operational_status is ignored)
		# - Draft, Proposed, Rejected, Cancelled: excluded
		allowed_workflow_state = "Approved"

		for item in items:
			project = project_map.get(item.project)
			if not project:
				frappe.throw(_("Planned Item {0}: linked project missing.").format(item.name))
			if project.workflow_state != allowed_workflow_state:
				continue
			if not project.cost_center:
				frappe.throw(
					_("Project {0} is missing Cost Center required by Planned Item {1}.").format(
						project.name, item.name
					)
				)

			periods = self._planned_item_periods(item, year_start, year_end)
			if not periods:
				continue

			for period_start, period_end, monthly_amount in periods:
				source_key = f"PLANNED_ITEM::{item.name}"
				lines.append(
					{
						"line_kind": "Planned Item",
						"source_key": source_key,
						"vendor": None,
						"description": item.description or project.title,
						"contract": None,
						"project": project.name,
						"cost_center": project.cost_center,
						"monthly_amount": monthly_amount,
						"annual_amount": 0,
						# amount_net is already NET - don't apply VAT extraction again
						"amount_includes_vat": 0,
						# Use item's VAT rate for gross/VAT calculation
						"vat_rate": flt(item.vat_rate or 0),
						"recurrence_rule": "None" if item.spend_date else "Monthly",
						"period_start_date": period_start,
						"period_end_date": period_end,
						"is_generated": 1,
					}
				)

		return lines

	def _planned_item_periods(self, item, year_start: date, year_end: date) -> list[tuple[date, date, float]]:
		"""Return list of (period_start, period_end, monthly_amount) respecting spend_date.

		Logic:
		- With spend_date: entire amount allocated to that single month
		- Without spend_date: amount spread evenly across start_date to end_date
		"""
		# Prefer amount_net (computed from VAT), fallback to amount for backward compat
		amount = flt(item.amount_net or item.amount or 0)
		if amount == 0:
			return []

		# Case 1: spend_date = single month allocation
		if item.spend_date:
			spend = _getdate(item.spend_date)
			if spend < year_start or spend > year_end:
				return []
			month_start, month_end = self._month_bounds(spend)
			return [(month_start, month_end, amount)]

		# Case 2: spread across period (start_date → end_date)
		if not item.start_date or not item.end_date:
			return []

		start = _getdate(item.start_date)
		end = _getdate(item.end_date)
		total_months = annualization.overlap_months(start, end, start, end)
		if total_months <= 0:
			return []

		period_start = max(start, year_start)
		period_end = min(end, year_end)
		if period_end < period_start:
			return []

		months_in_year = annualization.overlap_months(period_start, period_end, year_start, year_end)
		if months_in_year <= 0:
			return []

		monthly_amount = amount / total_months
		return [(period_start, period_end, monthly_amount)]

	@staticmethod
	def _month_bounds(dt: date) -> tuple[date, date]:
		last_day = calendar.monthrange(dt.year, dt.month)[1]
		month_start = date(dt.year, dt.month, 1)
		month_end = date(dt.year, dt.month, last_day)
		return month_start, month_end

	def _build_line_payload(
		self, contract, term, period_start: date, period_end: date,
		monthly_amount: float, unit_price: float, recurrence_rule: str, source_key: str
	) -> dict:
		"""Build payload for a contract budget line.

		VAT info is taken from the term (the single source of truth for pricing).
		Note: monthly_amount and unit_price are already NET values from the term
		(term.monthly_amount_net and term.amount_net), so amount_includes_vat
		must be 0 to avoid double VAT extraction in compute_line_amounts().
		"""
		return {
			"line_kind": "Contract",
			"source_key": source_key,
			"vendor": contract.vendor,
			"description": contract.description or contract.name,
			"contract": contract.name,
			"project": None,
			"cost_center": contract.cost_center,
			"monthly_amount": monthly_amount,
			"annual_amount": 0,
			"unit_price": unit_price,
			# Values from term are already NET - don't apply VAT extraction again
			"amount_includes_vat": 0,
			"vat_rate": term.vat_rate if term else 0,
			"recurrence_rule": recurrence_rule,
			"period_start_date": period_start,
			"period_end_date": period_end,
			"is_generated": 1,
		}

	def _upsert_generated_lines(self, generated: list[dict]) -> None:
		existing = {line.source_key: line for line in self.lines if getattr(line, "is_generated", 0)}
		seen = set()
		to_delete = []

		for payload in generated:
			sk = payload.get("source_key")
			if not sk:
				continue
			seen.add(sk)
			if sk in existing:
				line = existing[sk]
				for k, v in payload.items():
					setattr(line, k, v)
			else:
				self.append("lines", payload)

		# delete stale generated lines
		for sk, line in existing.items():
			if sk not in seen:
				to_delete.append(line)

		for line in to_delete:
			self.remove(line)

	def on_update(self):
		if self.is_new():
			return
		if getattr(self.flags, "skip_immutability", False):
			return
		# Snapshots are immutable beyond status invariants
		if self.budget_type == "Snapshot" and self.docstatus == 1 and self.has_value_changed("lines"):
			frappe.throw(_("Snapshot budgets are immutable."))

	def on_trash(self):
		"""Reset series counter if this was the last Snapshot in sequence."""
		if self.budget_type != "Snapshot":
			return
		from master_plan_it.naming_utils import reset_series_on_delete
		prefix, digits, middle = mpit_defaults.get_budget_series(
			year=self.year, budget_type="Snapshot"
		)
		series_prefix = f"{prefix}{middle}"
		reset_series_on_delete(self.name, series_prefix, digits)

	def _enforce_generated_lines_read_only(self) -> None:
		"""Prevent editing generated lines."""
		for line in self.lines:
			if not line.is_generated or not line.name:
				continue
			# fetch persisted row
			existing = frappe.db.get_value(
				"MPIT Budget Line",
				line.name,
				[
					"vendor",
					"description",
					"line_kind",
					"source_key",
					"qty",
					"unit_price",
					"monthly_amount",
					"annual_amount",
					"amount_includes_vat",
					"vat_rate",
					"recurrence_rule",
					"period_start_date",
					"period_end_date",
					"contract",
					"project",
					"cost_center",
				],
				as_dict=True,
			)
			if not existing:
				continue
			for field, old_value in existing.items():
				new_value = line.get(field)
				# normalize date fields (UI may send strings)
				if field in ("period_start_date", "period_end_date"):
					try:
						if _getdate(new_value) == _getdate(old_value):
							continue
					except Exception:
						frappe.log_error(
							f"Date comparison failed: line={line.name}, field={field}",
							"MPIT Budget _enforce_generated_lines_read_only"
						)
				if new_value != old_value:
					frappe.throw(frappe._("Generated line {0} is read-only (field {1}).").format(line.name, field))
	
	def _compute_lines_amounts(self):
		"""Compute all amounts for Budget Lines using bidirectional logic."""
		# Get global defaults once
		default_vat = mpit_defaults.get_default_vat_rate()
		
		# Get fiscal year bounds from year field
		year_start, year_end = annualization.get_year_bounds(self.year)
		
		for line in self.lines:
			if not line.cost_center:
				frappe.throw(_("Line {0}: Cost Center is required.").format(line.idx))
			# Apply VAT rate default if not specified
			if line.vat_rate is None and default_vat is not None:
				line.vat_rate = default_vat
			
			# Validate recurrence rule consistency
			annualization.validate_recurrence_rule(line.recurrence_rule)
			
			# Calculate overlap months for annualization
			if line.period_start_date and line.period_end_date:
				overlap_months_count = annualization.overlap_months(
					line.period_start_date,
					line.period_end_date,
					year_start,
					year_end
				)
				
				# Rule A: Block save if zero overlap
				if overlap_months_count == 0:
					frappe.throw(
						frappe._(
							"Line {0}: Period ({1} to {2}) has zero overlap with fiscal year {3}. Cannot save budget line with no temporal overlap."
						).format(line.idx, line.period_start_date, line.period_end_date, self.year)
					)
			else:
				# No period specified: treat as full year overlap
				overlap_months_count = 12
			
			# Use unified amounts module for all calculations
			result = amounts.compute_line_amounts(
				qty=flt(line.qty) or 1,
				unit_price=flt(line.unit_price),
				monthly_amount=flt(line.monthly_amount),
				annual_amount=flt(line.annual_amount),
				recurrence_rule=line.recurrence_rule or "Monthly",
				vat_rate=flt(line.vat_rate),
				amount_includes_vat=bool(line.amount_includes_vat),
				overlap_months=overlap_months_count
			)
			
			# Update line with calculated values
			line.monthly_amount = result["monthly_amount"]
			line.annual_amount = result["annual_amount"]
			line.amount_net = result["amount_net"]
			line.amount_vat = result["amount_vat"]
			line.amount_gross = result["amount_gross"]
			line.annual_net = result["annual_net"]
			line.annual_vat = result["annual_vat"]
			line.annual_gross = result["annual_gross"]

	def _enforce_status_invariants(self) -> None:
		"""Keep workflow_state aligned with budget type.

		Option B: Snapshot uses workflow for state management, no submit required.
		Live uses auto-set Active/Closed based on year.
		"""
		if not self.workflow_state:
			self.workflow_state = "Draft"

		if self.budget_type == "Snapshot":
			# Workflow manages states: Draft, Proposed, Approved, Rejected
			# Validate that workflow_state is valid for Snapshot
			valid_snapshot_states = {"Draft", "Proposed", "Approved", "Rejected"}
			if self.workflow_state not in valid_snapshot_states:
				self.workflow_state = "Draft"
			# Enforce immutability when not in Draft
			self._enforce_snapshot_workflow_immutability()
		elif self.budget_type == "Live":
			if self.workflow_state == "Approved":
				frappe.throw(_("Approved status is reserved for Snapshot budgets."))
			if self.docstatus == 1:
				frappe.throw(_("Live budgets cannot be submitted."))
			
			# Live budgets do not use Workflow states like Snapshots (Approved/Rejected),
			# but they must respect the Workflow DocType constraints.
			# Since 'Active' and 'Closed' are NOT in the Workflow states, we keep them in 'Draft'.
			# The concept of Active/Closed is implicit based on the year.
			if self.workflow_state != "Draft":
				self.workflow_state = "Draft"

	def _enforce_snapshot_workflow_immutability(self) -> None:
		"""Block editing Snapshot lines when not in Draft state (Option B)."""
		if self.budget_type != "Snapshot":
			return
		if self.workflow_state == "Draft":
			return  # Draft is editable
		if self.is_new():
			return
		# Check if lines were modified
		if self.has_value_changed("lines"):
			frappe.throw(
				_("Snapshot in state '{0}' is read-only. Reopen to edit.").format(self.workflow_state)
			)

	def before_submit(self):
		"""Allow submit of Snapshots without tripping immutability guard.

		Note: With Option B, submit is optional for Snapshot. Workflow manages states.
		"""
		self.flags.skip_immutability = True

	def on_submit(self):
		"""Handle submit for Snapshot budgets.

		With Option B, submit is optional. If used, it's a formal approval marker.
		"""
		if self.budget_type != "Snapshot":
			frappe.throw(_("Only Snapshot budgets can be submitted."))
		# With Option B, submit is optional - workflow manages Approved state
		# If submitting, ensure workflow_state is Approved
		if self.workflow_state != "Approved":
			self.workflow_state = "Approved"
			self.db_set("workflow_state", "Approved")

	def _compute_totals(self):
		total_monthly = 0.0
		total_annual = 0.0
		total_net = 0.0
		total_vat = 0.0
		total_gross = 0.0

		for line in (self.lines or []):
			total_monthly += flt(getattr(line, "monthly_amount", 0) or 0, 2)
			total_annual += flt(getattr(line, "annual_amount", 0) or 0, 2)
			total_net += flt(getattr(line, "annual_net", 0) or 0, 2)
			total_vat += flt(getattr(line, "annual_vat", 0) or 0, 2)
			total_gross += flt(getattr(line, "annual_gross", 0) or 0, 2)

		self.total_amount_annual = flt(total_annual, 2)
		# Use weighted average (Total Net / 12) to provide a consistent monthly burden.
		self.total_amount_monthly = flt(total_net / 12.0, 2)
		self.total_amount_net = flt(total_net, 2)
		self.total_amount_vat = flt(total_vat, 2)
		self.total_amount_gross = flt(total_gross, 2)


def update_budget_totals(budget_name: str) -> None:
	"""Recompute and persist totals for an existing budget without client scripts."""
	if not budget_name:
		return

	budget = frappe.get_doc("MPIT Budget", budget_name)
	budget._compute_totals()

	totals = {
		"total_amount_monthly": flt(budget.total_amount_monthly, 2),
		"total_amount_annual": flt(budget.total_amount_annual, 2),
		"total_amount_net": flt(budget.total_amount_net, 2),
		"total_amount_vat": flt(budget.total_amount_vat, 2),
		"total_amount_gross": flt(budget.total_amount_gross, 2),
	}

	frappe.db.set_value("MPIT Budget", budget_name, totals)


@frappe.whitelist()
def refresh_from_sources(budget: str) -> None:
	"""Public API to refresh a budget from sources."""
	if not budget:
		frappe.throw(_("Budget name is required"))
	doc = frappe.get_doc("MPIT Budget", budget)
	doc.refresh_from_sources()


@frappe.whitelist()
def create_snapshot(source_budget: str) -> str:
	"""Create an immutable Snapshot (APP) from a Live budget.
	
	Args:
		source_budget: Name of the source Live budget
		
	Returns:
		Name of the newly created Snapshot budget
	"""
	if not source_budget:
		frappe.throw(_("Source budget name is required"))

	source = frappe.get_doc("MPIT Budget", source_budget)

	if source.budget_type != "Live":
		frappe.throw(_("Snapshots can only be created from Live budgets."))

	# Create new Snapshot document
	snapshot = frappe.new_doc("MPIT Budget")
	snapshot.budget_type = "Snapshot"
	snapshot.year = source.year
	snapshot.workflow_state = "Draft"

	# Copy lines from source
	for source_line in source.lines:
		new_line = snapshot.append("lines", {})
		for field in source_line.as_dict():
			if field in ("name", "parent", "parenttype", "parentfield", "idx", "creation", "modified", "modified_by", "owner", "docstatus"):
				continue
			new_line.set(field, source_line.get(field))
		# Mark as generated to preserve immutability
		new_line.is_generated = 1

	snapshot.flags.skip_generated_guard = True
	snapshot.flags.skip_immutability = True
	snapshot.insert(ignore_permissions=True)

	# Add timeline comment to both documents
	source.add_comment("Comment", _("Snapshot {0} created from this Live budget.").format(snapshot.name))
	snapshot.add_comment("Comment", _("Created from Live budget {0}.").format(source.name))

	frappe.msgprint(_("Snapshot {0} created successfully.").format(snapshot.name))
	return snapshot.name


@frappe.whitelist()
def get_cap_for_cost_center(year: str, cost_center: str) -> dict:
	"""Calculate Cap for a Cost Center: Snapshot Allowance + approved Addendums.
	
	Args:
		year: MPIT Year name or year string
		cost_center: Cost Center name
		
	Returns:
		dict with snapshot_amount, addendum_total, cap_total
	"""
	if not year or not cost_center:
		frappe.throw(_("Year and Cost Center are required"))

	# Get approved Snapshot for this year
	snapshot_name = frappe.db.get_value(
		"MPIT Budget",
		filters={
			"year": year,
			"budget_type": "Snapshot",
			"docstatus": 1,
		},
		fieldname="name",
		order_by="modified desc",
	)

	snapshot_amount = 0.0
	if snapshot_name:
		# Sum Allowance lines for this cost center from the Snapshot (Query Builder)
		BudgetLine = frappe.qb.DocType("MPIT Budget Line")
		result = (
			frappe.qb.from_(BudgetLine)
			.select(Coalesce(Sum(BudgetLine.annual_net), 0).as_("total"))
			.where(BudgetLine.parent == snapshot_name)
			.where(BudgetLine.cost_center == cost_center)
			.where(BudgetLine.line_kind == "Allowance")
		).run(as_dict=True)
		snapshot_amount = flt(result[0].total if result else 0, 2)

	# Sum approved Addendums for this year + cost center (Query Builder)
	Addendum = frappe.qb.DocType("MPIT Budget Addendum")
	add_result = (
		frappe.qb.from_(Addendum)
		.select(Coalesce(Sum(Addendum.delta_amount), 0).as_("total"))
		.where(Addendum.year == year)
		.where(Addendum.cost_center == cost_center)
		.where(Addendum.docstatus == 1)
	).run(as_dict=True)
	addendum_total = flt(add_result[0].total if add_result else 0, 2)

	cap_total = flt(snapshot_amount + addendum_total, 2)

	return {
		"snapshot_name": snapshot_name,
		"snapshot_amount": snapshot_amount,
		"addendum_total": addendum_total,
		"cap_total": cap_total,
	}


@frappe.whitelist()
def get_cost_center_summary(year: str, cost_center: str) -> dict:
	"""Return plan/cap/actual summary for a cost center in a given year."""
	if not year or not cost_center:
		frappe.throw(_("Year and Cost Center are required"))

	plan = 0.0
	actual = 0.0
	snapshot_amount = 0.0
	addendum_total = 0.0
	cap_total = 0.0
	live_budget = frappe.db.get_value(
		"MPIT Budget",
		{"year": year, "budget_type": "Live", "docstatus": 0},
		"name",
	)
	if live_budget:
		BudgetLine = frappe.qb.DocType("MPIT Budget Line")
		plan_result = (
			frappe.qb.from_(BudgetLine)
			.select(Coalesce(Sum(BudgetLine.annual_net), 0).as_("total"))
			.where(BudgetLine.parent == live_budget)
			.where(BudgetLine.cost_center == cost_center)
		).run(as_dict=True)
		plan = flt(plan_result[0].total if plan_result else 0)

	# Cap (snapshot allowance + addendum)
	snapshot_budget = frappe.db.get_value(
		"MPIT Budget",
		{"year": year, "budget_type": "Snapshot", "docstatus": 1},
		"name",
		order_by="modified desc",
	)
	if snapshot_budget:
		BudgetLine = frappe.qb.DocType("MPIT Budget Line")
		allowance_result = (
			frappe.qb.from_(BudgetLine)
			.select(Coalesce(Sum(BudgetLine.annual_net), 0).as_("total"))
			.where(BudgetLine.parent == snapshot_budget)
			.where(BudgetLine.cost_center == cost_center)
			.where(BudgetLine.line_kind == "Allowance")
		).run(as_dict=True)
		snapshot_amount = flt(allowance_result[0].total if allowance_result else 0)

	Addendum = frappe.qb.DocType("MPIT Budget Addendum")
	add_result = (
		frappe.qb.from_(Addendum)
		.select(Coalesce(Sum(Addendum.delta_amount), 0).as_("total"))
		.where(Addendum.year == year)
		.where(Addendum.cost_center == cost_center)
		.where(Addendum.docstatus == 1)
	).run(as_dict=True)
	addendum_total = flt(add_result[0].total if add_result else 0)
	cap_total = flt(snapshot_amount + addendum_total)

	# Actual (Verified)
	ActualEntry = frappe.qb.DocType("MPIT Actual Entry")
	actual_result = (
		frappe.qb.from_(ActualEntry)
		.select(Coalesce(Sum(ActualEntry.amount_net), 0).as_("total"))
		.where(ActualEntry.year == year)
		.where(ActualEntry.status == "Verified")
		.where(ActualEntry.cost_center == cost_center)
	).run(as_dict=True)
	actual = flt(actual_result[0].total if actual_result else 0)

	remaining = cap_total - actual if cap_total > actual else 0
	over_cap = actual - cap_total if actual > cap_total else 0

	return {
		"year": year,
		"cost_center": cost_center,
		"plan": plan,
		"snapshot_allowance": snapshot_amount,
		"addendum_total": addendum_total,
		"cap_total": cap_total,
		"actual": actual,
		"remaining": remaining,
		"over_cap": over_cap,
		"live_budget": live_budget,
		"snapshot_budget": snapshot_budget,
	}


def enqueue_budget_refresh(years: list[str] | None = None) -> None:
	"""Enqueue refresh for Live budgets in the specified years.
	
	Called by doc_events handlers when sources change.
	Skips years outside rolling horizon (current + next).
	"""
	from frappe.utils import nowdate as _nowdate

	today = _getdate(_nowdate())
	horizon_years = {str(today.year), str(today.year + 1)}

	if years:
		years_to_refresh = [y for y in years if str(y) in horizon_years]
	else:
		years_to_refresh = list(horizon_years)

	if not years_to_refresh:
		return

	# Find Live budgets for these years (include year field to avoid N+1)
	live_budget_rows = frappe.get_all(
		"MPIT Budget",
		filters={
			"budget_type": "Live",
			"year": ["in", years_to_refresh],
			"docstatus": 0,
		},
		fields=["name", "year"],
	)
	live_budgets = [r.name for r in live_budget_rows]

	# Build existing years set from fetched data (no additional queries)
	existing_years = {str(r.year) for r in live_budget_rows}
	missing_years = [y for y in years_to_refresh if str(y) not in existing_years]

	for year in missing_years:
		try:
			doc = frappe.new_doc("MPIT Budget")
			doc.budget_type = "Live"
			doc.year = year
			doc.workflow_state = "Active"
			doc.insert(ignore_permissions=True)
			live_budgets.append(doc.name)
			doc.add_comment("Comment", _("Auto-created Live budget for year {0} (auto-refresh event).").format(year))
		except Exception:
			frappe.log_error(frappe.get_traceback(), f"Failed to auto-create Live budget for year {year}")

	for budget_name in live_budgets:
		try:
			frappe.enqueue(
				"master_plan_it.master_plan_it.doctype.mpit_budget.mpit_budget.refresh_from_sources",
				budget=budget_name,
				queue="short",
				# job_id required when deduplicate=True to avoid duplicate jobs per budget
				job_id=f"mpit-budget-refresh-{budget_name}",
				deduplicate=True,
			)
		except Exception:
			frappe.log_error(
				frappe.get_traceback(),
				f"Failed to enqueue budget refresh for {budget_name}",
			)
