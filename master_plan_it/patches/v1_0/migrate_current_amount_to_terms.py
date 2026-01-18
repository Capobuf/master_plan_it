# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

"""
Migra contratti esistenti: current_amount → term nella child table.

Per ogni contratto con current_amount > 0 ma senza terms:
1. Crea un term con from_date = start_date (fallback: 2020-01-01)
2. Copia amount, vat, billing_cycle dal contratto al term

Idempotente: contratti con terms esistenti vengono saltati.

NOTE: This patch checks if legacy columns exist before querying them.
If columns have already been removed from the schema, the patch is skipped.
"""

from __future__ import annotations

import frappe
from frappe.utils import flt, getdate


def execute():
    """Main patch entry point."""
    # Check if legacy columns still exist in the database
    # (they may have been removed by schema sync before patch runs)
    if not _legacy_columns_exist():
        return

    # Find contracts to migrate: have current_amount > 0 but no terms
    contracts_to_migrate = frappe.db.sql(
        """
        SELECT
            c.name,
            c.current_amount,
            c.current_amount_includes_vat,
            c.vat_rate,
            c.billing_cycle,
            c.start_date
        FROM `tabMPIT Contract` c
        LEFT JOIN `tabMPIT Contract Term` t ON t.parent = c.name
        WHERE c.current_amount IS NOT NULL
          AND c.current_amount > 0
        GROUP BY c.name
        HAVING COUNT(t.name) = 0
        """,
        as_dict=True,
    )

    if not contracts_to_migrate:
        return

    migrated = 0
    errors = 0

    for data in contracts_to_migrate:
        try:
            _migrate_contract(data)
            migrated += 1
        except Exception as e:
            frappe.log_error(
                f"Migration failed for contract {data.name}: {e}",
                "Patch: migrate_current_amount_to_terms",
            )
            errors += 1

    frappe.db.commit()

    if migrated or errors:
        frappe.log_error(
            f"Migration complete: {migrated} contracts migrated, {errors} errors",
            "Patch: migrate_current_amount_to_terms - Summary",
        )


def _legacy_columns_exist() -> bool:
    """Check if the legacy current_amount column still exists in the database."""
    try:
        columns = frappe.db.sql(
            """
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = 'tabMPIT Contract'
              AND COLUMN_NAME = 'current_amount'
            """,
            as_dict=True,
        )
        return len(columns) > 0
    except Exception:
        # If we can't check, assume columns don't exist (safe default)
        return False


def _migrate_contract(data: dict) -> None:
    """Create a term for a single contract from its current_amount field."""
    # Determine from_date: use start_date or fallback to 2020-01-01
    if data.start_date:
        from_date = getdate(data.start_date)
    else:
        from_date = getdate("2020-01-01")

    # Create the term child record
    term = frappe.get_doc(
        {
            "doctype": "MPIT Contract Term",
            "parent": data.name,
            "parenttype": "MPIT Contract",
            "parentfield": "terms",
            "from_date": from_date,
            "to_date": None,  # Open-ended
            "amount": flt(data.current_amount, 2),
            "amount_includes_vat": data.current_amount_includes_vat or 0,
            "vat_rate": data.vat_rate,
            "billing_cycle": data.billing_cycle or "Monthly",
            "notes": frappe._("Migrated from contract current_amount field"),
        }
    )
    term.insert(ignore_permissions=True)

    # Reload contract and trigger validate to compute derived fields
    # (amount_net, amount_vat, amount_gross, monthly_amount_net)
    contract = frappe.get_doc("MPIT Contract", data.name)
    contract.flags.ignore_validate_update_after_submit = True
    # Skip the new terms_required validation during migration
    contract.flags.skip_terms_validation = True
    contract.save(ignore_permissions=True)
