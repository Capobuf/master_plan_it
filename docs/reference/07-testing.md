# Reference: Testing (server + UI)

## Server-side tests (Python)

Place tests anywhere in the app, filenames must start with `test_*.py`.

Run:
```bash
bench --site <site> run-tests --app master_plan_it
```

You can run narrower scopes too (examples):
```bash
bench --site <site> run-tests --doctype "MPIT Budget"
bench --site <site> run-tests --test "test_smoke"
```

## UI tests (native approach)

Frappe supports UI tests using **Cypress** (integration tests) and also JS tests using **QUnit**.

We keep Cypress tests inside the app folder:
- `cypress/integration/*.spec.js`

To run Cypress for this app:
```bash
cd apps/master_plan_it
yarn
yarn cypress:open
# or: yarn cypress:run
```

> Note: UI tests require a running site (`bench start`) and a known admin password for the test site.

## What we test in V1

- Smoke: user can login and open MPIT workspace/pages
- Smoke: create a Budget, move it through workflow, ensure status changes
- Regression: permissions (Client Viewer can't edit, Client Editor can)
- Regression: Actual Entry derives year from posting_date; Project cannot be approved without allocations
