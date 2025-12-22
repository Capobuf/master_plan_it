# Reference: Fixtures and bootstrap

## What goes into fixtures (filtered only)
- Roles: vCIO Manager, Client Editor, Client Viewer (provisionati anche da `sync_all`)
- Workflows: MPIT Budget workflow, MPIT Budget Amendment workflow (provisionati da `spec/workflows/*.json` + `sync_all`)
- Workflow State and Workflow Action used by those workflows
- Dashboard assets: dashboard charts, number cards, and dashboard “Master Plan IT Overview” (via `spec/dashboard_charts`, `spec/number_cards`, `spec/dashboards`)

Do NOT export standard Roles/Workspaces/Module Defs.
Always use filters to include only MPIT records.

## What goes into bootstrap (idempotent)
Bootstrap creates per-tenant operational defaults:
- MPIT roles (if missing)
- MPIT Settings (Single) if missing
- MPIT Year records (e.g., current year + next year) if missing
- Workspace “Master Plan IT” non pubblica con ruoli MPIT + System Manager
- Optional: sample categories (only if explicitly requested)

Bootstrap must be safe to run multiple times.

## Suggested CLI entrypoint
- `master_plan_it.scripts.bootstrap.run(step="all")`
Steps:
- tenant
- verify

## Verify function (required)
The verify step should check presence of:
- core DocTypes
- workflows
- roles
- workspace
- reports
and print a concise summary.
