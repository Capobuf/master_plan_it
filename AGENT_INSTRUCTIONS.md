# Agent Instructions (Master Plan IT)

These rules exist to prevent drift and avoid “terminal copy/paste chaos”.

## Non‑negotiables
- Do **not** add custom JS/CSS or any frontend build pipeline.
- Keep everything **native Frappe Desk**.
- Keep changes **idempotent** where applicable.
- Never overwrite standard fixtures or export/import standard system records.
- Do **not** build or use any custom spec-import / `sync_all` pipeline; stay native.
- Metadata lives only under `apps/master_plan_it/master_plan_it/master_plan_it/` — do not create duplicates elsewhere.

## Development workflow (single source of truth)

### Location of source files
- **Metadata (source of truth):** `apps/master_plan_it/master_plan_it/master_plan_it/{doctype,report,workflow,dashboard,dashboard_chart,number_card,master_plan_it_dashboard,workspace,print_format}/`
- **Python logic:** `apps/master_plan_it/master_plan_it/doctype/*/mpit_*.py`
- **Spec folder:** `apps/master_plan_it/master_plan_it/spec/` is documentation only; do not import from it.

### The correct flow (native file-first)
1) Edit exported metadata JSON directly in the canonical module folder.
2) If you use Desk for skeleton/non-owned DocTypes, immediately **Export Customizations** back into the canonical path.
3) Edit Python logic alongside metadata as needed.
4) Apply with standard Frappe commands (`bench --site <site> migrate`, `clear-cache`) when required. No custom import pipeline.

### Steps to apply changes
1) Edit metadata JSON under `master_plan_it/master_plan_it/master_plan_it/...` (or export from Desk into that path).
2) Edit Python logic in `doctype/*/mpit_*.py` if needed.
3) Apply to database with standard Frappe commands as needed (`bench --site <site> migrate`, `clear-cache`).
4) Commit the canonical metadata and code.
5) For tenant provisioning:
   ```bash
   bench --site <site> execute master_plan_it.devtools.bootstrap.run --kwargs '{"step":"all"}'
   ```

## What to change where

### Always edit these (source of truth)
- **Metadata JSON:** `master_plan_it/master_plan_it/master_plan_it/{doctype,report,workflow,dashboard,dashboard_chart,number_card,master_plan_it_dashboard,workspace,print_format}/...`
- **Python logic:** `master_plan_it/master_plan_it/doctype/*/mpit_*.py`
- **Bootstrap scripts:** `master_plan_it/devtools/bootstrap.py`, `verify.py`, `sync.py`
- **Hooks:** `master_plan_it/hooks.py`
- **Docs:** `master_plan_it/master_plan_it/spec/` for reference only (no imports)

### Forbidden paths (must not exist)
- `apps/master_plan_it/master_plan_it/doctype/`
- `apps/master_plan_it/master_plan_it/report/`
- `apps/master_plan_it/master_plan_it/workflow/`
- `apps/master_plan_it/master_plan_it/workspace/`
- `apps/master_plan_it/master_plan_it/dashboard/`
- `apps/master_plan_it/master_plan_it/dashboard_chart/`
- `apps/master_plan_it/master_plan_it/number_card/`
- `apps/master_plan_it/master_plan_it/master_plan_it_dashboard/`
- `apps/master_plan_it/master_plan_it/print_format/`

## Required outputs for each task
- If you change metadata: ensure `bench migrate` is the apply step and update the relevant docs.
- Add or update an ADR if the change is an architectural decision (multi-tenant model, workflow semantics, immutability rules).
- Provide a `verify()` command/output plan: what should exist in DB after apply.

## Safety checks
- Avoid changing object/module names once created (keeps file paths stable).
- Never export fixtures without filters; ensure only MPIT objects are included.
- If unsure whether an object is “standard sync”: prefer to create it in dev mode via UI and commit its files.
