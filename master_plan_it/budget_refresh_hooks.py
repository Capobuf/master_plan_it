"""
FILE: master_plan_it/budget_refresh_hooks.py
SCOPO: Handler per doc_events che triggera auto-refresh dei budget Live quando cambiano sorgenti validate.
INPUT: Eventi Frappe (on_update, after_submit, on_cancel, on_trash) su Contract, Planned Item, Addendum.
OUTPUT/SIDE EFFECTS: Enqueue refresh per budget LIVE degli anni nell'orizzonte (current + next); skip per Draft o anni chiusi.
"""

from __future__ import annotations

import frappe
from frappe.utils import getdate, nowdate


def _get_horizon_years() -> set[str]:
    """Return set of year strings within rolling horizon (current year + next)."""
    today = getdate(nowdate())
    return {str(today.year), str(today.year + 1)}


def _extract_years_from_dates(start_date, end_date) -> list[str]:
    """Extract year(s) covered by a date range."""
    years = set()
    if start_date:
        years.add(str(getdate(start_date).year))
    if end_date:
        years.add(str(getdate(end_date).year))
    return list(years)


def _trigger_refresh(years: list[str]) -> None:
    """Enqueue budget refresh for specified years if within horizon."""
    if not years:
        return

    from master_plan_it.master_plan_it.doctype.mpit_budget.mpit_budget import enqueue_budget_refresh

    horizon = _get_horizon_years()
    years_in_horizon = [y for y in years if y in horizon]

    if years_in_horizon:
        enqueue_budget_refresh(years_in_horizon)


# ─────────────────────────────────────────────────────────────────────────────
# Contract handlers
# ─────────────────────────────────────────────────────────────────────────────

VALID_CONTRACT_STATUSES = {"Active", "Pending Renewal", "Renewed"}


def on_contract_change(doc, method: str) -> None:
    """Handle contract changes: trigger refresh only for validated statuses.
    
    Draft/Cancelled/Expired contracts do not trigger refresh (per v3 spec).
    When a contract transitions to/from valid status, refresh removes/adds lines.
    """
    # Skip Draft contracts entirely (they don't impact budget)
    if doc.status == "Draft":
        return

    # Skip Cancelled/Expired unless they were previously valid (transition case)
    if doc.status in ("Cancelled", "Expired"):
        # Check if was previously in valid status (to remove from budget)
        if doc.get_doc_before_save():
            old_status = doc.get_doc_before_save().status
            if old_status not in VALID_CONTRACT_STATUSES:
                return
        else:
            return

    # Extract years from contract period
    years = _extract_years_from_dates(doc.start_date, doc.end_date)

    # If no dates, use current year as fallback
    if not years:
        horizon = _get_horizon_years()
        years = list(horizon)

    _trigger_refresh(years)


# ─────────────────────────────────────────────────────────────────────────────
# Planned Item handlers
# ─────────────────────────────────────────────────────────────────────────────


def on_planned_item_change(doc, method: str) -> None:
    """Handle Planned Item changes: trigger refresh when submitted items change.
    
    Only submitted (docstatus=1) and not covered items affect budget.
    on_update also fires for draft edits - skip those.
    """
    # Skip draft items (docstatus=0) on update - they don't affect budget yet
    if method == "on_update" and doc.docstatus == 0:
        return

    # Skip covered items - they're excluded from budget calculation
    if doc.is_covered:
        return

    # Skip out_of_horizon items
    if doc.out_of_horizon:
        return

    # Extract years from spend_date or period
    years = []
    if doc.spend_date:
        years = [str(getdate(doc.spend_date).year)]
    else:
        years = _extract_years_from_dates(doc.start_date, doc.end_date)

    _trigger_refresh(years)


# ─────────────────────────────────────────────────────────────────────────────
# Addendum handlers
# ─────────────────────────────────────────────────────────────────────────────


def on_addendum_change(doc, method: str) -> None:
    """Handle Budget Addendum submit/cancel: trigger refresh for affected year.
    
    Addendum affects Cap calculation, not Live lines directly.
    But refreshing ensures consistency and re-validates caps.
    """
    if not doc.year:
        return

    # Get year string from Link field
    year_str = str(doc.year)

    _trigger_refresh([year_str])
