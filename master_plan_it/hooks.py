# -*- coding: utf-8 -*-
"""master_plan_it hooks."""

from . import __version__ as app_version

app_name = "master_plan_it"
app_title = "Master Plan IT"
app_publisher = "DOT"
app_description = "vCIO contract and expense management (MPIT)."
app_email = "n/a"
app_license = "MIT"

after_install = "master_plan_it.setup.install.after_install"
after_sync = "master_plan_it.setup.install.after_sync"
after_migrate = "master_plan_it.setup.install.after_migrate"

fixtures = [
    {"dt": "Role", "filters": [["name", "in", ["vCIO Manager", "Client Editor", "Client Viewer"]]]},
    {
        "dt": "Notification",
        "filters": [["name", "in", ["MPIT Contract Expiry (Non Auto Renew)", "MPIT Contract Renewal (Auto Renew)"]]],
    },
]
