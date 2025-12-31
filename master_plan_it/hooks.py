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

fixtures = [
    {"dt": "Role", "filters": [["name", "in", ["vCIO Manager", "Client Editor", "Client Viewer"]]]},
]
