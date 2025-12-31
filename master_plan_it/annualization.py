# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

"""
Annualization helpers for MPIT.

Handles temporal calculations for budget lines and baseline expenses
with different recurrence patterns (Monthly, Quarterly, Annual, Custom, None).

Rule A: If a budget line/expense has ZERO overlap with the fiscal year,
        the system MUST block save with a validation error.
"""

from __future__ import annotations

import datetime
from typing import Literal

import frappe
from frappe.utils import flt, getdate


RecurrenceRule = Literal["Monthly", "Quarterly", "Annual", "None"]


def get_year_bounds(year: int | str) -> tuple[datetime.date, datetime.date]:
	"""
	Get the start and end dates for a fiscal year.
	
	Args:
		year: Fiscal year (int or string, e.g., 2025)
	
	Returns:
		tuple: (year_start, year_end) as datetime.date objects
	
	Example:
		>>> get_year_bounds(2025)
		(datetime.date(2025, 1, 1), datetime.date(2025, 12, 31))
	"""
	year_int = int(year)
	calendar_start = datetime.date(year_int, 1, 1)
	calendar_end = datetime.date(year_int, 12, 31)

	if frappe.db.exists("MPIT Year", str(year_int)):
		year_doc = frappe.get_doc("MPIT Year", str(year_int))
		# Dates are mandatory in MPIT Year, so we can trust them.
		return (getdate(year_doc.start_date), getdate(year_doc.end_date))

	return (calendar_start, calendar_end)


def overlap_months(
	period_start: datetime.date | str,
	period_end: datetime.date | str,
	year_start: datetime.date,
	year_end: datetime.date
) -> int:
	"""
	Calculate number of calendar months touched by a period within a fiscal year.
	Partial months count as 1 if any day overlaps.
	"""
	period_start = getdate(period_start)
	period_end = getdate(period_end)

	overlap_start = max(period_start, year_start)
	overlap_end = min(period_end, year_end)

	if overlap_start > overlap_end:
		return 0

	months = set()
	current = datetime.date(overlap_start.year, overlap_start.month, 1)
	while current <= overlap_end:
		months.add((current.year, current.month))
		# move to first day of next month
		if current.month == 12:
			current = datetime.date(current.year + 1, 1, 1)
		else:
			current = datetime.date(current.year, current.month + 1, 1)

	return len(months)


def annualize(
	amount_net: float,
	recurrence_rule: RecurrenceRule,
	overlap_months_count: int,
	precision: int = 2
) -> float:
	"""
	Calculate annualized amount based on recurrence rule and overlap.
	
	Args:
		amount_net: The net amount for the period
		recurrence_rule: "Monthly", "Quarterly", "Annual", or "None"
		overlap_months_count: Number of months of overlap with fiscal year
		precision: Decimal precision (default: 2)
	
	Returns:
		float: Annualized net amount
	Examples:
		>>> # Monthly: 100/month × 12 months overlap = 1200
		>>> annualize(100, "Monthly", 12)
		1200.0
		
		>>> # Quarterly: 300/quarter × 4 quarters (12 months) = 1200
		>>> annualize(300, "Quarterly", 12)
		1200.0
		
		>>> # Annual: 1200/year, full overlap = 1200
		>>> annualize(1200, "Annual", 12)
		1200.0
		
		>>> # Partial overlap: Monthly 100 × 3 months Q1 only = 300
		>>> annualize(100, "Monthly", 3)
		300.0
		
		>>> # None: amount is already annual, just return it
		>>> annualize(1200, "None", 12)
		1200.0
	"""
	if overlap_months_count == 0:
		# Rule A enforcement happens in controller validate()
		return 0.0
	
	if recurrence_rule == "None":
		# Amount is already annual (no recurrence)
		return flt(amount_net, precision)
	
	if recurrence_rule == "Monthly":
		# amount_net is per-month, multiply by overlap months
		return flt(amount_net * overlap_months_count, precision)
	
	if recurrence_rule == "Quarterly":
		# amount_net is per-quarter (3 months)
		# Calculate number of complete quarters in overlap
		quarters = overlap_months_count / 3.0
		return flt(amount_net * quarters, precision)
	
	if recurrence_rule == "Annual":
		# amount_net is per-year (12 months)
		# Pro-rate based on overlap
		return flt(amount_net * (overlap_months_count / 12.0), precision)
	
	# Unknown recurrence rule - treat as None
	return flt(amount_net, precision)


def validate_recurrence_rule(
	recurrence_rule: RecurrenceRule | None,
) -> None:
	"""
	Validate recurrence rule consistency (supported set only).
	"""
	allowed = {"Monthly", "Quarterly", "Annual", "None", None}
	if recurrence_rule not in allowed:
		frappe.throw(frappe._("Unsupported recurrence rule: {0}").format(recurrence_rule))
