# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt
"""
Test script to verify amounts calculation logic.

Run via: bench --site <site> execute master_plan_it.devtools.test_amounts.run
"""

from __future__ import annotations

import frappe


def run():
	"""Test the amounts calculation logic on a baseline expense."""
	print("\n=== Testing Amounts Module ===\n")
	
	# Get a baseline expense to test
	baseline_name = frappe.db.get_value("MPIT Baseline Expense", {}, "name")
	if not baseline_name:
		print("No baseline expense found to test")
		return
	
	baseline = frappe.get_doc("MPIT Baseline Expense", baseline_name)
	print(f"Testing on: {baseline_name}")
	print(f"  Description: {baseline.description[:50] if baseline.description else 'N/A'}...")
	print(f"  Before save:")
	print(f"    qty: {baseline.qty}")
	print(f"    unit_price: {baseline.unit_price}")
	print(f"    monthly_amount: {baseline.monthly_amount}")
	print(f"    annual_amount: {baseline.annual_amount}")
	print(f"    recurrence_rule: {baseline.recurrence_rule}")
	print(f"    vat_rate: {baseline.vat_rate}")
	
	# Force a validate to trigger calculation
	baseline.validate()
	
	print(f"\n  After validate:")
	print(f"    qty: {baseline.qty}")
	print(f"    unit_price: {baseline.unit_price}")
	print(f"    monthly_amount: {baseline.monthly_amount}")
	print(f"    annual_amount: {baseline.annual_amount}")
	print(f"    amount_net: {baseline.amount_net}")
	print(f"    amount_vat: {baseline.amount_vat}")
	print(f"    amount_gross: {baseline.amount_gross}")
	print(f"    annual_net: {baseline.annual_net}")
	print(f"    annual_vat: {baseline.annual_vat}")
	print(f"    annual_gross: {baseline.annual_gross}")
	
	# Test bidirectional: set only monthly, see if annual is calculated
	print(f"\n=== Test Bidirectional Calculation ===")
	print(f"Setting monthly_amount=100, annual_amount=0...")
	
	baseline.monthly_amount = 100
	baseline.annual_amount = 0
	baseline.recurrence_rule = "Monthly"
	baseline.validate()
	
	print(f"  Result:")
	print(f"    monthly_amount: {baseline.monthly_amount}")
	print(f"    annual_amount: {baseline.annual_amount} (expected: 1200)")
	
	# Test reverse: set only annual, see if monthly is calculated
	print(f"\nSetting annual_amount=2400, monthly_amount=0...")
	
	baseline.monthly_amount = 0
	baseline.annual_amount = 2400
	baseline.recurrence_rule = "Monthly"
	baseline.validate()
	
	print(f"  Result:")
	print(f"    monthly_amount: {baseline.monthly_amount} (expected: 200)")
	print(f"    annual_amount: {baseline.annual_amount}")
	
	# Test with qty and unit_price
	print(f"\n=== Test Qty × Unit Price ===")
	print(f"Setting qty=5, unit_price=100, recurrence=Monthly...")
	
	baseline.qty = 5
	baseline.unit_price = 100
	baseline.monthly_amount = 0
	baseline.annual_amount = 0
	baseline.recurrence_rule = "Monthly"
	baseline.validate()
	
	print(f"  Result:")
	print(f"    monthly_amount: {baseline.monthly_amount} (expected: 500)")
	print(f"    annual_amount: {baseline.annual_amount} (expected: 6000)")
	
	print("\n✅ Test completed!")
