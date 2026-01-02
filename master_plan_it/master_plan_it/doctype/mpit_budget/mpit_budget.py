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
from frappe.utils import cint, flt, getdate as _getdate, nowdate
from master_plan_it import amounts, annualization, mpit_user_prefs


class MPITBudget(Document):
	def autoname(self):
		"""Generate name:
		
		- Live: `{prefix}{year}-LIVE` (single Live per year)
		- Snapshot: `{prefix}{year}-APP-{NN}`
		
		Prefix/digits are global (MPIT Settings), not per-user.
		"""
		if not self.year:
			frappe.throw(_("Year and Budget Type are required to generate Budget name"))
		budget_type = self.budget_type or "Live"
		self.budget_type = budget_type

		prefix, digits, middle = mpit_user_prefs.get_budget_series(
			user=frappe.session.user, year=self.year, budget_type=budget_type
		)

		if budget_type == "Live":
			# Deterministic name (no sequence): one Live budget per year.
			existing = frappe.db.get_value("MPIT Budget", {"year": self.year, "budget_type": "Live"}, "name")
			if existing:
				frappe.throw(_("Live budget for year {0} already exists: {1}.").format(self.year, existing))
			self.name = f"{prefix}{self.year}-LIVE"
			return

		series_key = f"{prefix}{middle}.####"
		sequence = getseries(series_key, digits)
		self.name = f"{prefix}{middle}{sequence}"
	
	def validate(self):
		self._enforce_budget_type_rules()
		self._enforce_status_invariants()
		self._enforce_live_no_manual_lines()
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

	def _enforce_budget_type_rules(self) -> None:
		"""Validate Live/Snapshot semantics and approval constraints."""
		if not self.budget_type:
			self.budget_type = "Live"

		if self.budget_type == "Live":
			if self.docstatus == 1:
				frappe.throw(_("Live budgets cannot be submitted. Create a Snapshot instead."))
			if self.workflow_state == "Approved":
				frappe.throw(_("Approved status is reserved for Snapshot budgets."))
		elif self.budget_type == "Snapshot":
			if self.docstatus == 0 and self.workflow_state == "Approved":
				frappe.throw(_("Submit the Snapshot to approve it."))
		else:
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

	@frappe.whitelist()
	def refresh_from_sources(self, is_manual: int = 0, reason: str | None = None) -> None:
		"""Generate/refresh Live budget lines from contracts/projects (idempotent).
		
		Args:
			is_manual: 1 if triggered by user action (allows refresh on closed years)
			reason: optional reason provided by the user for manual refresh
		"""
		if self.budget_type != "Live":
			frappe.throw(_("Only Live budgets can be refreshed."))

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
		self.save(ignore_permissions=True)
		self._add_timeline_comment(_("Budget refreshed from sources."))

	def _within_horizon(self) -> bool:
		today = _getdate(nowdate())
		allowed_years = {today.year, today.year + 1}
		try:
			return int(self.year) in allowed_years
		except Exception:
			return False

	def _is_year_closed(self, year_end: date) -> bool:
		return _getdate(nowdate()) > year_end

	def _add_timeline_comment(self, message: str) -> None:
		try:
			self.add_comment("Comment", message)
		except Exception:
			frappe.log_error(frappe.get_traceback(), "MPIT Budget refresh timeline comment failed")

	def _generate_contract_lines(self, year_start: date, year_end: date) -> list[dict]:
		lines: list[dict] = []
		allowed_status = {"Active", "Pending Renewal", "Renewed"}
		contracts = frappe.get_all(
			"MPIT Contract",
			filters={"status": ["in", list(allowed_status)]},
			fields=[
				"name",
				"status",
			],
		)
		for c in contracts:
			contract = frappe.get_doc("MPIT Contract", c.name)
			if not contract.cost_center:
				frappe.throw(_("Contract {0} is missing Cost Center. Please set it to include in Forecast.").format(contract.name))
			# v3: no spread/rate schedule; use flat amount with billing_cycle
			lines.extend(self._generate_contract_flat_lines(contract, year_start, year_end))
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

	def _generate_planned_item_lines(self, year_start: date, year_end: date) -> list[dict]:
		lines: list[dict] = []
		items = frappe.get_all(
			"MPIT Planned Item",
			filters={"docstatus": 1, "is_covered": 0, "out_of_horizon": 0},
			fields=[
				"name",
				"project",
				"description",
				"amount",
				"start_date",
				"end_date",
				"spend_date",
				"distribution",
			],
		)
		if not items:
			return lines

		project_map = {
			p.name: p
			for p in frappe.get_all(
				"MPIT Project",
				filters={"name": ["in", [i.project for i in items if i.project]]},
				fields=["name", "title", "status", "cost_center"],
			)
		}

		# v3 inclusion rules (§7.1 decisions doc):
		# - Approved, In Progress, On Hold: always included
		# - Completed: included only if it has valid Planned Items (enforced by
		#   the outer filter on Planned Items: docstatus=1, is_covered=0, out_of_horizon=0)
		# - Draft, Proposed, Cancelled: excluded
		allowed_status = {"Approved", "In Progress", "On Hold", "Completed"}

		for item in items:
			project = project_map.get(item.project)
			if not project:
				frappe.throw(_("Planned Item {0}: linked project missing.").format(item.name))
			if project.status not in allowed_status:
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
				source_key = f"PLANNED_ITEM::{item.name}::{period_start.isoformat()}"
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

	def _planned_item_periods(self, item, year_start: date, year_end: date) -> list[tuple[date, date, float]]:
		"""Return list of (period_start, period_end, monthly_amount) respecting spend_date/distribution."""
		amount = flt(item.amount or 0)
		if amount == 0:
			return []

		distribution = (item.distribution or "all").lower()

		if item.spend_date:
			spend = _getdate(item.spend_date)
			if spend < year_start or spend > year_end:
				return []
			month_start, month_end = self._month_bounds(spend)
			return [(month_start, month_end, amount)]

		start = _getdate(item.start_date)
		end = _getdate(item.end_date)
		period_start = max(start, year_start)
		period_end = min(end, year_end)
		if period_end < period_start:
			return []

		months = annualization.overlap_months(period_start, period_end, year_start, year_end)
		if months <= 0:
			return []

		if distribution == "start":
			first_month_start, first_month_end = self._month_bounds(period_start)
			return [(first_month_start, first_month_end, amount)]
		if distribution == "end":
			last_month_start, last_month_end = self._month_bounds(period_end)
			return [(last_month_start, last_month_end, amount)]

		monthly_amount = amount / months
		return [(period_start, period_end, monthly_amount)]

	@staticmethod
	def _month_bounds(dt: date) -> tuple[date, date]:
		last_day = calendar.monthrange(dt.year, dt.month)[1]
		month_start = date(dt.year, dt.month, 1)
		month_end = date(dt.year, dt.month, last_day)
		return month_start, month_end

	def _build_line_payload(self, contract, period_start: date, period_end: date, monthly_net: float, source_key: str) -> dict:
		return {
			"line_kind": "Contract",
			"source_key": source_key,
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
		if self.budget_type == "Snapshot" and self.has_value_changed("lines"):
			frappe.throw(_("Snapshot budgets are immutable."))

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
					"cost_type",
					"is_active",
				],
				as_dict=True,
			)
			if not existing:
				continue
			for field, old_value in existing.items():
				if line.get(field) != old_value:
					frappe.throw(frappe._("Generated line {0} is read-only (field {1}).").format(line.name, field))
	
	def _compute_lines_amounts(self):
		"""Compute all amounts for Budget Lines using bidirectional logic."""
		# Get user defaults once
		default_vat = mpit_user_prefs.get_default_vat_rate(frappe.session.user)
		
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
		"""Keep workflow_state aligned with docstatus and budget type."""
		if not self.workflow_state:
			self.workflow_state = "Draft"

		if self.budget_type == "Snapshot":
			if self.docstatus == 0 and self.workflow_state == "Approved":
				frappe.throw(_("Submit the Snapshot to approve it."))
			if self.docstatus == 1 and self.workflow_state != "Approved":
				self.workflow_state = "Approved"
		elif self.budget_type == "Live":
			if self.workflow_state == "Approved":
				frappe.throw(_("Approved status is reserved for Snapshot budgets."))
			if self.docstatus == 1:
				frappe.throw(_("Live budgets cannot be submitted."))

	def on_submit(self):
		# Only Snapshot budgets can be approved/submitted
		if self.budget_type != "Snapshot":
			frappe.throw(_("Only Snapshot budgets can be submitted."))
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
		# Sum Allowance lines for this cost center from the Snapshot
		allowance_sum = frappe.db.sql("""
			SELECT COALESCE(SUM(annual_net), 0) as total
			FROM `tabMPIT Budget Line`
			WHERE parent = %(snapshot)s
			  AND cost_center = %(cc)s
			  AND line_kind = 'Allowance'
		""", {"snapshot": snapshot_name, "cc": cost_center}, as_dict=True)
		snapshot_amount = flt(allowance_sum[0].total if allowance_sum else 0, 2)

	# Sum approved Addendums for this year + cost center
	addendum_sum = frappe.db.sql("""
		SELECT COALESCE(SUM(delta_amount), 0) as total
		FROM `tabMPIT Budget Addendum`
		WHERE year = %(year)s
		  AND cost_center = %(cc)s
		  AND docstatus = 1
	""", {"year": year, "cc": cost_center}, as_dict=True)
	addendum_total = flt(addendum_sum[0].total if addendum_sum else 0, 2)

	cap_total = flt(snapshot_amount + addendum_total, 2)

	return {
		"snapshot_name": snapshot_name,
		"snapshot_amount": snapshot_amount,
		"addendum_total": addendum_total,
		"cap_total": cap_total,
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

	# Find Live budgets for these years
	live_budgets = frappe.get_all(
		"MPIT Budget",
		filters={
			"budget_type": "Live",
			"year": ["in", years_to_refresh],
			"docstatus": 0,
		},
		pluck="name",
	)

	# Auto-create missing Live budgets within horizon
	existing_years = {
		str(frappe.db.get_value("MPIT Budget", name, "year")) for name in live_budgets
	}
	missing_years = [y for y in years_to_refresh if str(y) not in existing_years]

	for year in missing_years:
		try:
			doc = frappe.new_doc("MPIT Budget")
			doc.budget_type = "Live"
			doc.year = year
			doc.workflow_state = "Draft"
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
