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


RecurrenceRule = Literal["Monthly", "Quarterly", "Annual", "Custom", "None"]


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
		if year_doc.start_date and year_doc.end_date:
			return (getdate(year_doc.start_date), getdate(year_doc.end_date))

	return (calendar_start, calendar_end)


def overlap_months(
	period_start: datetime.date | str,
	period_end: datetime.date | str,
	year_start: datetime.date,
	year_end: datetime.date
) -> int:
	"""
	Calculate number of complete months of overlap between a period and a fiscal year.
	
	Uses conservative counting: only full calendar months count.
	If period starts mid-month or ends mid-month, those partial months are excluded.
	
	Args:
		period_start: Start date of the period
		period_end: End date of the period
		year_start: Start date of the fiscal year
		year_end: End date of the fiscal year
	
	Returns:
		int: Number of complete overlapping months (0 to 12)
	
	Examples:
		>>> # Full year overlap
		>>> overlap_months(date(2025,1,1), date(2025,12,31), date(2025,1,1), date(2025,12,31))
		12
		
		>>> # Q1 overlap (Jan-Mar)
		>>> overlap_months(date(2025,1,1), date(2025,3,31), date(2025,1,1), date(2025,12,31))
		3
		
		>>> # Partial month (mid-Feb to mid-Mar) - conservative counting
		>>> overlap_months(date(2025,2,15), date(2025,3,15), date(2025,1,1), date(2025,12,31))
		0  # No complete months
		
		>>> # No overlap
		>>> overlap_months(date(2024,1,1), date(2024,12,31), date(2025,1,1), date(2025,12,31))
		0
	"""
	period_start = getdate(period_start)
	period_end = getdate(period_end)
	
	# Find the actual overlap window
	overlap_start = max(period_start, year_start)
	overlap_end = min(period_end, year_end)
	
	# No overlap if start > end
	if overlap_start > overlap_end:
		return 0
	
	# Count complete months in overlap window
	# A complete month requires: start on day 1, end on last day of month
	months = 0
	current = overlap_start
	
	while current <= overlap_end:
		# Check if this is the start of a complete month
		month_start = datetime.date(current.year, current.month, 1)
		
		# Calculate last day of this month
		if current.month == 12:
			month_end = datetime.date(current.year, 12, 31)
		else:
			next_month = datetime.date(current.year, current.month + 1, 1)
			month_end = next_month - datetime.timedelta(days=1)
		
		# Check if the entire month is within the overlap window
		if month_start >= overlap_start and month_end <= overlap_end:
			months += 1
		
		# Move to next month
		if current.month == 12:
			current = datetime.date(current.year + 1, 1, 1)
		else:
			current = datetime.date(current.year, current.month + 1, 1)
	
	return months


def annualize(
	amount_net: float,
	recurrence_rule: RecurrenceRule,
	custom_period_months: int | None,
	overlap_months_count: int,
	precision: int = 2
) -> float:
	"""
	Calculate annualized amount based on recurrence rule and overlap.
	
	Args:
		amount_net: The net amount for the period
		recurrence_rule: "Monthly", "Quarterly", "Annual", "Custom", or "None"
		custom_period_months: Number of months for Custom rule (required if rule is Custom)
		overlap_months_count: Number of months of overlap with fiscal year
		precision: Decimal precision (default: 2)
	
	Returns:
		float: Annualized net amount
	
	Raises:
		frappe.ValidationError: If custom_period_months is required but missing
	
	Examples:
		>>> # Monthly: 100/month × 12 months overlap = 1200
		>>> annualize(100, "Monthly", None, 12)
		1200.0
		
		>>> # Quarterly: 300/quarter × 4 quarters (12 months) = 1200
		>>> annualize(300, "Quarterly", None, 12)
		1200.0
		
		>>> # Annual: 1200/year, full overlap = 1200
		>>> annualize(1200, "Annual", None, 12)
		1200.0
		
		>>> # Custom 6-month period: 600/6mo × 2 periods (12 months) = 1200
		>>> annualize(600, "Custom", 6, 12)
		1200.0
		
		>>> # Partial overlap: Monthly 100 × 3 months Q1 only = 300
		>>> annualize(100, "Monthly", None, 3)
		300.0
		
		>>> # None: amount is already annual, just return it
		>>> annualize(1200, "None", None, 12)
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
	
	if recurrence_rule == "Custom":
		if not custom_period_months:
			frappe.throw(
				frappe._("Custom recurrence rule requires custom_period_months to be specified")
			)
		
		# amount_net is per custom period
		# Calculate how many custom periods fit in overlap
		periods = overlap_months_count / float(custom_period_months)
		return flt(amount_net * periods, precision)
	
	# Unknown recurrence rule - treat as None
	return flt(amount_net, precision)


def validate_recurrence_rule(
	recurrence_rule: RecurrenceRule | None,
	custom_period_months: int | None
) -> None:
	"""
	Validate recurrence rule and custom_period_months consistency.
	
	Args:
		recurrence_rule: The recurrence rule value
		custom_period_months: The custom period months value
	
	Raises:
		frappe.ValidationError: If validation fails
	"""
	if recurrence_rule == "Custom" and not custom_period_months:
		frappe.throw(
			frappe._("Custom recurrence rule requires custom_period_months to be specified")
		)
	
	if recurrence_rule != "Custom" and custom_period_months:
		frappe.msgprint(
			frappe._("custom_period_months is only used when recurrence_rule is 'Custom' - value will be ignored"),
			indicator="orange",
			alert=True
		)
	
	if custom_period_months and (custom_period_months < 1 or custom_period_months > 12):
		frappe.throw(
			frappe._("custom_period_months must be between 1 and 12")
		)
