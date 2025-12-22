# Agent Instructions (Master Plan IT)

These rules exist to prevent drift and avoid “terminal copy/paste chaos”.

## Non‑negotiables
- Do **not** add custom JS/CSS or any frontend build pipeline.
- Keep everything **native Frappe Desk**.
- Keep changes **idempotent** where applicable.
- Never overwrite standard fixtures or export/import standard system records.- **CRITICAL:** Never edit auto-generated JSON files in `doctype/*/` directly — always edit spec files in `spec/`.
## Development workflow (single source of truth)

### Location of source files
- **Specs (metadata):** `apps/master_plan_it/master_plan_it/spec/` ← **EDIT THESE**
- **Python logic:** `apps/master_plan_it/master_plan_it/doctype/*/mpit_*.py` ← **EDIT THESE**
- **Exported JSON:** `apps/master_plan_it/master_plan_it/doctype/*/mpit_*.json` ← **DO NOT EDIT** (auto-generated)

### The correct flow
```
Spec files (spec/) → [sync_all] → Database → [export_to_files] → Exported JSON (doctype/)
```

### Steps to apply changes
1) Edit spec JSON in `spec/doctypes/`, `spec/workflows/`, etc.
2) Edit Python logic in `doctype/*/mpit_*.py` if needed
3) Apply to database:
   ```bash
   bench --site <site> execute master_plan_it.devtools.sync.sync_all
   bench --site <site> migrate
   bench --site <site> clear-cache
   ```
4) Commit both spec files AND auto-generated files to Git
5) For tenant provisioning:
   ```bash
   bench --site <site> execute master_plan_it.devtools.bootstrap.run --kwargs '{"step":"all"}'
   ```

## What to change where

### Always edit these (source of truth)
- **DocType specs:** `master_plan_it/spec/doctypes/*.json`
- **Workflow specs:** `master_plan_it/spec/workflows/*.json`
- **Report specs:** `master_plan_it/spec/reports/*.json`
- **Dashboard specs:** `master_plan_it/spec/dashboards/*.json`, `spec/dashboard_charts/*.json`, `spec/number_cards/*.json`
- **Python logic:** `master_plan_it/master_plan_it/doctype/*/mpit_*.py`
- **Bootstrap scripts:** `master_plan_it/devtools/bootstrap.py`, `verify.py`, `sync.py`
- **Hooks:** `master_plan_it/hooks.py`

### Never edit these (auto-generated)
- **Exported DocTypes:** `master_plan_it/master_plan_it/doctype/*/mpit_*.json` ← Created by sync_all
- **Exported Reports:** `master_plan_it/master_plan_it/report/*/mpit_*.json` ← Created by sync_all
- **Exported Workflows:** `master_plan_it/master_plan_it/workflow/*/mpit_*.json` ← Created by sync_all

## Required outputs for each task
- If you change metadata: ensure `bench migrate` is the apply step and update the relevant docs.
- Add or update an ADR if the change is an architectural decision (multi-tenant model, workflow semantics, immutability rules).
- Provide a `verify()` command/output plan: what should exist in DB after apply.

## Safety checks
- Avoid changing object/module names once created (keeps file paths stable).
- Never export fixtures without filters; ensure only MPIT objects are included.
- If unsure whether an object is “standard sync”: prefer to create it in dev mode via UI and commit its files.

