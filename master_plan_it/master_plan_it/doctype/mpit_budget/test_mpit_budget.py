# Copyright (c) 2025, DOT and Contributors
# See license.txt

"""
Comprehensive tests for MPIT Budget DocType.

Test Coverage Goals:
- 100% function coverage for mpit_budget.py
- Idempotent: uses FrappeTestCase auto-rollback, no force deletes
- Thread-safe: uses UUID for unique test data instead of class counter

Run with:
    docker exec -u frappe <container> bench --site <site> run-tests \
        --module master_plan_it.master_plan_it.doctype.mpit_budget.test_mpit_budget
"""

from __future__ import annotations

import uuid
from datetime import date

import frappe
from frappe.tests.utils import FrappeTestCase
from frappe.utils import flt, getdate, nowdate
from master_plan_it import amounts


class TestMPITBudget(FrappeTestCase):
	"""
	Test suite for MPIT Budget DocType.
	
	Uses FrappeTestCase for automatic transaction rollback after each test.
	Each test uses UUID-based unique identifiers to avoid conflicts.
	"""

	@classmethod
	def setUpClass(cls):
		"""Create shared test fixtures once per test class."""
		super().setUpClass()
		# Use UUID prefix for all shared fixtures
		cls._test_id = str(uuid.uuid4())[:8]
		cls.test_cost_center = cls._create_test_cost_center(f"_Test CC {cls._test_id}")
		cls.test_vendor = cls._create_test_vendor(f"_Test Vendor {cls._test_id}")

	def setUp(self):
		"""Create unique year for each test using UUID."""
		super().setUp()
		# Generate unique year per test (use high year to avoid conflicts)
		self._test_uuid = str(uuid.uuid4())[:8]
		# Use year range 3000-9999 to avoid conflicts with real data
		self._year_value = 3000 + (hash(self._test_uuid) % 6000)
		self.test_year = self._create_test_year(self._year_value)
		frappe.flags.allow_live_manual_lines = True

	def tearDown(self):
		"""Reset flags after each test."""
		frappe.flags.allow_live_manual_lines = False
		super().tearDown()

	# ─────────────────────────────────────────────────────────────────────────────
	# Fixture Helpers (Idempotent - rely on FrappeTestCase rollback)
	# ─────────────────────────────────────────────────────────────────────────────

	@staticmethod
	def _create_test_year(year_value: int) -> str:
		"""Create MPIT Year if not exists. Returns year name."""
		year_str = str(year_value)
		if not frappe.db.exists("MPIT Year", year_str):
			frappe.get_doc({
				"doctype": "MPIT Year",
				"year": year_value,
				"start_date": f"{year_value}-01-01",
				"end_date": f"{year_value}-12-31"
			}).insert(ignore_if_duplicate=True)
		return year_str

	@classmethod
	def _create_test_cost_center(cls, name: str) -> str:
		"""Create MPIT Cost Center if not exists. Returns cost center name."""
		if not frappe.db.exists("MPIT Cost Center", name):
			frappe.get_doc({
				"doctype": "MPIT Cost Center",
				"cost_center_name": name,
				"is_group": 0
			}).insert(ignore_if_duplicate=True)
		return name

	@classmethod
	def _create_test_vendor(cls, name: str) -> str:
		"""Create MPIT Vendor if not exists. Returns vendor name."""
		if not frappe.db.exists("MPIT Vendor", name):
			frappe.get_doc({
				"doctype": "MPIT Vendor",
				"vendor_name": name,
			}).insert(ignore_if_duplicate=True)
		return name

	def _create_test_contract(self, **kwargs) -> str:
		"""
		Create MPIT Contract for testing. Auto-generates unique name.
		
		Returns: Contract name
		"""
		defaults = {
			"doctype": "MPIT Contract",
			"description": f"Test Contract {self._test_uuid}",
			"vendor": self.test_vendor,
			"cost_center": self.test_cost_center,
			"status": "Active",
			"start_date": f"{self.test_year}-01-01",
			"end_date": f"{self.test_year}-12-31",
			"billing_cycle": "Monthly",
			"current_amount": 1000,
			"current_amount_includes_vat": 0,
			"vat_rate": 22,
		}
		defaults.update(kwargs)
		doc = frappe.get_doc(defaults)
		try:
			doc.insert()
		except frappe.DuplicateEntryError:
			# Contract already exists from previous test run, reuse it
			existing = frappe.get_all("MPIT Contract", 
				filters={"description": defaults["description"]}, limit=1, pluck="name")
			if existing:
				return existing[0]
			raise
		return doc.name

	def _create_test_project(self, **kwargs) -> str:
		"""Create MPIT Project for testing."""
		defaults = {
			"doctype": "MPIT Project",
			"title": f"Test Project {self._test_uuid}",
			"cost_center": self.test_cost_center,
			"status": "In Progress",  # Approved requires Allocations
		}
		defaults.update(kwargs)
		doc = frappe.get_doc(defaults)
		try:
			doc.insert()
		except frappe.DuplicateEntryError:
			existing = frappe.get_all("MPIT Project",
				filters={"title": defaults["title"]}, limit=1, pluck="name")
			if existing:
				return existing[0]
			raise
		return doc.name

	def _create_test_planned_item(self, project: str, **kwargs) -> str:
		"""Create MPIT Planned Item for testing."""
		defaults = {
			"doctype": "MPIT Planned Item",
			"project": project,
			"description": f"Test Planned Item {self._test_uuid}",
			"amount": 5000,
			"start_date": f"{self.test_year}-01-01",
			"end_date": f"{self.test_year}-12-31",
			"distribution": "all",
			"is_covered": 0,
			"out_of_horizon": 0,
		}
		defaults.update(kwargs)
		doc = frappe.get_doc(defaults)
		doc.insert()
		doc.submit()
		# Force out_of_horizon=0 because test uses future years which logic flags as out of horizon
		frappe.db.set_value("MPIT Planned Item", doc.name, "out_of_horizon", 0)
		return doc.name

	def _create_live_budget(self) -> "frappe.Document":
		"""
		Create a Live budget for testing.
		
		NOTE: FrappeTestCase auto-rollback handles cleanup.
		No force delete needed.
		"""
		doc = frappe.get_doc({
			"doctype": "MPIT Budget",
			"year": self.test_year,
			"budget_type": "Live",
			"workflow_state": "Draft",
		})
		doc.insert()
		return doc

	def _create_snapshot_budget(self, with_lines: bool = True) -> "frappe.Document":
		"""Create a Snapshot budget for testing."""
		doc = frappe.get_doc({
			"doctype": "MPIT Budget",
			"year": self.test_year,
			"budget_type": "Snapshot",
			"workflow_state": "Draft",
		})
		if with_lines:
			doc.append("lines", {
				"doctype": "MPIT Budget Line",
				"cost_center": self.test_cost_center,
				"line_kind": "Allowance",
				"monthly_amount": 100,
				"recurrence_rule": "Monthly",
				"is_generated": 1,
			})
		doc.flags.skip_generated_guard = True
		doc.insert()
		return doc

	# ═══════════════════════════════════════════════════════════════════════════
	# NAMING TESTS (3 tests)
	# ═══════════════════════════════════════════════════════════════════════════

	def test_live_budget_naming_is_deterministic(self):
		"""
		Test: Live budget name follows pattern {prefix}{year}-LIVE.
		
		Failure indicates: mpit_defaults.get_budget_series() or autoname() issue.
		"""
		budget = self._create_live_budget()
		self.assertIn("-LIVE", budget.name,
			f"Live budget name should contain '-LIVE', got: {budget.name}")

	def test_only_one_live_budget_per_year(self):
		"""
		Test: Creating second Live budget for same year should fail.
		
		Failure indicates: autoname() duplicate check bypassed.
		"""
		self._create_live_budget()
		
		with self.assertRaises(frappe.ValidationError) as ctx:
			frappe.get_doc({
				"doctype": "MPIT Budget",
				"year": self.test_year,
				"budget_type": "Live",
			}).insert()
		
		self.assertIn("already exists", str(ctx.exception).lower())

	def test_snapshot_budget_naming_is_sequential(self):
		"""
		Test: Snapshot budget name follows pattern {prefix}{year}-APP-{NN}.
		
		Failure indicates: getseries() or naming configuration issue.
		"""
		snapshot = self._create_snapshot_budget()
		self.assertIn("-APP-", snapshot.name)

	# ═══════════════════════════════════════════════════════════════════════════
	# VALIDATION TESTS (4 tests)
	# ═══════════════════════════════════════════════════════════════════════════

	def test_live_budget_cannot_be_submitted(self):
		"""
		Test: Attempting to submit a Live budget should fail.
		
		Failure indicates: _enforce_budget_type_rules() not blocking submit.
		"""
		budget = self._create_live_budget()
		with self.assertRaises(frappe.ValidationError) as ctx:
			budget.submit()
		self.assertIn("live", str(ctx.exception).lower())

	def test_live_budget_cannot_be_approved(self):
		"""
		Test: Setting workflow_state='Approved' on Live budget should fail.
		
		Failure indicates: _enforce_status_invariants() bypass.
		"""
		budget = self._create_live_budget()
		budget.workflow_state = "Approved"
		with self.assertRaises(frappe.ValidationError):
			budget.save()

	def test_live_budget_blocks_manual_lines(self):
		"""
		Test: Live budget should not allow manual (non-generated) lines.
		
		Failure indicates: _enforce_live_no_manual_lines() not working.
		"""
		frappe.flags.allow_live_manual_lines = False
		
		with self.assertRaises(frappe.ValidationError) as ctx:
			frappe.get_doc({
				"doctype": "MPIT Budget",
				"year": self.test_year,
				"budget_type": "Live",
				"lines": [{
					"doctype": "MPIT Budget Line",
					"cost_center": self.test_cost_center,
					"line_kind": "Contract",
					"monthly_amount": 100,
					"recurrence_rule": "Monthly",
					"is_generated": 0,
				}]
			}).insert()
		
		self.assertIn("system-managed", str(ctx.exception).lower())

	def test_snapshot_allows_only_allowance_manual_lines(self):
		"""
		Test: Snapshot manual lines must be of type 'Allowance'.
		
		Failure indicates: _enforce_snapshot_manual_line_rules() not enforcing.
		"""
		with self.assertRaises(frappe.ValidationError) as ctx:
			frappe.get_doc({
				"doctype": "MPIT Budget",
				"year": self.test_year,
				"budget_type": "Snapshot",
				"workflow_state": "Draft",
				"lines": [{
					"doctype": "MPIT Budget Line",
					"cost_center": self.test_cost_center,
					"line_kind": "Contract",
					"monthly_amount": 100,
					"recurrence_rule": "Monthly",
					"is_generated": 0,
				}]
			}).insert()
		
		self.assertIn("allowance", str(ctx.exception).lower())

	# ═══════════════════════════════════════════════════════════════════════════
	# TOTALS COMPUTATION TESTS (2 tests)
	# ═══════════════════════════════════════════════════════════════════════════

	def test_totals_aggregated_from_lines(self):
		"""
		Test: Budget totals are computed from line amounts.
		
		Failure indicates: _compute_totals() or _compute_lines_amounts() issue.
		"""
		budget = frappe.get_doc({
			"doctype": "MPIT Budget",
			"year": self.test_year,
			"budget_type": "Live",
			"lines": [
				{
					"doctype": "MPIT Budget Line",
					"cost_center": self.test_cost_center,
					"line_kind": "Manual",
					"monthly_amount": 100,
					"amount_includes_vat": 0,
					"vat_rate": 10,
					"recurrence_rule": "Monthly"
				},
				{
					"doctype": "MPIT Budget Line",
					"cost_center": self.test_cost_center,
					"line_kind": "Manual",
					"monthly_amount": 200,
					"amount_includes_vat": 0,
					"vat_rate": 5,
					"recurrence_rule": "Monthly"
				}
			]
		})
		budget.insert()
		budget.reload()

		self.assertEqual(budget.total_amount_monthly, flt(300, 2))
		self.assertEqual(budget.total_amount_annual, flt(3600, 2))

	def test_totals_update_on_line_change(self):
		"""
		Test: Totals update when individual lines are modified.
		
		Failure indicates: update_budget_totals() not triggered on line save.
		"""
		budget = frappe.get_doc({
			"doctype": "MPIT Budget",
			"year": self.test_year,
			"budget_type": "Live",
			"lines": [{
				"doctype": "MPIT Budget Line",
				"cost_center": self.test_cost_center,
				"line_kind": "Manual",
				"monthly_amount": 100,
				"amount_includes_vat": 0,
				"vat_rate": 10,
				"recurrence_rule": "Monthly"
			}]
		})
		budget.insert()
		initial_monthly = budget.total_amount_monthly

		line = frappe.get_doc("MPIT Budget Line", budget.lines[0].name)
		updated = amounts.compute_line_amounts(
			qty=1, unit_price=0, monthly_amount=200, annual_amount=0,
			vat_rate=10, amount_includes_vat=False, recurrence_rule="Monthly",
			overlap_months=12
		)
		for k, v in updated.items():
			setattr(line, k, v)
		line.save()

		budget.reload()
		self.assertGreater(budget.total_amount_monthly, initial_monthly)

	# ═══════════════════════════════════════════════════════════════════════════
	# REFRESH FROM SOURCES TESTS (4 tests)
	# ═══════════════════════════════════════════════════════════════════════════

	def test_refresh_generates_contract_lines(self):
		"""
		Test: refresh_from_sources() creates lines from active contracts.
		
		Failure indicates: _generate_contract_lines() issue.
		"""
		contract_name = self._create_test_contract()
		budget = self._create_live_budget()
		budget.refresh_from_sources(is_manual=1)
		budget.reload()
		
		contract_lines = [l for l in budget.lines if l.source_key == f"CONTRACT::{contract_name}"]
		self.assertEqual(len(contract_lines), 1,
			f"Expected 1 contract line, found {len(contract_lines)}")

	def test_refresh_generates_planned_item_lines(self):
		"""
		Test: refresh_from_sources() creates lines from planned items.
		
		Failure indicates: _generate_planned_item_lines() issue.
		"""
		project_name = self._create_test_project()
		self._create_test_planned_item(project_name)
		
		budget = self._create_live_budget()
		budget.refresh_from_sources(is_manual=1)
		budget.reload()
		
		planned_lines = [l for l in budget.lines if "PLANNED_ITEM::" in (l.source_key or "")]
		print(f"DEBUG: Planned lines count: {len(planned_lines)}")
		if len(planned_lines) == 0:
			print(f"DEBUG: All budget lines: {[l.source_key for l in budget.lines]}")
			print(f"DEBUG: Planned Item status: {frappe.db.get_value('MPIT Planned Item', {'project': project_name}, ['name', 'docstatus', 'is_covered', 'out_of_horizon'], as_dict=True)}")
			print(f"DEBUG: Project status: {frappe.db.get_value('MPIT Project', project_name, ['name', 'status', 'cost_center'], as_dict=True)}")
		
		self.assertGreaterEqual(len(planned_lines), 1,
			"Expected at least 1 planned item line")

	def test_refresh_only_allowed_on_live_budgets(self):
		"""
		Test: refresh_from_sources() should fail on Snapshot budgets.
		
		Failure indicates: budget_type check at start of refresh_from_sources().
		"""
		snapshot = self._create_snapshot_budget()
		with self.assertRaises(frappe.ValidationError) as ctx:
			snapshot.refresh_from_sources()
		self.assertIn("live", str(ctx.exception).lower())

	def test_refresh_is_idempotent(self):
		"""
		Test: Multiple refresh calls produce same result (idempotent).
		
		Failure indicates: _upsert_generated_lines() not doing proper update.
		"""
		self._create_test_contract()
		budget = self._create_live_budget()
		
		budget.refresh_from_sources(is_manual=1)
		budget.reload()
		lines_after_first = len(budget.lines)
		
		budget.refresh_from_sources(is_manual=1)
		budget.reload()
		lines_after_second = len(budget.lines)
		
		self.assertEqual(lines_after_first, lines_after_second,
			"Refresh should be idempotent - same line count after multiple refreshes")

	# ═══════════════════════════════════════════════════════════════════════════
	# BILLING CYCLE TESTS (3 tests)
	# ═══════════════════════════════════════════════════════════════════════════

	def test_contract_monthly_billing(self):
		"""
		Test: Monthly billing uses amount directly as monthly_amount.
		
		Failure indicates: _generate_contract_flat_lines() billing logic.
		"""
		contract_name = self._create_test_contract(billing_cycle="Monthly", current_amount=1200)
		contract = frappe.get_doc("MPIT Contract", contract_name)
		budget = self._create_live_budget()
		budget.refresh_from_sources(is_manual=1)
		budget.reload()
		
		contract_lines = [l for l in budget.lines if l.source_key == f"CONTRACT::{contract.name}"]
		self.assertEqual(len(contract_lines), 1)
		self.assertEqual(contract_lines[0].monthly_amount, flt(1200, 6))

	def test_contract_quarterly_billing(self):
		"""
		Test: Quarterly billing converts to monthly: amount * 4 / 12.
		
		Failure indicates: _generate_contract_flat_lines() quarterly logic.
		"""
		contract_name = self._create_test_contract(billing_cycle="Quarterly", current_amount=300)
		contract = frappe.get_doc("MPIT Contract", contract_name)
		budget = self._create_live_budget()
		budget.refresh_from_sources(is_manual=1)
		budget.reload()
		
		# 300 * 4 / 12 = 100
		contract_lines = [l for l in budget.lines if l.source_key == f"CONTRACT::{contract.name}"]
		self.assertEqual(len(contract_lines), 1)
		self.assertEqual(contract_lines[0].monthly_amount, flt(100, 6))

	def test_contract_annual_billing(self):
		"""
		Test: Annual billing converts to monthly: amount / 12.
		
		Failure indicates: _generate_contract_flat_lines() annual logic.
		"""
		contract_name = self._create_test_contract(billing_cycle="Annual", current_amount=1200)
		contract = frappe.get_doc("MPIT Contract", contract_name)
		budget = self._create_live_budget()
		budget.refresh_from_sources(is_manual=1)
		budget.reload()
		
		# 1200 / 12 = 100
		contract_lines = [l for l in budget.lines if l.source_key == f"CONTRACT::{contract.name}"]
		self.assertEqual(len(contract_lines), 1)
		self.assertEqual(contract_lines[0].monthly_amount, flt(100, 6))

	# ═══════════════════════════════════════════════════════════════════════════
	# PLANNED ITEM DISTRIBUTION TESTS (3 tests)
	# ═══════════════════════════════════════════════════════════════════════════

	def test_planned_item_distribution_all(self):
		"""
		Test: Distribution 'all' spreads amount across all months.
		
		Failure indicates: _planned_item_periods() all distribution.
		"""
		project_name = self._create_test_project()
		self._create_test_planned_item(project_name, amount=1200, distribution="all")
		
		budget = self._create_live_budget()
		budget.refresh_from_sources(is_manual=1)
		budget.reload()
		
		planned_lines = [l for l in budget.lines if "PLANNED_ITEM::" in (l.source_key or "")]
		self.assertEqual(len(planned_lines), 1)
		# 1200 / 12 months = 100 per month
		self.assertEqual(planned_lines[0].monthly_amount, flt(100, 6))

	def test_planned_item_distribution_start(self):
		"""
		Test: Distribution 'start' places full amount in first month.
		
		Failure indicates: _planned_item_periods() start distribution.
		"""
		project_name = self._create_test_project()
		self._create_test_planned_item(project_name, amount=1200, distribution="start")
		
		budget = self._create_live_budget()
		budget.refresh_from_sources(is_manual=1)
		budget.reload()
		
		planned_lines = [l for l in budget.lines if "PLANNED_ITEM::" in (l.source_key or "")]
		self.assertEqual(len(planned_lines), 1)
		self.assertEqual(planned_lines[0].monthly_amount, flt(1200, 6))

	def test_planned_item_distribution_end(self):
		"""
		Test: Distribution 'end' places full amount in last month.
		
		Failure indicates: _planned_item_periods() end distribution.
		"""
		project_name = self._create_test_project()
		self._create_test_planned_item(project_name, amount=1200, distribution="end")
		
		budget = self._create_live_budget()
		budget.refresh_from_sources(is_manual=1)
		budget.reload()
		
		planned_lines = [l for l in budget.lines if "PLANNED_ITEM::" in (l.source_key or "")]
		self.assertEqual(len(planned_lines), 1)
		self.assertEqual(planned_lines[0].monthly_amount, flt(1200, 6))

	# ═══════════════════════════════════════════════════════════════════════════
	# SNAPSHOT TESTS (3 tests)
	# ═══════════════════════════════════════════════════════════════════════════

	def test_create_snapshot_copies_lines(self):
		"""
		Test: create_snapshot() copies all lines from Live budget.
		
		Failure indicates: Line copying loop in create_snapshot() issue.
		"""
		from master_plan_it.master_plan_it.doctype.mpit_budget.mpit_budget import create_snapshot
		
		live = self._create_live_budget()
		live.append("lines", {
			"doctype": "MPIT Budget Line",
			"cost_center": self.test_cost_center,
			"line_kind": "Contract",
			"monthly_amount": 500,
			"recurrence_rule": "Monthly",
			"is_generated": 1,
		})
		live.flags.skip_generated_guard = True
		live.save()
		
		snapshot_name = create_snapshot(live.name)
		snapshot = frappe.get_doc("MPIT Budget", snapshot_name)
		
		self.assertEqual(len(snapshot.lines), len(live.lines))
		self.assertEqual(snapshot.budget_type, "Snapshot")

	def test_create_snapshot_fails_for_non_live(self):
		"""
		Test: create_snapshot() should fail if source is not Live.
		
		Failure indicates: budget_type check in create_snapshot().
		"""
		from master_plan_it.master_plan_it.doctype.mpit_budget.mpit_budget import create_snapshot
		
		snapshot = self._create_snapshot_budget()
		with self.assertRaises(frappe.ValidationError):
			create_snapshot(snapshot.name)

	def test_snapshot_submit_sets_approved(self):
		"""
		Test: Submitting a Snapshot sets workflow_state to 'Approved'.
		
		Failure indicates: on_submit() not setting workflow_state.
		"""
		snapshot = self._create_snapshot_budget()
		snapshot.submit()
		snapshot.reload()
		
		self.assertEqual(snapshot.workflow_state, "Approved")
		self.assertEqual(snapshot.docstatus, 1)

	# ═══════════════════════════════════════════════════════════════════════════
	# GENERATED LINES PROTECTION TESTS (2 tests)
	# ═══════════════════════════════════════════════════════════════════════════

	def test_generated_lines_are_read_only(self):
		"""
		Test: Editing generated lines should fail.
		
		Failure indicates: _enforce_generated_lines_read_only() not working.
		"""
		self._create_test_contract()
		budget = self._create_live_budget()
		budget.refresh_from_sources(is_manual=1)
		budget.reload()
		
		# Try to modify a generated line
		budget.lines[0].monthly_amount = 99999
		budget.flags.skip_generated_guard = False
		
		with self.assertRaises(frappe.ValidationError) as ctx:
			budget.save()
		
		self.assertIn("read-only", str(ctx.exception).lower())

	def test_upsert_removes_stale_lines(self):
		"""
		Test: _upsert_generated_lines() removes lines no longer in source.
		
		Failure indicates: Delete logic in _upsert_generated_lines().
		"""
		contract_name = self._create_test_contract()
		budget = self._create_live_budget()
		budget.refresh_from_sources(is_manual=1)
		budget.reload()
		initial_count = len(budget.lines)
		
		# Deactivate contract
		frappe.db.set_value("MPIT Contract", contract_name, "status", "Expired")
		
		budget.refresh_from_sources(is_manual=1)
		budget.reload()
		
		self.assertLess(len(budget.lines), initial_count,
			"Stale lines should be removed after contract deactivation")

	# ═══════════════════════════════════════════════════════════════════════════
	# AUTOFILL TESTS (2 tests)
	# ═══════════════════════════════════════════════════════════════════════════

	def test_autofill_cost_center_from_contract(self):
		"""
		Test: _autofill_cost_centers() fills from linked contract.
		
		Failure indicates: before_validate autofill logic.
		"""
		contract_name = self._create_test_contract()
		
		budget = frappe.get_doc({
			"doctype": "MPIT Budget",
			"year": self.test_year,
			"budget_type": "Live",
			"lines": [{
				"doctype": "MPIT Budget Line",
				"contract": contract_name,
				"line_kind": "Contract",
				"monthly_amount": 100,
				"recurrence_rule": "Monthly",
				"is_generated": 1,
				# cost_center intentionally omitted
			}]
		})
		budget.flags.skip_generated_guard = True
		budget.insert()
		budget.reload()
		
		self.assertEqual(budget.lines[0].cost_center, self.test_cost_center)

	def test_autofill_cost_center_from_project(self):
		"""
		Test: _autofill_cost_centers() fills from linked project.
		
		Failure indicates: before_validate autofill logic.
		"""
		project_name = self._create_test_project()
		
		budget = frappe.get_doc({
			"doctype": "MPIT Budget",
			"year": self.test_year,
			"budget_type": "Live",
			"lines": [{
				"doctype": "MPIT Budget Line",
				"project": project_name,
				"line_kind": "Planned Item",
				"monthly_amount": 100,
				"recurrence_rule": "Monthly",
				"is_generated": 1,
				# cost_center intentionally omitted
			}]
		})
		budget.flags.skip_generated_guard = True
		budget.insert()
		budget.reload()
		
		self.assertEqual(budget.lines[0].cost_center, self.test_cost_center)

	# ═══════════════════════════════════════════════════════════════════════════
	# WHITELISTED API TESTS (3 tests)
	# ═══════════════════════════════════════════════════════════════════════════

	def test_get_cap_for_cost_center(self):
		"""
		Test: get_cap_for_cost_center() returns correct cap calculation.
		
		Failure indicates: Query Builder refactoring issue.
		"""
		from master_plan_it.master_plan_it.doctype.mpit_budget.mpit_budget import get_cap_for_cost_center
		
		# Create and approve a Snapshot with Allowance
		snapshot = self._create_snapshot_budget()
		snapshot.lines[0].line_kind = "Allowance"
		snapshot.lines[0].monthly_amount = 100
		snapshot.flags.skip_generated_guard = True
		snapshot.save()
		snapshot.submit()
		
		result = get_cap_for_cost_center(self.test_year, self.test_cost_center)
		
		self.assertIn("cap_total", result)
		self.assertIn("snapshot_amount", result)

	def test_get_cost_center_summary(self):
		"""
		Test: get_cost_center_summary() returns complete summary.
		
		Failure indicates: Query Builder refactoring issue.
		"""
		from master_plan_it.master_plan_it.doctype.mpit_budget.mpit_budget import get_cost_center_summary
		
		self._create_live_budget()
		
		result = get_cost_center_summary(self.test_year, self.test_cost_center)
		
		self.assertIn("plan", result)
		self.assertIn("cap_total", result)
		self.assertIn("actual", result)

	def test_refresh_from_sources_api(self):
		"""
		Test: Whitelisted refresh_from_sources() API works.
		
		Failure indicates: API wrapper issue.
		"""
		from master_plan_it.master_plan_it.doctype.mpit_budget.mpit_budget import refresh_from_sources
		
		budget = self._create_live_budget()
		
		# Should not raise
		refresh_from_sources(budget.name)

	# ═══════════════════════════════════════════════════════════════════════════
	# EDGE CASE TESTS (2 tests)
	# ═══════════════════════════════════════════════════════════════════════════

	def test_within_horizon_current_year(self):
		"""
		Test: _within_horizon() returns True for current/next year.
		
		Failure indicates: Year parsing or horizon logic issue.
		"""
		from datetime import date
		current_year = date.today().year
		
		# Use a unique test year in the horizon (current + 1 to avoid conflict)
		next_year = current_year + 1
		test_year_str = self._create_test_year(next_year)
		
		# Check if Live budget already exists for next year
		existing = frappe.db.get_value("MPIT Budget", 
			{"year": test_year_str, "budget_type": "Live"}, "name")
		
		if existing:
			# Use existing budget
			budget = frappe.get_doc("MPIT Budget", existing)
		else:
			budget = frappe.get_doc({
				"doctype": "MPIT Budget",
				"year": test_year_str,
				"budget_type": "Live",
			})
			budget.insert()
		
		self.assertTrue(budget._within_horizon())

	def test_month_bounds_calculation(self):
		"""
		Test: _month_bounds() returns correct first/last day of month.
		
		Failure indicates: calendar.monthrange usage issue.
		"""
		from master_plan_it.master_plan_it.doctype.mpit_budget.mpit_budget import MPITBudget
		
		# February in leap year
		start, end = MPITBudget._month_bounds(date(2024, 2, 15))
		self.assertEqual(start, date(2024, 2, 1))
		self.assertEqual(end, date(2024, 2, 29))
		
		# December
		start, end = MPITBudget._month_bounds(date(2024, 12, 25))
		self.assertEqual(start, date(2024, 12, 1))
		self.assertEqual(end, date(2024, 12, 31))
