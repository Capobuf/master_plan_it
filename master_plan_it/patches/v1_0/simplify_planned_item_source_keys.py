"""
Patch: Simplify source_key for Planned Item budget lines.

Before: PLANNED_ITEM::{item.name}::{period_start}
After:  PLANNED_ITEM::{item.name}

This prevents duplicates when spend_date is changed on a Planned Item.
The old format includes a date suffix that changes when dates are modified,
causing the upsert logic to create new lines instead of updating existing ones.
"""
import frappe


def execute():
    """Migrate old source_key format to simplified format."""
    # Find all budget lines with old format: PLANNED_ITEM::xxx::YYYY-MM-DD
    # The regex matches: PLANNED_ITEM::<something>::<date in ISO format>
    lines = frappe.db.sql("""
        SELECT name, source_key 
        FROM `tabMPIT Budget Line`
        WHERE source_key LIKE 'PLANNED_ITEM::%'
          AND source_key REGEXP 'PLANNED_ITEM::[^:]+::[0-9]{4}-[0-9]{2}-[0-9]{2}'
    """, as_dict=True)

    if not lines:
        return

    updated = 0
    seen_keys = {}  # Track new keys per parent to detect potential duplicates

    for line in lines:
        parts = line.source_key.split("::")
        if len(parts) < 2:
            continue

        # Extract just PLANNED_ITEM::{item_name}
        item_name = parts[1]
        new_key = f"PLANNED_ITEM::{item_name}"

        # Get parent budget to check for duplicates within same budget
        parent = frappe.db.get_value("MPIT Budget Line", line.name, "parent")
        if not parent:
            continue

        parent_key = (parent, new_key)
        if parent_key in seen_keys:
            # This would create a duplicate - delete the older line
            # Keep the first one we saw, delete this one
            frappe.db.delete("MPIT Budget Line", line.name)
            frappe.log_error(
                f"Deleted duplicate budget line {line.name} (source_key: {line.source_key})",
                "Planned Item source_key migration"
            )
            continue

        seen_keys[parent_key] = line.name
        frappe.db.set_value(
            "MPIT Budget Line", line.name,
            "source_key", new_key,
            update_modified=False
        )
        updated += 1

    if updated:
        frappe.db.commit()
