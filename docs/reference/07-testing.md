# Reference: Testing (server + UI)

## Frappe native tests (Python)

Place tests in the app with filenames `test_*.py`.

Run the full suite:
```bash
bench --site <site> run-tests --app master_plan_it
```

Run narrower scopes (examples):
```bash
bench --site <site> run-tests --doctype "MPIT Expense"
bench --site <site> run-tests --test "test_workspace_smoke"
```

## UI coverage approach

Cypress smoke tests are supplementary. Core business behavior remains covered by Frappe-native tests.

## Minimum smoke expectations

- Workspace `Master Plan IT` is reachable.
- `MPIT Overview` report is reachable from workspace navigation.
- Core economic doctypes (Expense, Contract, Project) open correctly.
