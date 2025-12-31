# Master Plan IT

Frappe Desk app (v15) for budgeting, contracts, and projects. Native file-first workflow; no custom JS/CSS or build pipeline.

- App root: this repo; inside a bench the app lives at `apps/master_plan_it/`.
- Multi-tenant: 1 site = 1 client.
- Canonical metadata and controllers: `master_plan_it/master_plan_it/`.
- Apply changes: edit canonical files, then run standard Frappe commands (`bench --site <site> migrate`, `clear-cache`).
- Desk policy: treat Desk as read-only for day-to-day metadata; use it only for initial skeletons and Export Customizations for non-owned DocTypes.
- Install hooks: create MPIT Settings, MPIT Year (current + next), and seed the root Cost Center (“All Cost Centers”). Fixtures ship MPIT roles only.
- Docs: `docs/reference/06-dev-workflow.md` is the authoritative workflow; see also `docs/reference/05-fixtures-bootstrap.md`.
