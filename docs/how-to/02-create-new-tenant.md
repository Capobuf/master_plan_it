# How to create a new tenant (new client site)

**Model:** 1 client = 1 Frappe site.

## Steps
1) Create site
   - `bench new-site <client-site-name>`

2) Install the app
   - `bench --site <client-site-name> install-app master_plan_it`

3) Apply metadata and fixtures
   - `bench --site <client-site-name> migrate`

4) Provision tenant defaults (idempotent)
   - `bench --site <client-site-name> execute master_plan_it.scripts.bootstrap.run --kwargs '{"step":"tenant"}'`

## Verification
In Desk, confirm:
- Workspace “Master Plan IT” exists
- DocTypes exist (Categories, Vendors, Contracts, Budgets, Amendments, Actual Entries, Projects, Settings)
- Roles exist: vCIO Manager, Client Viewer, Client Editor

