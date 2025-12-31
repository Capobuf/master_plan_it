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
- **Install hooks & fixtures:** `master_plan_it/master_plan_it/setup/install.py` and `master_plan_it/master_plan_it/fixtures/role.json` handle bootstrap (settings/years) and ship MPIT roles.

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

### Forbidden paths (must not exist)
- `master_plan_it/master_plan_it/doctype/`
- `master_plan_it/master_plan_it/report/`
- `master_plan_it/master_plan_it/workflow/`
- `master_plan_it/master_plan_it/workspace/`
- `master_plan_it/master_plan_it/dashboard/`
- `master_plan_it/master_plan_it/dashboard_chart/`
- `master_plan_it/master_plan_it/number_card/`
- `master_plan_it/master_plan_it/master_plan_it_dashboard/`
- `master_plan_it/master_plan_it/print_format/`

## Translations (i18n) – Native Frappe Rules

Source of truth
- Translation file lives only at `master_plan_it/master_plan_it/translations/it.csv`. Do not create alternative translation sources or duplicate CSVs.
- The CSV filename must match the Frappe Language code configured in **System Settings > Language**. Default is `it.csv`; if your site uses `it-IT`, rename accordingly.

What auto-translates vs what must be explicit
- DocType JSON (labels, descriptions, select options, workflow/workspace/report labels) is auto-translatable by Frappe; no `_()` needed inside JSON.
- Code strings must be marked explicitly:
  - Python: `from frappe import _` then `_("Literal string")`
  - JS: `__("Literal string")`
  - Jinja/Print Format: `{{ _("Literal string") }}`

Literal-string rules (mirror Frappe docs)
- `_()` / `__()` take literal strings only (no variables). Use positional placeholders `{0}`, `{1}` and format after translating:
  - Python: `_("Welcome {0}.").format(name)`
  - JS: `__("Welcome {0}.", [name])`
- No concatenation or multiline translation literals; avoid trailing spaces; do not pluralize by appending “s” (write full singular/plural strings).
- Add context when the same source string has multiple meanings:
  - JS: `__("Change", null, "Coins")`
  - Python: `_("Change", context="Switch")`
  - In `it.csv` use the 3rd “context” column to disambiguate.

How to update it.csv (anti-drift)
- Use a 3-column CSV: `source_string,translated_string,context` (context empty when not needed).
- Keep one entry per unique source_string + context; quote values containing commas; keep rows deterministically sorted (e.g., by source_string then context).
- Do not translate technical identifiers (DocType names, fieldnames, module names, route paths, DB keys). Keep app/product names as-is unless the business glossary says otherwise.
- Prefer existing translations for consistency. If a core business term lacks precedent, pause and obtain a glossary decision before adding it.

Verification (manual, not run here)
- Switch a test user to Italian and confirm UI/prints/reports show translated labels/messages.
- If UI strings stay stale, clear cache/build per native bench steps for the site; no custom tooling beyond standard Frappe commands.

Italian translations — verify & troubleshoot
- Verify steps (in order): (1) Set your user language to Italian. (2) Confirm translation file exists at `master_plan_it/master_plan_it/translations/it.csv`. (3) Ensure the filename matches your Frappe Language code (e.g., use `it-IT.csv` if Language code is `it-IT`). (4) Hard-refresh the browser. (5) If still stale, clear cache: `bench --site <site> clear-cache`. (6) If still stale, restart bench/web services for the site. (7) Only when you changed custom frontend JS with `__()`, build assets: `bench --site <site> build`.
- Common causes: wrong folder (not under the app package), wrong filename vs Language code, source string mismatch (spaces/punctuation), string not wrapped in `_()` / `__()` / `{{ _("...") }}`, cache not cleared or services not restarted.

## Required outputs for each task
- If you change metadata: ensure `bench migrate` is the apply step and update the relevant docs.
- Add or update an ADR if the change is an architectural decision (multi-tenant model, workflow semantics, immutability rules).
- Provide a `verify()` command/output plan: what should exist in DB after apply.

## Safety checks
- Avoid changing object/module names once created (keeps file paths stable).
- Never export fixtures without filters; ensure only MPIT objects are included.
- If unsure whether an object is “standard sync”: prefer to create it in dev mode via UI and commit its files.
