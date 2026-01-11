# Reference: Technical dev workflow (Frappe v15)

This document defines the **native file-first** workflow for Master Plan IT.

## Source of truth

- App root in Git: this repo root (`.`); inside a bench it lives at `/home/frappe/frappe-bench/apps/master_plan_it`.
- Exported metadata lives under `master_plan_it/master_plan_it/` (doctype, report, workflow, dashboard, dashboard_chart, number_card, workspace, print_format). Legacy `master_plan_it_dashboard/` copies were removed to avoid duplicates.
- These files are edited directly in Git; they are the canonical inputs for Frappe. No spec-import and no `sync_all`.
- **Warning:** Do not manually create duplicate metadata folders outside the canonical module folder.
- **Translations:** Follow `docs/reference/12-i18n.md` for marking strings and maintaining `translations/it.csv`.
- **Dashboard Chart/Source:** crea/aggiorna i record via bench/console e poi `bench --site <site> export-json "Dashboard Chart" "<Name>" <path>` (idem per Dashboard Chart Source) per evitare di indovinare lo schema JSON.
- **Dashboard Chart/Source:** vengono ricaricati via `after_migrate` (`reload_doc`) per garantire file-first deterministico.
- **Dashboard Chart Source:** richiede registrazione JS in `frappe.dashboards.chart_sources` e un metodo server whitelisted `get(...)` (stile ERPNext); non dipendere da `tabDashboard Chart Source.method`.

## Who generates/edits files?

- Human or LLM agent edits the exported JSON + Python controllers in the canonical module folder.
- Desk/UI is read-only for day-to-day metadata changes. Allowed UI actions:
  - Initial skeleton creation when a new object is first made.
  - **Export Customizations** for non-owned DocTypes.

## Native workflow (file-first)

1. Edit the exported JSON and Python controllers under `master_plan_it/master_plan_it/`.
2. If you used Desk for skeleton/customizations, immediately export to files so the canonical folder stays accurate.
3. Apply changes with standard Frappe commands (`bench --site <site> migrate` / `clear-cache`) as needed. No `sync_all` or custom import pipelines.
4. Commit the canonical files; these are the sole source for deployments.
5. Install hooks handle baseline bootstrap (MPIT Settings + current/next MPIT Year); fixtures ship MPIT roles.

## Determinism rules

1) Keep metadata JSON stable and sorted; avoid renaming DocTypes/modules once created.
2) Treat Desk exports as read-through: after UI edits, ensure the canonical folder reflects the final state.
3) If an object is removed, delete its folder from the canonical path; do not leave shadow copies elsewhere.
4) In bind-mounted dev (docker compose), edits on the host appear in the container immediately; Desk reflects changes after the usual reload/migrate cycle.
