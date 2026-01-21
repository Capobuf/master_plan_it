"""
Migra Planned Items con distribution='start'/'end' a spend_date.

Questa patch DEVE essere eseguita PRIMA della rimozione del campo distribution
(sezione [pre_model_sync] in patches.txt).

Logica:
- distribution="start" AND spend_date vuoto → spend_date = start_date
- distribution="end" AND spend_date vuoto → spend_date = end_date
- distribution="all" o già con spend_date → nessuna azione

Idempotenza:
- Se eseguita più volte, non modifica record già migrati (spend_date non vuoto)
- Se il campo distribution non esiste più, la patch termina senza errori
"""
import frappe


def execute():
    # Guard: se il campo distribution non esiste più, skip (idempotenza post-migrazione)
    if not frappe.db.has_column("MPIT Planned Item", "distribution"):
        return

    # Trova item da migrare: distribution in (start, end) e spend_date vuoto
    items_to_migrate = frappe.db.sql("""
        SELECT name, distribution, start_date, end_date
        FROM `tabMPIT Planned Item`
        WHERE distribution IN ('start', 'end')
        AND (spend_date IS NULL OR spend_date = '')
        AND docstatus != 2
    """, as_dict=True)

    if not items_to_migrate:
        return

    migrated = 0
    errors = 0

    for item in items_to_migrate:
        try:
            # Determina spend_date in base a distribution
            if item.distribution == "start" and item.start_date:
                new_spend_date = item.start_date
            elif item.distribution == "end" and item.end_date:
                new_spend_date = item.end_date
            else:
                continue  # Skip se date mancanti

            frappe.db.set_value(
                "MPIT Planned Item",
                item.name,
                "spend_date",
                new_spend_date,
                update_modified=False
            )
            migrated += 1
        except Exception as e:
            frappe.log_error(f"Failed to migrate {item.name}: {e}", "migrate_distribution_to_spend_date")
            errors += 1

    frappe.db.commit()

    if migrated > 0 or errors > 0:
        frappe.log_error(
            f"Migration complete: {migrated} migrated, {errors} errors",
            "migrate_distribution_to_spend_date"
        )
