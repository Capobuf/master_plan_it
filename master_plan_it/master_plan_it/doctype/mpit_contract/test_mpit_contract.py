# Copyright (c) 2025, DOT and Contributors
# See license.txt

from datetime import date

import frappe
from frappe.tests.utils import FrappeTestCase
from frappe.utils import flt, now_datetime


class TestMPITContract(FrappeTestCase):
	def setUp(self):
		super().setUp()
		self.vendor = self._ensure_vendor("Test Contract Vendor")
		self.cost_center = self._ensure_cost_center("Test Contract CC")

	# ─────────────────────────────────────────────────────────────────────────
	# Budget-related fixture helpers (used only by rollback integration test)
	# ─────────────────────────────────────────────────────────────────────────

	@staticmethod
	def _ensure_mpit_year(year_value: int) -> str:
		"""Create an MPIT Year record for the given numeric year if absent."""
		year_str = str(year_value)
		if not frappe.db.exists("MPIT Year", year_str):
			frappe.get_doc({
				"doctype": "MPIT Year",
				"year": year_value,
				"start_date": f"{year_value}-01-01",
				"end_date": f"{year_value}-12-31",
			}).insert(ignore_if_duplicate=True)
		return year_str

	def _create_live_budget_with_generated_line(
		self, year_str: str, contract_name: str, annual_net: float = 1200.0
	) -> tuple:
		"""Insert a Live budget with one generated line linked to *contract_name*.

		The line is injected via frappe.flags.allow_live_manual_lines so that
		the Live-budget-no-manual-lines guard is bypassed intentionally.
		Caller is responsible for setting/resetting that flag.

		Returns (budget_name, line_name).
		"""
		budget = frappe.get_doc({
			"doctype": "MPIT Budget",
			"year": year_str,
			"budget_type": "Live",
			"workflow_state": "Draft",
		})
		budget.flags.skip_generated_guard = True
		budget.append("lines", {
			"doctype": "MPIT Budget Line",
			"cost_center": self.cost_center.name,
			"line_kind": "Contract",
			"contract": contract_name,
			"is_generated": 1,
			"source_key": f"contract:{contract_name}",
			"recurrence_rule": "Annual",
			"monthly_amount": flt(annual_net / 12, 2),
			"annual_amount": annual_net,
			"annual_net": annual_net,
			"annual_vat": 0,
			"annual_gross": annual_net,
		})
		budget.insert()
		budget.reload()
		line_name = budget.lines[0].name
		return budget.name, line_name

	def _ensure_vendor(self, name: str):
		if not frappe.db.exists("MPIT Vendor", name):
			frappe.get_doc(
				{
					"doctype": "MPIT Vendor",
					"vendor_name": name,
				}
			).insert(ignore_if_duplicate=True)
		return frappe.get_doc("MPIT Vendor", name)

	def _ensure_cost_center(self, name: str):
		if not frappe.db.exists("MPIT Cost Center", name):
			frappe.get_doc(
				{
					"doctype": "MPIT Cost Center",
					"cost_center_name": name,
					"is_group": 0,
				}
			).insert(ignore_if_duplicate=True)
		return frappe.get_doc("MPIT Cost Center", name)

	def _make_contract(
		self,
		description_suffix: str,
		amount: float,
		billing_cycle: str = "Monthly",
		start_date: str = "2025-01-01",
	):
		"""Create a contract with a single term (terms are the source of truth)."""
		timestamp = now_datetime().strftime("%Y%m%d%H%M%S%f")
		description = f"Contract {description_suffix} {timestamp}"
		doc = frappe.get_doc(
			{
				"doctype": "MPIT Contract",
				"description": description,
				"vendor": self.vendor.name,
				"cost_center": self.cost_center.name,
				"auto_renew": 0,
				"start_date": start_date,
				"terms": [
					{
						"from_date": start_date,
						"amount": amount,
						"amount_includes_vat": 0,
						"vat_rate": 0,
						"billing_cycle": billing_cycle,
					}
				],
			}
		)

		doc.insert()
		doc.reload()
		return doc

	def test_monthly_amount_from_billing_cycle(self):
		"""Term monthly_amount_net is computed from billing_cycle."""
		monthly = self._make_contract("Monthly", amount=100, billing_cycle="Monthly")
		self.assertEqual(monthly.terms[0].monthly_amount_net, flt(100, 2))

		quarterly = self._make_contract("Quarterly", amount=1200, billing_cycle="Quarterly")
		# Quarterly: amount * 4 / 12 = 1200 * 4 / 12 = 400
		self.assertEqual(quarterly.terms[0].monthly_amount_net, flt(400, 2))

		annual = self._make_contract("Annual", amount=1200, billing_cycle="Annual")
		# Annual: amount / 12 = 1200 / 12 = 100
		self.assertEqual(annual.terms[0].monthly_amount_net, flt(100, 2))

	def test_auto_renew_contract_stays_active(self):
		"""Auto-renew contracts should not have Pending Renewal status."""
		contract = self._make_contract("AutoRenew", amount=100, billing_cycle="Monthly")
		contract.status = "Pending Renewal"
		contract.auto_renew = 1
		contract.save()
		contract.reload()
		self.assertEqual(contract.status, "Active")

	def test_contract_requires_at_least_one_term(self):
		"""Contract without terms should fail validation."""
		with self.assertRaises(frappe.ValidationError):
			frappe.get_doc(
				{
					"doctype": "MPIT Contract",
					"vendor": self.vendor.name,
					"cost_center": self.cost_center.name,
					"start_date": "2025-01-01",
					"terms": [],
				}
			).insert()

	def test_contract_requires_vendor(self):
		"""Contract without vendor should fail validation."""
		with self.assertRaises(frappe.ValidationError):
			frappe.get_doc(
				{
					"doctype": "MPIT Contract",
					"cost_center": self.cost_center.name,
					"terms": [
						{
							"from_date": "2025-01-01",
							"amount": 100,
							"billing_cycle": "Monthly",
							"vat_rate": 0,
						}
					],
				}
			).insert()

	def test_overlapping_terms_rejected(self):
		"""Overlapping date ranges should fail validation."""
		with self.assertRaises(frappe.ValidationError):
			frappe.get_doc(
				{
					"doctype": "MPIT Contract",
					"vendor": self.vendor.name,
					"cost_center": self.cost_center.name,
					"start_date": "2025-01-01",
					"terms": [
						{
							"from_date": "2025-01-01",
							"to_date": "2025-06-30",
							"amount": 100,
							"billing_cycle": "Monthly",
						},
						{
							"from_date": "2025-06-01",
							"amount": 120,
							"billing_cycle": "Monthly",
						},
					],
				}
			).insert()

	def test_annual_summary_computed(self):
		"""Annual summaries should reflect terms."""
		current_year = date.today().year
		contract = frappe.get_doc(
			{
				"doctype": "MPIT Contract",
				"vendor": self.vendor.name,
				"cost_center": self.cost_center.name,
				"start_date": f"{current_year}-01-01",
				"terms": [
					{
						"from_date": f"{current_year}-01-01",
						"amount": 100,
						"billing_cycle": "Monthly",
						"vat_rate": 0,
					}
				],
			}
		)
		contract.insert()
		# 12 months × 100 = 1200
		self.assertEqual(contract.annual_amount_current_year, 1200)

	def test_current_term_identified(self):
		"""Current term should be identified based on today's date."""
		current_year = date.today().year
		contract = frappe.get_doc(
			{
				"doctype": "MPIT Contract",
				"vendor": self.vendor.name,
				"cost_center": self.cost_center.name,
				"start_date": f"{current_year}-01-01",
				"terms": [
					{
						"from_date": f"{current_year}-01-01",
						"amount": 100,
						"billing_cycle": "Monthly",
					},
					{
						"from_date": f"{current_year + 1}-06-01",
						"amount": 150,
						"billing_cycle": "Monthly",
					},
				],
			}
		)
		contract.insert()
		# If we are in the current year, the active term is the first one
		self.assertEqual(contract.current_term_amount, 100)
		self.assertEqual(contract.current_term_billing_cycle, "Monthly")

	def test_open_ended_term_respects_status(self):
		"""Open-ended terms should not be treated as active when status is inactive."""
		current_year = date.today().year
		contract = frappe.get_doc(
			{
				"doctype": "MPIT Contract",
				"vendor": self.vendor.name,
				"cost_center": self.cost_center.name,
				"status": "Cancelled",
				"auto_renew": 0,
				"terms": [
					{
						"from_date": f"{current_year}-01-01",
						"amount": 100,
						"billing_cycle": "Monthly",
						"vat_rate": 0,
					}
				],
			}
		)
		contract.flags.skip_terms_auto_compute = True
		contract.insert()
		self.assertIsNone(contract.current_term_amount)

	def test_recompute_failure_propagates_not_swallowed(self):
		"""Budget recompute failure during cleanup must propagate, not be swallowed.

		Sequence in _cleanup_linked_budget_lines:
		  1. SQL DELETE removes linked budget lines from the DB.
		  2. frappe.get_doc + _compute_totals + db_update refreshes budget totals.

		If step 2 fails, the exception MUST propagate out of the cleanup method
		and ultimately out of on_trash.  Frappe will then abort the entire
		transaction — both the line deletions and the contract deletion are rolled
		back atomically.  No partial state (deleted lines but stale totals) should
		ever be committed.

		This test patches frappe.db.sql to inject a fake linked budget line and
		patches frappe.get_doc to return a mock budget whose _compute_totals raises.
		The goal is purely to verify exception propagation — not DB state — because
		the rollback guarantee is owned by the framework transaction layer.
		"""
		from unittest.mock import MagicMock, patch

		contract = self._make_contract("PropagateTest", amount=100)

		fake_lines = [
			{"line_name": "FAKE-BL-001", "budget_name": "FAKE-BUD-001", "budget_type": "Live"}
		]

		mock_budget = MagicMock()
		mock_budget._compute_totals.side_effect = frappe.ValidationError(
			"Simulated recompute failure"
		)

		def patched_sql(query, *args, **kwargs):
			if "SELECT" in query.upper():
				return fake_lines
			return None  # DELETE statement — no useful return value

		original_get_doc = frappe.get_doc

		def patched_get_doc(doctype, *args, **kwargs):
			if doctype == "MPIT Budget":
				return mock_budget
			return original_get_doc(doctype, *args, **kwargs)

		with patch("frappe.db.sql", side_effect=patched_sql), \
				patch("frappe.get_doc", side_effect=patched_get_doc):
			with self.assertRaises(frappe.ValidationError) as ctx:
				contract._cleanup_linked_budget_lines()

		self.assertIn("Simulated recompute failure", str(ctx.exception))

	def test_failed_recompute_rolls_back_all_changes(self):
		"""Integration test: proves full atomic rollback when budget recompute fails.

		This test builds a minimal real dataset — an MPIT Year, a Live budget with one
		generated line linked to a contract — and exercises the full document-deletion
		path.  A savepoint is set immediately before the attempted deletion to simulate
		what the Frappe request handler does when delete_doc() propagates an exception:
		it calls frappe.db.rollback() to undo all writes made during that request.

		After the savepoint rollback the test asserts:
		  1. The exception propagated (was not swallowed by the cleanup path).
		  2. The contract still exists (delete_from_table was never reached).
		  3. The linked budget line still exists (the raw SQL DELETE was rolled back).
		  4. Budget totals are unchanged (db_update was never reached).

		This goes beyond the narrow unit test (test_recompute_failure_propagates_not_swallowed)
		which only verifies exception propagation through _cleanup_linked_budget_lines in
		isolation.  This test verifies the database state after the framework-level rollback.

		Why savepoint rather than asserting on the live transaction state:
		  After on_trash raises, the raw SQL DELETE has been issued but not committed.
		  Within the same MariaDB connection the DELETE is visible (REPEATABLE READ at the
		  statement level for InnoDB, but our own writes are always visible to us).
		  Rolling back to the savepoint restores the database to the pre-attempt state,
		  which is what the framework would do on a real request.  Querying after the
		  rollback therefore mirrors the state any subsequent request would observe.
		"""
		import uuid
		from unittest.mock import patch
		from master_plan_it.master_plan_it.doctype.mpit_budget.mpit_budget import MPITBudget

		# Use a far-future year that is extremely unlikely to conflict with any other
		# test or fixture.  Hash the UUID to stay in a deterministic numeric range.
		year_val = 7000 + (hash(str(uuid.uuid4())) % 1000)
		year_str = self._ensure_mpit_year(year_val)

		contract = self._make_contract(
			"RollbackTest",
			amount=1200,
			billing_cycle="Annual",
			start_date=f"{year_val}-01-01",
		)
		contract_name = contract.name

		# Create a Live budget with a generated line linked to the contract.
		# allow_live_manual_lines bypasses the Live-budget immutability guard so
		# the test can inject the generated line without running the full budget engine.
		frappe.flags.allow_live_manual_lines = True
		try:
			budget_name, line_name = self._create_live_budget_with_generated_line(
				year_str, contract_name, annual_net=1200.0
			)
		finally:
			frappe.flags.allow_live_manual_lines = False

		# Record the budget's total_amount_net as computed during insert; this is
		# the value that must be preserved unchanged after a failed deletion attempt.
		budget = frappe.get_doc("MPIT Budget", budget_name)
		initial_total_net = budget.total_amount_net

		# Sanity: both records must be visible before the attempted deletion.
		self.assertTrue(frappe.db.exists("MPIT Contract", contract_name))
		self.assertTrue(frappe.db.exists("MPIT Budget Line", line_name))

		# Set a savepoint that mirrors the frame the Frappe request handler
		# establishes around each DELETE/POST request.  Rolling back to it after
		# the failed deletion simulates what frappe.db.rollback() would do when the
		# framework catches the propagated exception from delete_doc().
		frappe.db.savepoint("pre_delete_attempt")

		raised_exc = None
		try:
			# Patch _compute_totals on the class so that every MPITBudget instance
			# raises during the cleanup phase.  The patch is scoped to this block
			# only, so no other test infrastructure is affected.
			with patch.object(
				MPITBudget,
				"_compute_totals",
				side_effect=frappe.ValidationError("Simulated recompute failure"),
			):
				contract.delete()
		except frappe.ValidationError as exc:
			raised_exc = exc

		# ── Assertion 1: exception must have propagated ──────────────────────────
		self.assertIsNotNone(
			raised_exc,
			"Expected ValidationError did not propagate from contract.delete() — "
			"the cleanup path is swallowing exceptions, leaving partial state unsafe",
		)
		self.assertIn("Simulated recompute failure", str(raised_exc))

		# Simulate the framework's rollback.  This is the step that makes all DB
		# writes issued during the failed deletion attempt invisible to subsequent
		# queries — exactly as they would be invisible to any other request after
		# the server-side rollback.
		frappe.db.rollback(save_point="pre_delete_attempt")

		# ── Assertion 2: contract must still exist ───────────────────────────────
		# delete_from_table (the step that actually removes the contract row) runs
		# AFTER on_trash.  Because on_trash raised, delete_from_table was never
		# called, so even without the rollback the contract should not be deleted.
		# The rollback confirms this is the case as seen by fresh DB reads.
		self.assertTrue(
			frappe.db.exists("MPIT Contract", contract_name),
			"Contract was deleted despite recompute failure — "
			"delete_from_table ran even though on_trash raised",
		)

		# ── Assertion 3: linked budget line must still exist ─────────────────────
		# The raw SQL DELETE inside _cleanup_linked_budget_lines happened BEFORE
		# _compute_totals raised.  Without a rollback the line would be gone.
		# This assertion is the key proof that the rollback restores it.
		self.assertTrue(
			frappe.db.exists("MPIT Budget Line", line_name),
			"Budget line no longer exists after rollback — "
			"the raw SQL DELETE was committed despite the subsequent exception, "
			"leaving the budget with deleted lines but stale totals",
		)

		# ── Assertion 4: budget totals must be unchanged ──────────────────────────
		# db_update() for the budget was never called (it comes after _compute_totals).
		# After the rollback, the persisted total must equal the value from insert.
		budget_after = frappe.get_doc("MPIT Budget", budget_name)
		budget_after.reload()
		self.assertEqual(
			budget_after.total_amount_net,
			initial_total_net,
			f"Budget total_amount_net changed after rollback "
			f"(expected {initial_total_net}, got {budget_after.total_amount_net}) — "
			"partial db_update was committed",
		)

	def test_delete_contract_with_no_linked_lines_is_safe(self):
		"""Deleting a contract that has no linked budget lines should complete cleanly."""
		contract = self._make_contract("DeleteNoLines", amount=75, billing_cycle="Monthly")
		contract_name = contract.name
		contract.delete()
		self.assertFalse(frappe.db.exists("MPIT Contract", contract_name))
