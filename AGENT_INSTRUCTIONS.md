# Agent Instructions (Master Plan IT)

These rules exist to prevent drift and avoid “terminal copy/paste chaos”.

## Non‑negotiables
- Do **not** add custom JS/CSS or any frontend build pipeline.
- Keep everything **native Frappe Desk**.
- Keep changes **idempotent** where applicable.
- Never overwrite standard fixtures or export/import standard system records.

## Development workflow (single source of truth)
1) Create/modify **standard objects** using Frappe mechanisms (DocType/Report/Workspace/Workflow).
2) Commit generated JSON files in the app.
3) Apply changes using **one command**:
   - `bench --site <site> migrate`
4) For tenant provisioning or sanity checks run:
   - `bench --site <site> execute master_plan_it.scripts.bootstrap.run --kwargs '{"step":"all"}'`

## What to change where
- DocTypes: `master_plan_it/master_plan_it/doctype/...`
- Reports: `master_plan_it/master_plan_it/report/...`
- Workspace: `master_plan_it/master_plan_it/workspace/...`
- Bootstrap scripts: `master_plan_it/scripts/...`
- Fixtures (filtered only): `master_plan_it/fixtures/...`
- Hooks: `master_plan_it/hooks.py`

## Required outputs for each task
- If you change metadata: ensure `bench migrate` is the apply step and update the relevant docs.
- Add or update an ADR if the change is an architectural decision (multi-tenant model, workflow semantics, immutability rules).
- Provide a `verify()` command/output plan: what should exist in DB after apply.

## Safety checks
- Avoid changing object/module names once created (keeps file paths stable).
- Never export fixtures without filters; ensure only MPIT objects are included.
- If unsure whether an object is “standard sync”: prefer to create it in dev mode via UI and commit its files.

