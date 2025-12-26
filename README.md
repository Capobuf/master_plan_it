# Master Plan IT

Frappe Desk app (v15) for budgeting, contracts, and projects. This repo follows a **native file-first workflow**.

- **App root (this repo):** `apps/master_plan_it/` (the same path inside a bench after `bench get-app`)
- **Canonical metadata and controllers:** `apps/master_plan_it/master_plan_it/master_plan_it/`
- **Apply changes:** edit canonical files, then run standard Frappe commands (`bench --site <site> migrate`, `clear-cache`) so the site picks them up.
- **Desk policy:** Desk is read-only for day-to-day metadata; use it only for initial skeletons and Export Customizations for non-owned DocTypes.
- **Install hooks:** create MPIT Settings and MPIT Year (current + next); fixtures ship MPIT roles only.
- **Fixtures:** keep fixtures minimal and filtered to MPIT records only (see `docs/reference/05-fixtures-bootstrap.md`).
- **More docs:** `docs/reference/06-dev-workflow.md` is the authoritative workflow guide (native file-first, no spec/sync pipeline).
