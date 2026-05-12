# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

"""
Annualization helpers for MPIT.

Handles fiscal year bounds and month-overlap calculations for expenses and
contract terms. Billing-cycle math lives in the owning DocType/controller.
"""

from __future__ import annotations

import datetime

import frappe
from frappe.utils import getdate, nowdate


def get_horizon_years() -> set[int]:
	"""Return the set of years in the planning horizon (current year + next year).

	This is the single source of truth for the horizon window.
	All code that needs to determine if a year is "in horizon" should use this.
	"""
	today = getdate(nowdate())
	return {today.year, today.year + 1}


@frappe.whitelist()
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
