"""
FILE: master_plan_it/patches/v3_0/migrate_budget_types.py
SCOPO: Migrare budget esistenti da Baseline/Forecast a Snapshot/Live con nuovo naming LIVE/APP.
INPUT: Budget esistenti con budget_kind='Baseline'/'Forecast'.
OUTPUT/SIDE EFFECTS: Rinomina budget con nuovo naming, aggiorna budget_type, rimuove campi legacy.
"""

from __future__ import annotations

import frappe
from frappe.model.naming import getseries


def execute():
    """Migrate Baseline/Forecast budgets to Snapshot/Live with new naming."""
    frappe.reload_doc("master_plan_it", "doctype", "mpit_budget")
    frappe.reload_doc("master_plan_it", "doctype", "mpit_budget_line")

    # Check if migration is needed (budget_kind field exists)
    if not frappe.db.has_column("MPIT Budget", "budget_kind"):
        frappe.log_error("budget_kind column not found, skipping migration", "Budget v3 Migration")
        return

    # Get all budgets grouped by year
    budgets = frappe.get_all(
        "MPIT Budget",
        fields=["name", "year", "budget_kind", "is_active_forecast", "docstatus", "workflow_state"],
        order_by="year, modified desc",
    )

    if not budgets:
        return

    migrated_count = {"baseline_to_snapshot": 0, "forecast_to_live": 0}

    # Group by year for processing
    by_year: dict[str, list] = {}
    for b in budgets:
        year = str(b.year)
        if year not in by_year:
            by_year[year] = []
        by_year[year].append(b)

    for year, year_budgets in by_year.items():
        # Find baseline for this year -> becomes Snapshot (APP)
        baselines = [b for b in year_budgets if b.budget_kind == "Baseline"]
        forecasts = [b for b in year_budgets if b.budget_kind == "Forecast"]

        # Migrate baselines to Snapshot
        for baseline in baselines:
            new_name = _generate_new_name(year, "Snapshot")
            _migrate_budget(baseline.name, new_name, "Snapshot")
            migrated_count["baseline_to_snapshot"] += 1

        # Migrate active forecast (or most recent) to Live
        active_forecast = next((f for f in forecasts if f.is_active_forecast), None)
        if not active_forecast and forecasts:
            active_forecast = forecasts[0]  # Most recent by modified desc

        if active_forecast:
            new_name = _generate_new_name(year, "Live")
            _migrate_budget(active_forecast.name, new_name, "Live")
            migrated_count["forecast_to_live"] += 1

        # Other forecasts: migrate to Live with incremental sequence
        for forecast in forecasts:
            if forecast.name == active_forecast.name if active_forecast else None:
                continue
            new_name = _generate_new_name(year, "Live")
            _migrate_budget(forecast.name, new_name, "Live")
            migrated_count["forecast_to_live"] += 1

    frappe.db.commit()

    frappe.log_error(
        f"Budget v3 Migration complete: {migrated_count['baseline_to_snapshot']} Baseline→Snapshot, "
        f"{migrated_count['forecast_to_live']} Forecast→Live",
        "Budget v3 Migration",
    )


def _generate_new_name(year: str, budget_type: str) -> str:
    """Generate new name with LIVE/APP token."""
    token = "APP" if budget_type == "Snapshot" else "LIVE"
    prefix = "BUD-"  # Default prefix
    series_key = f"{prefix}{year}-{token}-.####"
    sequence = getseries(series_key, 4)
    return f"{prefix}{year}-{token}-{sequence}"


def _migrate_budget(old_name: str, new_name: str, budget_type: str) -> None:
    """Rename budget and update budget_type field."""
    try:
        # Update budget_type first
        frappe.db.set_value("MPIT Budget", old_name, "budget_type", budget_type, update_modified=False)

        # Rename document (updates all references)
        frappe.rename_doc("MPIT Budget", old_name, new_name, force=True)

        # If Snapshot and submitted, ensure workflow_state is Approved
        if budget_type == "Snapshot":
            doc = frappe.get_doc("MPIT Budget", new_name)
            if doc.docstatus == 1 and doc.workflow_state != "Approved":
                frappe.db.set_value("MPIT Budget", new_name, "workflow_state", "Approved", update_modified=False)

    except Exception as e:
        frappe.log_error(
            f"Failed to migrate budget {old_name} to {new_name}: {str(e)}",
            "Budget v3 Migration Error",
        )
        raise
