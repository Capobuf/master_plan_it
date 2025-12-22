# Master Plan IT (MPIT)

vCIO budgeting, contracts/renewals, amendments, actuals and projects — Frappe Desk app (multi-tenant: one site per client).

## Dev workflow
- Apply specs (DocTypes) deterministically: `bench --site <site> execute master_plan_it.devtools.sync.sync_all`
- Apply schema: `bench --site <site> migrate`
- Clear caches: `bench --site <site> clear-cache`
- Bootstrap tenant defaults: `bench --site <site> execute master_plan_it.devtools.bootstrap.run --kwargs '{"step":"tenant"}'`
- Verify required DocTypes: `bench --site <site> execute master_plan_it.devtools.verify.run`
- Tests: `bench --site <site> run-tests --app master_plan_it`

## Notes
- Specs live in `master_plan_it/spec/doctypes/*.json` (file-first, no Desk editing).
- No custom JS/CSS or frontend build; stick to native Desk components.
- For workflows/dashboards, add filtered fixtures once canonical examples are available.
