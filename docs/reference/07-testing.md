# Reference: Testing (server + UI)

## Frappe native tests (Python)

Place tests anywhere in the app; filenames must start with `test_*.py`.

Run the full suite:
```bash
bench --site <site> run-tests --app master_plan_it
```

You can run narrower scopes too (examples):
```bash
bench --site <site> run-tests --doctype "MPIT Budget"
bench --site <site> run-tests --test "test_smoke"
```

## UI coverage approach (no Cypress)

We rely only on Frappe's native Python tests (including UI helpers) instead of Cypress. New UI flows should be exercised through server-side tests and page/doctype controllers, keeping fixtures in the app.

## What we test in V1

- Smoke: user can login and open MPIT workspace/pages
- Smoke: create a Budget, move it through workflow, ensure status changes
- Regression: permissions (Client Viewer can't edit, Client Editor can)
- Regression: Actual Entry derives year from posting_date; Project cannot be approved without allocations
