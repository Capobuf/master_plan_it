# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

from __future__ import annotations

from datetime import date, timedelta

import frappe
from frappe import _
from frappe.model.document import Document
from frappe.model.naming import getseries
from frappe.utils import flt, getdate as _getdate
from master_plan_it import amounts, annualization, mpit_user_prefs


class MPITBudget(Document):
	def autoname(self):
		"""Generate name: BUD-{year}-{NN} based on Budget.year and user preferences."""
		from master_plan_it import mpit_user_prefs
		
		# Budget.year is mandatory for naming
		if not self.year:
			frappe.throw(_("Year is required to generate Budget name"))
		
		# Get user preferences for prefix and digits
		prefix, digits, middle = mpit_user_prefs.get_budget_series(user=frappe.session.user, year=self.year)
		
		# Generate name: BUD-2025-01, BUD-2025-02, etc.
		# getseries returns only the number part, we need to add prefix + middle
		series_key = f"{prefix}{middle}.####"
		sequence = getseries(series_key, digits)
		self.name = f"{prefix}{middle}{sequence}"
	
	def validate(self):
		self._enforce_status_invariants()
		self._enforce_budget_kind_rules()
		self._autofill_cost_centers()
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

	def _enforce_budget_kind_rules(self) -> None:
		"""Validate Baseline/Forecast semantics and active forecast uniqueness."""
		if not self.budget_kind:
			self.budget_kind = "Forecast"

		if self.budget_kind == "Baseline" and self.is_active_forecast:
			frappe.throw(_("Baseline budget cannot be marked as active forecast."))

		# Uniqueness: one Baseline per year
		if self.budget_kind == "Baseline":
			existing = frappe.db.exists(
				"MPIT Budget",
				{
					"year": self.year,
					"budget_kind": "Baseline",
					"name": ["!=", self.name],
				},
			)
			if existing:
				frappe.throw(_("Only one Baseline is allowed per year ({0}).").format(self.year))

		# Active forecast uniqueness
		if self.budget_kind == "Forecast" and self.is_active_forecast:
			conflict = frappe.db.exists(
				"MPIT Budget",
				{
					"year": self.year,
					"budget_kind": "Forecast",
					"is_active_forecast": 1,
					"name": ["!=", self.name],
				},
			)
			if conflict:
				frappe.throw(_("Only one active Forecast allowed per year. Deactivate other forecasts first."))

	@frappe.whitelist()
	def refresh_from_sources(self) -> None:
		"""Generate/refresh Forecast lines from contracts/projects (idempotent)."""
		if self.budget_kind != "Forecast":
			frappe.throw(_("Only Forecast budgets can be refreshed."))

		year_start, year_end = annualization.get_year_bounds(self.year)
		generated_lines: list[dict] = []

		generated_lines.extend(self._generate_contract_lines(year_start, year_end))
		generated_lines.extend(self._generate_project_lines(year_start, year_end))

		self._upsert_generated_lines(generated_lines)
		self.flags.skip_generated_guard = True
		self.save(ignore_permissions=True)

	def _generate_contract_lines(self, year_start: date, year_end: date) -> list[dict]:
		lines: list[dict] = []
		contracts = frappe.get_all(
			"MPIT Contract",
			fields=[
				"name",
				"title",
				"category",
				"vendor",
				"cost_center",
				"current_amount_net",
				"vat_rate",
				"billing_cycle",
				"start_date",
				"end_date",
				"spread_months",
				"spread_start_date",
				"spread_end_date",
				"status",
			],
		)
		for c in contracts:
			if c.status in ("Cancelled", "Expired"):
				continue
			contract = frappe.get_doc("MPIT Contract", c.name)
			self._require_contract_category(contract)
			if contract.spread_months:
				lines.extend(self._generate_contract_spread_lines(contract, year_start, year_end))
			elif contract.rate_schedule:
				lines.extend(self._generate_contract_rate_lines(contract, year_start, year_end))
			else:
				lines.extend(self._generate_contract_flat_lines(contract, year_start, year_end))
		return lines

	def _generate_contract_spread_lines(self, contract, year_start: date, year_end: date) -> list[dict]:
		lines = []
		if not contract.spread_start_date or not contract.spread_months:
			return lines
		# spread months unbounded; end date already computed on doc
		spread_start = max(_getdate(contract.spread_start_date), year_start)
		spread_end = min(
			_getdate(contract.spread_end_date) if contract.spread_end_date else year_end,
			year_end,
		)
		months = annualization.overlap_months(spread_start, spread_end, year_start, year_end)
		if months <= 0:
			return lines
		monthly_net = flt(contract.current_amount_net or 0) / flt(contract.spread_months or 1)
		source_key = f"CONTRACT_SPREAD::{contract.name}"
		lines.append(
			self._build_line_payload(
				contract=contract,
				period_start=spread_start,
				period_end=spread_end,
				monthly_net=monthly_net,
				source_key=source_key,
			)
		)
		return lines

	def _generate_contract_rate_lines(self, contract, year_start: date, year_end: date) -> list[dict]:
		lines = []
		rows = sorted(contract.rate_schedule, key=lambda r: _getdate(r.effective_from))
		for idx, row in enumerate(rows):
			start = _getdate(row.effective_from)
			if idx + 1 < len(rows):
				next_start = _getdate(rows[idx + 1].effective_from)
				end = next_start - timedelta(days=1)
			else:
				# open-ended
				end = contract.end_date or year_end
			seg_start = max(start, year_start)
			seg_end = min(_getdate(end), year_end)
			months = annualization.overlap_months(seg_start, seg_end, year_start, year_end)
			if months <= 0:
				continue
			source_key = f"CONTRACT_RATE::{contract.name}::{start.isoformat()}"
			lines.append(
				self._build_line_payload(
					contract=contract,
					period_start=seg_start,
					period_end=seg_end,
					monthly_net=flt(row.amount_net or 0),
					source_key=source_key,
				)
			)
		return lines

	def _generate_contract_flat_lines(self, contract, year_start: date, year_end: date) -> list[dict]:
		lines = []
		period_start = max(_getdate(contract.start_date) if contract.start_date else year_start, year_start)
		period_end = min(_getdate(contract.end_date) if contract.end_date else year_end, year_end)
		months = annualization.overlap_months(period_start, period_end, year_start, year_end)
		if months <= 0:
			return lines

		billing = contract.billing_cycle or "Monthly"
		monthly_net = flt(contract.current_amount_net or 0)
		if billing == "Quarterly":
			monthly_net = flt((contract.current_amount_net or 0) * 4 / 12, 2)
		elif billing == "Annual":
			monthly_net = flt((contract.current_amount_net or 0) / 12, 2)
		# Other -> treat as monthly

		source_key = f"CONTRACT::{contract.name}"
		lines.append(
			self._build_line_payload(
				contract=contract,
				period_start=period_start,
				period_end=period_end,
				monthly_net=monthly_net,
				source_key=source_key,
			)
		)
		return lines

	def _require_contract_category(self, contract) -> None:
		if contract.category:
			return
		frappe.throw(
			_("Contract {0} is missing Category. Please set a Category to include it in Forecast refresh.")
			.format(contract.name)
		)

	def _generate_project_lines(self, year_start: date, year_end: date) -> list[dict]:
		lines: list[dict] = []
		projects = frappe.get_all(
			"MPIT Project",
			fields=[
				"name",
				"title",
				"status",
				"start_date",
				"end_date",
				"cost_center",
			],
		)
		for p in projects:
			if p.status in ("Cancelled", "Draft", "Proposed"):
				continue
			project = frappe.get_doc("MPIT Project", p.name)
			lines.extend(self._project_lines_for_year(project, year_start, year_end))
		return lines

	def _project_lines_for_year(self, project, year_start: date, year_end: date) -> list[dict]:
		lines = []
		# Planned dates rules: both or none; fallback full year
		if project.start_date and not project.end_date:
			frappe.throw(_("Project {0}: set both start and end date or clear both.").format(project.name))
		if project.end_date and not project.start_date:
			frappe.throw(_("Project {0}: set both start and end date or clear both.").format(project.name))

		if project.start_date and project.end_date:
			if _getdate(project.end_date) < _getdate(project.start_date):
				frappe.throw(_("Project {0}: end date before start date.").format(project.name))
			period_start = max(_getdate(project.start_date), year_start)
			period_end = min(_getdate(project.end_date), year_end)
		else:
			period_start, period_end = year_start, year_end

		months = annualization.overlap_months(period_start, period_end, year_start, year_end)
		if months <= 0:
			return lines

		planned_by_cat = {}
		for alloc in project.allocations or []:
			if str(alloc.year) != str(self.year):
				continue
			planned_by_cat.setdefault(alloc.category, 0)
			planned_by_cat[alloc.category] += flt(getattr(alloc, "planned_amount_net", None) or alloc.planned_amount or 0)

		quotes_by_cat = {}
		for quote in project.quotes or []:
			if quote.status != "Approved":
				continue
			quotes_by_cat.setdefault(quote.category, 0)
			quotes_by_cat[quote.category] += flt(getattr(quote, "amount_net", None) or quote.amount or 0)

		# Deltas: Verified Delta entries linked to project/year
		deltas = frappe.db.sql(
			"""
			SELECT category, SUM(COALESCE(amount_net, amount)) AS total
			FROM `tabMPIT Actual Entry`
			WHERE project = %(project)s
			  AND status = 'Verified'
			  AND entry_kind = 'Delta'
			  AND year = %(year)s
			GROUP BY category
			""",
			{"project": project.name, "year": self.year},
			as_dict=True,
		)
		deltas_by_cat = {row.category: flt(row.total or 0) for row in deltas or []}

		categories = set(planned_by_cat) | set(quotes_by_cat) | set(deltas_by_cat)
		for cat in categories:
			planned = planned_by_cat.get(cat, 0)
			quoted = quotes_by_cat.get(cat, 0)
			base = quoted if quoted > 0 else planned
			expected = base + deltas_by_cat.get(cat, 0)
			if expected == 0:
				continue
			monthly_net = flt(expected / months, 2)
			source_key = f"PROJECT::{project.name}::{self.year}::{cat}"
			lines.append(
				{
					"line_kind": "Project",
					"source_key": source_key,
					"category": cat,
					"vendor": None,
					"description": project.title,
					"contract": None,
					"project": project.name,
					"cost_center": project.cost_center,
					"monthly_amount": monthly_net,
					"annual_amount": 0,
					"amount_includes_vat": 0,
					"vat_rate": 0,
					"recurrence_rule": "Monthly",
					"period_start_date": period_start,
					"period_end_date": period_end,
					"is_generated": 1,
					"is_active": 1,
				}
			)
		return lines

	def _build_line_payload(self, contract, period_start: date, period_end: date, monthly_net: float, source_key: str) -> dict:
		return {
			"line_kind": "Contract",
			"source_key": source_key,
			"category": contract.category,
			"vendor": contract.vendor,
			"description": contract.title,
			"contract": contract.name,
			"project": None,
			"cost_center": contract.cost_center,
			"monthly_amount": monthly_net,
			"annual_amount": 0,
			"amount_includes_vat": 0,
			"vat_rate": contract.vat_rate,
			"recurrence_rule": "Monthly",
			"period_start_date": period_start,
			"period_end_date": period_end,
			"is_generated": 1,
			"is_active": 1,
		}

	def _upsert_generated_lines(self, generated: list[dict]) -> None:
		existing = {line.source_key: line for line in self.lines if getattr(line, "is_generated", 0)}
		seen = set()

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

		# deactivate stale generated lines
		for sk, line in existing.items():
			if sk not in seen:
				line.is_active = 0

	def on_update(self):
		# For Forecasts, refresh buttons handled client-side; server ensures readonly Baseline
		if self.budget_kind == "Baseline":
			# Prevent accidental edits beyond status invariants
			if self.has_value_changed("lines"):
				frappe.throw(_("Baseline budgets are immutable."))

	def _enforce_generated_lines_read_only(self) -> None:
		"""Prevent editing generated lines except is_active toggle."""
		for line in self.lines:
			if not line.is_generated or not line.name:
				continue
			# fetch persisted row
			existing = frappe.db.get_value(
				"MPIT Budget Line",
				line.name,
				[
					"category",
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
					"cost_type",
					"is_active",
				],
				as_dict=True,
			)
			if not existing:
				continue
			for field, old_value in existing.items():
				if field == "is_active":
					continue  # allow toggling active flag
				if line.get(field) != old_value:
					frappe.throw(frappe._("Generated line {0} is read-only (field {1}).").format(line.name, field))
	
	def _compute_lines_amounts(self):
		"""Compute all amounts for Budget Lines using bidirectional logic."""
		# Get user defaults once
		default_vat = mpit_user_prefs.get_default_vat_rate(frappe.session.user)
		
		# Get fiscal year bounds from year field
		year_start, year_end = annualization.get_year_bounds(self.year)
		
		for line in self.lines:
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
		"""Keep workflow_state aligned with docstatus now that it is an editable status label."""
		if not self.workflow_state:
			self.workflow_state = "Draft"

		if self.docstatus == 0 and self.workflow_state == "Approved":
			frappe.throw(_("Draft Budget cannot be set to Approved. Submit the document to approve it."))

		if self.docstatus == 1 and self.workflow_state != "Approved":
			self.workflow_state = "Approved"

	def on_submit(self):
		# Ensure submitted budgets always reflect Approved status
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
			if line.is_generated:
				# generated lines are read-only; skip inactive ones
				if not getattr(line, "is_active", 1):
					continue
			total_monthly += flt(getattr(line, "monthly_amount", 0) or 0, 2)
			total_annual += flt(getattr(line, "annual_amount", 0) or 0, 2)
			total_net += flt(getattr(line, "annual_net", 0) or 0, 2)
			total_vat += flt(getattr(line, "annual_vat", 0) or 0, 2)
			total_gross += flt(getattr(line, "annual_gross", 0) or 0, 2)

		self.total_amount_monthly = flt(total_monthly, 2)
		self.total_amount_annual = flt(total_annual, 2)
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
def set_active(budget: str | None = None, budget_name: str | None = None) -> None:
	"""Set a Forecast budget as active, deactivating others for the same year."""
	target = budget_name or budget
	if not target:
		frappe.throw(_("Budget name is required"))

	budget = frappe.get_doc("MPIT Budget", target)
	if budget.budget_kind != "Forecast":
		frappe.throw(_("Only Forecast budgets can be set as active."))

	# Deactivate other forecasts for the same year
	frappe.db.sql(
		"""
		UPDATE `tabMPIT Budget`
		SET is_active_forecast = 0
		WHERE year = %(year)s
		  AND budget_kind = 'Forecast'
		  AND name != %(name)s
		""",
		{"year": budget.year, "name": budget.name},
	)

	budget.is_active_forecast = 1
	budget.save(ignore_permissions=True)


@frappe.whitelist()
def refresh_from_sources(budget: str) -> None:
	"""Public API to refresh a budget from sources."""
	if not budget:
		frappe.throw(_("Budget name is required"))
	doc = frappe.get_doc("MPIT Budget", budget)
	doc.refresh_from_sources()
