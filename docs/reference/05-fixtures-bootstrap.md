# Reference: Fixtures & Bootstrap

Fixtures are standard Frappe exports filtered to Master Plan IT records only. Keep them minimal and deterministic.

## Fixture principles
- Export only MPIT-specific records; avoid unfiltered exports.
- Keep filters in fixture definitions so re-exporting stays stable.
- Do not rely on spec/import pipelines. The source of truth is the exported files under `apps/master_plan_it/master_plan_it/master_plan_it/`.
- Avoid creating duplicate metadata paths outside the canonical module folder.

## Applying changes
- Edit canonical metadata and controllers in `apps/master_plan_it/master_plan_it/master_plan_it/`.
- Export Customizations for non-owned DocTypes if you modified them in Desk.
- Apply to a site with standard Frappe commands (`bench --site <site> migrate`, `clear-cache`) as needed.
