from __future__ import annotations

import datetime

import frappe


def promote_deferred_projects() -> int:
    """Promote Deferred projects to Proposed when their target MPIT Year becomes current."""
    today = datetime.date.today()
    current_year_name = frappe.db.get_value(
        "MPIT Year",
        {"start_date": ["<=", today], "end_date": [">=", today]},
        "name",
    )
    if not current_year_name:
        frappe.logger("master_plan_it").info(
            "Skipped deferred project promotion on %s: no current MPIT Year.",
            today,
        )
        return 0

    project_names = frappe.get_all(
        "MPIT Project",
        filters={"workflow_state": "Deferred", "deferred_to_year": current_year_name},
        pluck="name",
    )
    for project_name in project_names:
        frappe.db.set_value(
            "MPIT Project",
            project_name,
            "workflow_state",
            "Proposed",
            update_modified=False,
        )

    return len(project_names)
