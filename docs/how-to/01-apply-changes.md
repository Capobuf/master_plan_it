# Apply changes (deterministic, no GUI)

**Goal:** After editing *spec files* or code, apply them so changes appear in the GUI (DocTypes, permissions, workflows, reports/dashboards, workspace).

This project uses a **file-first spec** (JSON) and a **sync command** that:
1) creates/updates DocTypes + security + workflows (and ensures MPIT roles/workspace) in the database, and
2) (optionally) promotes DocTypes to *standard* so Frappe writes the usual JSON/Python files for versioning.

> Why: we want a workflow that is idempotent, deterministic, and does not depend on clicking in Desk.

---

## A) Apply spec changes (recommended during development)

From `frappe-bench`:

1) **Sync MPIT specs into the site**
```bash
bench --site <site> execute master_plan_it.devtools.sync.sync_all
```

2) **Apply schema + rebuild caches**
```bash
bench --site <site> migrate
bench --site <site> clear-cache
```

3) If you changed JS/CSS (public assets)
```bash
bench build
# or: bench watch (dev)
```

Open Desk and verify.

---

## B) Apply pure code changes (python)

If you only changed Python (controllers, hooks, API) and did not touch DocTypes:
```bash
bench --site <site> restart
bench --site <site> clear-cache
```

---

## C) Targeted reload (advanced)

Use only if you want to reload a single DocType or a single file-based document.

```bash
bench --site <site> reload-doctype "<DocType Name>"
bench --site <site> reload-doc <module> <doctype> "<docname>"
```

Prefer `sync_all` + `migrate` for schema changes.


4) (Optional) tenant defaults + verify
```bash
bench --site <site> execute master_plan_it.devtools.bootstrap.run --kwargs '{"step":"tenant"}'
bench --site <site> execute master_plan_it.devtools.verify.run
```
