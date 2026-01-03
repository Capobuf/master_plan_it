# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

"""
Tax/VAT calculation helpers for MPIT.

Provides strict VAT normalization with 2-decimal precision.
All Currency fields must be split into net/vat/gross components.
"""

from __future__ import annotations

import frappe
from frappe.utils import flt


def split_net_vat_gross(
	amount: float,
	vat_rate_pct: float | None,
	includes_vat: bool,
	precision: int = 2
) -> tuple[float, float, float]:
	"""
	Split an amount into net, vat, and gross components.
	
	Args:
		amount: The input amount (can be net or gross depending on includes_vat)
		vat_rate_pct: VAT rate as percentage (e.g., 22.0 for 22%). Can be None if amount is 0.
		includes_vat: True if amount includes VAT (gross), False if amount is net
		precision: Decimal precision for rounding (default: 2)
	
	Returns:
		tuple: (net, vat, gross) all rounded to specified precision
	
	Examples:
		>>> split_net_vat_gross(100.0, 22.0, False, 2)
		(100.0, 22.0, 122.0)  # Input is net
		
		>>> split_net_vat_gross(122.0, 22.0, True, 2)
		(100.0, 22.0, 122.0)  # Input is gross
		
		>>> split_net_vat_gross(0.0, None, False, 2)
		(0.0, 0.0, 0.0)  # Zero amount
	"""
	# Handle zero amount
	if not amount or flt(amount, precision) == 0:
		return (0.0, 0.0, 0.0)
	
	# VAT rate is required for non-zero amounts (will be validated in controller)
	if vat_rate_pct is None:
		# This will be caught by strict VAT validation in validate()
		# For calculation purposes, treat as 0
		vat_rate_pct = 0.0
	
	# Convert percentage to decimal
	vat_rate = flt(vat_rate_pct, precision) / 100.0
	
	if includes_vat:
		# Input is gross, extract net and vat
		gross = flt(amount, precision)
		net = flt(gross / (1 + vat_rate), precision)
		vat = flt(gross - net, precision)
	else:
		# Input is net, calculate gross and vat
		net = flt(amount, precision)
		gross = flt(net * (1 + vat_rate), precision)
		vat = flt(gross - net, precision)
	
	return (net, vat, gross)


def validate_strict_vat(
	amount: float,
	vat_rate: float | None,
	default_vat_rate: float | None,
	field_label: str = "Amount"
) -> float:
	"""
	Validate strict VAT rules and return the VAT rate to use.
	
	Strict VAT mode rules:
	- If amount != 0: vat_rate is REQUIRED (either on row or from user prefs)
	- If amount == 0: vat_rate can be None (will default to 0)
	- 0 is a valid VAT rate (zero-rated items)
	
	Args:
		amount: The amount being validated
		vat_rate: VAT rate from the row/document (can be None)
		default_vat_rate: Default VAT rate from MPIT Settings (can be None)
		field_label: Label for error messages
	
	Returns:
		float: The VAT rate to use (guaranteed not None)
	
	Raises:
		frappe.ValidationError: If strict VAT rules are violated
	"""
	# If amount is zero, VAT rate is optional (default to 0)
	if not amount or flt(amount, 2) == 0:
		if vat_rate is not None:
			return flt(vat_rate, 2)
		if default_vat_rate is not None:
			return flt(default_vat_rate, 2)
		return 0.0
	
	# Amount is non-zero: VAT rate is REQUIRED
	# Priority: row vat_rate > user default > ERROR
	if vat_rate is not None:
		return flt(vat_rate, 2)
	
	if default_vat_rate is not None:
		return flt(default_vat_rate, 2)
	
	# No VAT rate found: BLOCK save
	frappe.throw(
		frappe._(
			"{0} is non-zero but no VAT rate is specified. Please set a VAT rate on this row or configure a default VAT rate in MPIT Settings."
		).format(field_label)
	)
