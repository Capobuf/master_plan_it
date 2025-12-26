# Reference: Fixtures & Bootstrap

Fixtures are standard Frappe exports filtered to Master Plan IT records only. Keep them minimal and deterministic.

## Fixture principles
- Export only MPIT-specific records; avoid unfiltered exports.
- Keep filters in fixture definitions so re-exporting stays stable.
- Do not rely on any spec/import pipeline. The source of truth is the exported files under `apps/master_plan_it/master_plan_it/master_plan_it/`.
- Avoid creating duplicate metadata paths outside the canonical module folder.

## Applying changes
- Edit canonical metadata and controllers in `apps/master_plan_it/master_plan_it/master_plan_it/`.
- Export Customizations for non-owned DocTypes if you modified them in Desk.
- Apply to a site with standard Frappe commands (`bench --site <site> migrate`, `clear-cache`) as needed.

## What ships as fixtures
- Roles are shipped as a filtered fixture (`apps/master_plan_it/master_plan_it/fixtures/role.json`) covering only: `vCIO Manager`, `Client Editor`, `Client Viewer`.
- Keep the fixture minimal (doctype, name/role_name, desk_access) so it stays stable between exports.

## Bootstrap (install hooks)
- Install hooks (`master_plan_it.setup.install.after_install/after_sync`) create the singleton MPIT Settings document and MPIT Year for the current and next calendar year.
- No devtools “bootstrap” scripts are required for baseline data; verification happens later via Desk after a normal migrate.
