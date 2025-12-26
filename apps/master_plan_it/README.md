# Master Plan IT — App Notes

- Frappe Desk app (multi-tenant: 1 site = 1 client). No custom JS/CSS or build pipeline.
- **App root:** this folder in Git; inside a bench the app lives at `apps/master_plan_it/`.
- **Source of truth:** exported metadata + controllers under `apps/master_plan_it/master_plan_it/master_plan_it/`.
- **Apply changes:** edit the canonical files, then run standard Frappe commands (`bench --site <site> migrate`, `clear-cache`) so the site picks up file changes.
- **Desk policy:** treat Desk as read-only for day-to-day schema/metadata. Use Desk only for initial skeleton creation or to export customizations for non-owned DocTypes.
- **Install hooks + fixtures:** hooks create MPIT Settings and MPIT Year (current + next); fixtures ship only MPIT roles.
- **Docs:** see `docs/reference/06-dev-workflow.md` for the authoritative workflow and `docs/reference/05-fixtures-bootstrap.md` for fixture rules (native file-first, no spec-import).
