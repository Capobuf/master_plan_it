# Reference: Technical dev workflow (Frappe v15)

This document defines a **deterministic, idempotent** workflow for Master Plan IT.

## What is the source of truth?

**Source of truth:** JSON specs in `apps/master_plan_it/master_plan_it/spec/`

- `spec/doctypes/*.json` — DocTypes (fields + basic settings)
- `spec/workflows/*.json` — Workflows + states/actions
- `spec/reports/*.json` — Query Reports
- `spec/dashboards/*.json`, `spec/dashboard_charts/*.json`, `spec/number_cards/*.json` — Dashboard components

**Important:** The `starter-kit/spec/` directory is a **bootstrap template** that is copied to `apps/master_plan_it/master_plan_it/spec/` during initial setup. Once the app is created, **always modify files in `apps/master_plan_it/master_plan_it/spec/`**, not in `starter-kit/spec/`.

### Spec files vs Python logic

- **Spec JSON files** contain metadata only: field definitions, permissions, workflow states/transitions
- **Python logic** (calculations, validations, business rules) lives in `apps/master_plan_it/master_plan_it/doctype/*/mpit_*.py`
- Both are versioned in Git and are required for a complete, distributable app

We do **not** rely on manual edits in Desk for schema changes.

## Who generates/edits files?

- Human or LLM agent edits **spec JSON** and **Python code**.
- Then a single command applies everything into the site:
  - `bench --site <site> execute master_plan_it.devtools.sync.sync_all`

## Where do files live in the final repo?

In the final Git repository, you will keep:

- The standard Frappe app folder (created by `bench new-app master_plan_it`)
- `master_plan_it/master_plan_it/spec/*` — **SOURCE OF TRUTH** for metadata
- `master_plan_it/master_plan_it/devtools/*` — sync + verify + bootstrap scripts
- `master_plan_it/master_plan_it/doctype/*/mpit_*.py` — **Python business logic**
- `master_plan_it/master_plan_it/doctype/*/mpit_*.json` — **Auto-exported** metadata (DO NOT EDIT directly)
- `master_plan_it/cypress/*` — UI tests (optional)

The `starter-kit/` folder is kept in sync as a template reference, but **all development happens in `apps/master_plan_it/master_plan_it/spec/`**.

## Why not write DocType JSON directly?

Frappe DocType JSON files contain many defaulted keys. Writing them manually is error-prone.

### The spec → DB → export flow

```
1. Edit spec files (apps/master_plan_it/master_plan_it/spec/)
   ↓
2. Run sync_all (applies spec to database via DocType API)
   ↓
3. Promote to Standard (export_to_files)
   ↓
4. Auto-generated files (apps/master_plan_it/master_plan_it/doctype/*/mpit_*.json)
```

**CRITICAL:** Never edit the auto-generated JSON files in `doctype/*/` directly. They will be overwritten on the next `sync_all`. Always edit the spec files in `spec/` instead.

This flow ensures:
- Specs remain the single source of truth
- Database is always in sync with specs
- Exported files are consistent and complete

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
