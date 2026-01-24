# -*- coding: utf-8 -*-
"""master_plan_it hooks

This file declares hooks used by Frappe.
Keep changes minimal and deterministic.
"""
from . import __version__ as app_version

app_name = "master_plan_it"
app_title = "Master Plan IT"
app_publisher = "DOT"
app_description = "vCIO multi-tenant budgeting & actuals management (MPIT)."
app_email = "n/a"
app_license = "MIT"

after_install = "master_plan_it.setup.install.after_install"
after_sync = "master_plan_it.setup.install.after_sync"
after_migrate = "master_plan_it.setup.install.after_migrate"

fixtures = [
    {"dt": "Role", "filters": [["name", "in", ["vCIO Manager", "Client Editor", "Client Viewer"]]]},
    # Workflow components - order matters: Actions and States before Workflows
    {"dt": "Workflow Action Master", "filters": [["name", "in", ["Propose", "Request Changes", "Approve", "Reject", "Reopen", "Cancel"]]]},
    {"dt": "Workflow State", "filters": [["name", "in", ["Draft", "Proposed", "Approved", "Rejected", "Cancelled", "Submitted"]]]},
    {"dt": "Workflow", "filters": [["name", "in", ["MPIT Budget Workflow", "MPIT Project Workflow", "MPIT Planned Item Workflow"]]]},
]

# Scheduled jobs
scheduler_events = {
    "daily": [
        "master_plan_it.budget_refresh_hooks.realign_planned_items_horizon",
    ],
}

# Budget Engine v3: Auto-refresh triggers
# When validated sources change, enqueue refresh for Live budgets in horizon (current year + next)
doc_events = {
    "MPIT Contract": {
        "on_update": "master_plan_it.budget_refresh_hooks.on_contract_change",
        "on_trash": "master_plan_it.budget_refresh_hooks.on_contract_change",
    },
    "MPIT Planned Item": {
        "on_update": "master_plan_it.budget_refresh_hooks.on_planned_item_change",
        "after_submit": "master_plan_it.budget_refresh_hooks.on_planned_item_change",
        "on_cancel": "master_plan_it.budget_refresh_hooks.on_planned_item_change",
        "on_trash": "master_plan_it.budget_refresh_hooks.on_planned_item_change",
    },
    "MPIT Budget Addendum": {
        "after_submit": "master_plan_it.budget_refresh_hooks.on_addendum_change",
        "on_cancel": "master_plan_it.budget_refresh_hooks.on_addendum_change",
    },
}
