# Epic E01: Money naming & printing

Notes for aligning money fields and print formats using the native file-first workflow.

- Edit metadata and print formats under `apps/master_plan_it/master_plan_it/master_plan_it/` (DocTypes, print formats, controllers).
- If you tweak print formats or labels in Desk, export the changes back into the canonical folder immediately.
- Apply changes with standard Frappe commands (`bench --site <site> migrate`, `clear-cache`); no custom import/sync pipeline.
- Keep translations updated in the `translations/` CSVs alongside the app.
