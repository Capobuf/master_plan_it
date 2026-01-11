# Agent Instructions (Master Plan IT)

These rules exist to prevent drift and avoid “terminal copy/paste chaos”.

## Non‑negotiables
- Do **not** add custom JS/CSS or any frontend build pipeline.
- Keep everything **native Frappe Desk**.
- Keep changes **idempotent** where applicable.
- Never overwrite standard fixtures or export/import standard system records.
- Do **not** build or use any custom spec-import / `sync_all` pipeline; stay native.
- Metadata lives only under `master_plan_it/master_plan_it/` — do not create duplicates elsewhere. The Git repo root is the app root; inside a bench the path remains `apps/master_plan_it/` after `bench get-app`.

## Development workflow (single source of truth)

For applying changes to a site, see `docs/how-to/01-apply-changes.md`.

### Location of source files
- **Metadata (source of truth):** `master_plan_it/master_plan_it/{doctype,report,workflow,dashboard,dashboard_chart,number_card,master_plan_it_dashboard,workspace,print_format}/`
- **Python logic:** `master_plan_it/master_plan_it/doctype/*/mpit_*.py`
- **Install hooks & fixtures:** `master_plan_it/setup/install.py` and `master_plan_it/fixtures/role.json` handle bootstrap (settings/years) and ship MPIT roles.

### The correct flow (native file-first)
1) Edit exported metadata JSON directly in the canonical module folder.
2) If you use Desk for skeleton/non-owned DocTypes, immediately **Export Customizations** back into the canonical path.
3) Edit Python logic alongside metadata as needed.
4) Apply with standard Frappe commands (`bench --site <site> migrate`, `clear-cache`) when required. No custom import pipeline.

### Steps to apply changes
1) Edit metadata JSON under `master_plan_it/master_plan_it/...` (or export from Desk into that path).
2) Edit Python logic in `doctype/*/mpit_*.py` if needed.
3) Apply to database with standard Frappe commands as needed (`bench --site <site> migrate`, `clear-cache`).
4) Commit the canonical metadata and code. Install hooks will create MPIT Settings + current/next year, and fixtures ship MPIT roles.

## What to change where

### Always edit these (source of truth)
- **Metadata JSON:** `master_plan_it/master_plan_it/{doctype,report,workflow,dashboard,dashboard_chart,number_card,master_plan_it_dashboard,workspace,print_format}/...`
- **Python logic:** `master_plan_it/master_plan_it/doctype/*/mpit_*.py`
- **Install/bootstrap:** `master_plan_it/setup/install.py` (after_install/after_sync)
- **Fixtures:** `master_plan_it/fixtures/` (filtered exports only; roles already provided)
- **Hooks:** `master_plan_it/hooks.py`

### Anti-patterns (do not duplicate)
Do not create metadata in these paths (the inner `master_plan_it/` folder is the correct location):
- `master_plan_it/doctype/`
- `master_plan_it/report/`
- `master_plan_it/workflow/`
- `master_plan_it/workspace/`
- `master_plan_it/dashboard/`
- `master_plan_it/dashboard_chart/`
- `master_plan_it/number_card/`
- `master_plan_it/master_plan_it_dashboard/`
- `master_plan_it/print_format/`

A regression test (`test_no_forbidden_metadata_paths.py`) enforces this.

## Translations (i18n)

→ See `docs/reference/12-i18n.md` for complete translation rules.

**Quick ref:**
- Source: `master_plan_it/master_plan_it/translations/it.csv`
- Python: `_("text")` | JS: `__("text")` | Jinja: `{{ _("text") }}`


## Required outputs for each task
- If you change metadata: ensure `bench migrate` is the apply step and update the relevant docs.
- Add or update an ADR if the change is an architectural decision (multi-tenant model, workflow semantics, immutability rules).
- Provide a `verify()` command/output plan: what should exist in DB after apply.

## Safety checks
- Avoid changing object/module names once created (keeps file paths stable).
- Never export fixtures without filters; ensure only MPIT objects are included.
- If unsure whether an object is “standard sync”: prefer to create it in dev mode via UI and commit its files.
