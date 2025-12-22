# Reference: Technical dev workflow (Frappe v15)

This document defines a **deterministic, idempotent** workflow for Master Plan IT.

## What is the source of truth?

**Source of truth:** JSON specs in `starter-kit/spec/`

- `spec/doctypes/*.json` — DocTypes (fields + basic settings)
- `spec/security/*.json` — Roles, DocPerm, Role Permissions Manager (if needed)
- `spec/workflows/*.json` — Workflows + states/actions
- `spec/fixtures/` — only for *data* fixtures, not schema (optional)

We do **not** rely on manual edits in Desk.

## Who generates/edits files?

- Human or LLM agent edits **spec JSON** and **Python code**.
- Then a single command applies everything into the site:
  - `bench --site <site> execute master_plan_it.devtools.sync.sync_all`

## Where do files live in the final repo?

In the final Git repository, you will keep:

- The standard Frappe app folder (created by `bench new-app master_plan_it`)
- `master_plan_it/master_plan_it/devtools/*` (our sync + verify + bootstrap)
- `master_plan_it/spec/*` (copied from `starter-kit/spec/*`)
- `master_plan_it/cypress/*` (UI tests, optional)

This `starter-kit/` folder is a *bootstrap* artifact: you can copy its content into the real app repo once the bench exists.

## Why not write DocType JSON directly?

Frappe DocType JSON files contain many defaulted keys. Writing them manually is error-prone.

Instead, we:
1) create/update DocTypes through Frappe's own DocType API (programmatically),
2) then **promote them to Standard** (unset "Custom") programmatically,
so Frappe generates the expected `doctype/*.json` and controller stubs (same effect as "uncheck Custom? and Save").

## Commands you will use daily

From `frappe-bench`:

- Apply spec → `bench --site <site> execute master_plan_it.devtools.sync.sync_all`
- Apply schema → `bench --site <site> migrate`
- Clear caches → `bench --site <site> clear-cache`
- Build assets → `bench build` (or `bench watch`)
- Run server-side tests → `bench --site <site> run-tests --app master_plan_it`
- Run UI tests (Cypress) → see `docs/reference/07-testing.md`

## Determinism rules

1) Specs are sorted and stable (we write canonical JSON).
2) Sync is idempotent: running it twice must not change results.
3) Sync fails fast if a referenced DocType/fieldname is missing or renamed without a migration step.


## Standard docfiles generation
During `sync_all`, we call `export_to_files` for each DocType after promoting it to standard. This produces the canonical `doctype/<name>/<name>.json` files inside the app.
