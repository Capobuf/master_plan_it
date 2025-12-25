# How-to: Apply metadata changes (native file-first)

This guide describes how to apply changes when the **source of truth** is the exported metadata and controllers under `apps/master_plan_it/master_plan_it/master_plan_it/`.

## Before you start
- Do not edit metadata JSON in Desk for day-to-day work. Use Desk only to create an initial skeleton or to export customizations for non-owned DocTypes.
- Keep all metadata inside the canonical module folder; do not create duplicate paths elsewhere.

## Workflow
1) Edit metadata JSON and Python controllers under `apps/master_plan_it/master_plan_it/master_plan_it/...`.
2) If you made UI changes in Desk, run **Export Customizations** immediately to sync them into the canonical folder.
3) Apply changes to the site with standard Frappe commands:
   - `bench --site <site> migrate`
   - `bench --site <site> clear-cache`
4) Verify: confirm expected DocTypes/Workflows/Reports exist and behave as intended.
5) Commit the canonical files. No custom import/sync pipeline is used.

## Tips
- Keep fixture exports filtered to MPIT records only.
- Avoid renaming DocTypes/modules once created to keep file paths stable.
