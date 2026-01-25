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


def _extract_years_from_contract(doc) -> list[str]:
    """Extract ALL years covered by contract terms.

    Terms are the single source of truth for pricing and dates.
    Contracts must have at least one term (validated by mpit_contract.py).
    """
    years = set()
    for term in (doc.terms or []):
        if term.from_date:
            years.add(str(getdate(term.from_date).year))
        if term.to_date:
            years.add(str(getdate(term.to_date).year))
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
	prev = doc.get_doc_before_save()
	prev_status = prev.status if prev else None

	# Draft: trigger only on regression from a valid status, else skip
	if doc.status == "Draft":
		if prev_status in VALID_CONTRACT_STATUSES:
			years = _extract_years_from_contract(doc) or list(_get_horizon_years())
			_trigger_refresh(years)
		return

	# Skip Cancelled/Expired unless they were previously valid (transition case)
	if doc.status in ("Cancelled", "Expired"):
		# Check if was previously in valid status (to remove from budget)
		if not prev_status or prev_status not in VALID_CONTRACT_STATUSES:
			return

	# Extract years from contract terms (source of truth)
	years = _extract_years_from_contract(doc)

	# If no terms with dates, use current year as fallback
	if not years:
		horizon = _get_horizon_years()
		years = list(horizon)

	_trigger_refresh(years)


# ─────────────────────────────────────────────────────────────────────────────
# Planned Item handlers
# ─────────────────────────────────────────────────────────────────────────────


def on_planned_item_change(doc, method: str) -> None:
	"""Handle Planned Item changes: trigger refresh for BOTH old and new years.

	Only submitted (workflow_state='Submitted') and not covered items affect budget.
	on_update also fires for draft edits - skip those.
	When dates change, both old and new years must be refreshed to avoid stale data.
	"""
	prev = doc.get_doc_before_save()
	coverage_changed = bool(prev) and prev.is_covered != doc.is_covered

	# Skip draft items on update - they don't affect budget yet
	workflow_state = getattr(doc, 'workflow_state', 'Draft')
	if method == "on_update" and workflow_state == 'Draft' and not coverage_changed:
		return

	# Skip covered items - they're excluded from budget calculation, unless coverage just flipped
	if doc.is_covered and not coverage_changed:
		return

	# Skip out_of_horizon items
	if doc.out_of_horizon:
		return

	# Extract years from BOTH old AND new dates
	years = set()

	# NEW dates
	if doc.spend_date:
		years.add(str(getdate(doc.spend_date).year))
	else:
		if doc.start_date:
			years.add(str(getdate(doc.start_date).year))
		if doc.end_date:
			years.add(str(getdate(doc.end_date).year))

	# OLD dates (to clean up stale budget lines when dates change)
	if prev:
		if prev.spend_date:
			years.add(str(getdate(prev.spend_date).year))
		else:
			if prev.start_date:
				years.add(str(getdate(prev.start_date).year))
			if prev.end_date:
				years.add(str(getdate(prev.end_date).year))

	_trigger_refresh(list(years))


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


# ─────────────────────────────────────────────────────────────────────────────
# Horizon realignment (scheduler)
# ─────────────────────────────────────────────────────────────────────────────


def realign_planned_items_horizon() -> None:
	"""Daily job: bring Planned Items back into budget when they re-enter horizon."""
	horizon = _get_horizon_years()
	items = frappe.get_all(
		"MPIT Planned Item",
		filters={"workflow_state": "Submitted", "out_of_horizon": 1},
		fields=["name", "spend_date", "start_date", "end_date"],
		limit=None,
	)

	if not items:
		return

	affected_years = set()

	for item in items:
		years = set()
		if item.get("spend_date"):
			years.add(str(getdate(item.get("spend_date")).year))
		else:
			if item.get("start_date"):
				years.add(str(getdate(item.get("start_date")).year))
			if item.get("end_date"):
				years.add(str(getdate(item.get("end_date")).year))

		if not years or not (horizon & years):
			continue

		try:
			doc = frappe.get_doc("MPIT Planned Item", item.name)
			doc.out_of_horizon = 0
			doc.save(ignore_permissions=True)
			affected_years.update(y for y in years if y in horizon)
		except Exception:
			frappe.log_error(frappe.get_traceback(), f"Failed to realign Planned Item {item.name}")

	if affected_years:
		_trigger_refresh(list(affected_years))
