# API contract — Feature 017

All endpoints are Sanctum SPA, active-user, active-Tenant, tenant-context and ability protected. Foreign Tenant
identifiers return 404 without data leakage. Monetary values are decimal strings.

## Budget

### `GET /api/v1/budget`

Query: `planning_year_id` required; optional `cost_center_id`; optional `as_of` as ISO date-time or `YYYY-MM-DD`.

Response data includes:

```json
{
  "mode": "current",
  "requested_as_of": null,
  "cutoff_utc": null,
  "read_only": false,
  "budget": {"planning_year_id": 1, "year": 2027, "state": "approved", "lock_version": 3, "warning": null},
  "summary": {
    "currency": "EUR", "official_basis": "net",
    "proposed": "10000.00", "initial_approved": "9000.00", "approved_variations": "500.00",
    "approved_current": "9500.00", "actual": "8000.00", "residual": "1500.00",
    "variance": "-1500.00", "utilization_percentage": "84.21", "plafond_overrun": "0.00",
    "open_expenses": 2, "closed_expenses": 8, "unapproved_actual_expenses": 1
  },
  "expenses": []
}
```

Historical response uses `mode=historical`, normalized `cutoff_utc`, `read_only=true`, includes the reconstructed
current rows under each Expense and a `historical_context` containing approval operations plus the linked Cost
Centers, Projects, Contracts, Contract terms and Vendors at the cutoff. A cutoff before activation returns 422
code `HISTORY_BEFORE_ACTIVATION`.

### `POST /api/v1/budget/{planningYear}/approval-decisions`

Ability: `expense.update`.

```json
{
  "budget_lock_version": 2,
  "effective_date": "2027-01-15",
  "reason": "Prima approvazione",
  "items": [
    {"expense_id": 10, "expense_lock_version": 4, "approved_amount": "7000.00"},
    {"expense_id": 11, "expense_lock_version": 2, "approved_amount": "0.00"}
  ]
}
```

The server chooses initial/variation, calculates deltas and commits atomically. Duplicate, foreign, wrong-year,
negative or stale items reject the whole operation.

### `POST /api/v1/budget/{planningYear}/close`

Ability: `planning-year.update`. Body `{ "lock_version": 3 }`. State becomes closed. There is no implicit reopen.

## Expense delta and lifecycle

Expense create/update adds nullable `current_planning_row_id`; response adds `state`, `closure_outcome`,
`approved_amount`, `approved_basis`, `current_planning_row_id`, `planned`, `actual`, `residual`, `variance`,
`variance_final`, movement/credit links, recent authorized `revision_activity` and optional `warnings`.

Expense create accepts optional `credit_for_expense_id`. When present, the referenced Expense must belong to
the same Tenant and an earlier Planning Year; every submitted row must be a negative Actual in the destination
year. The link is immutable after creation. Foreign origins fail without disclosure.

Rows expose no confirmation state or period distribution. Planning date is optional; Actual spend date is required
and same-year. Project and Contract may coexist only with matching Project.

### `POST /api/v1/expenses/{expense}/close`

Ability: `expense.update`. Body `{ "lock_version": 4, "outcome": null }`, where outcome may be
`not_incurred`, `cancelled`, `moved`.

### `POST /api/v1/expenses/{expense}/move`

Ability: `expense.update` and `expense.create`.

Body: `{ "lock_version": 4, "target_planning_year_id": 2 }`. Returns origin and destination. Destination copies
header/planning context, starts open/unapproved, and contains no Actual rows.

The former `/rows/{row}/confirm` endpoint is removed.

## Contract delta

Contract create/update/read adds nullable `project_id`. Generate/synchronize operates once per Contract/year and
returns a planning Expense/Quote. Occurrence response uses `planning_state` and optional
`expected_difference: { net, vat, gross }`; differences are expected Contract amounts minus the preserved
selected or manual Quote. A newly generated Quote is initially unselected and remains system-managed until the
VCO selects or edits it; synchronization never overwrites a selected/manual Quote. It never returns an Actual
confirmation state.

## Report

### `GET /api/v1/reports`

Query: required `planning_year_id`; optional `group_by=cost_center|project|contract|vendor|expense`, matching
dimension filter IDs, pagination and `as_of`.

Response uses the same summary contract as Budget. Each grouped row includes planned, approved, actual,
residual, variance, open/closed and unapproved-Actual counts, plus historical labels when as-of is supplied.
Per-Expense state, outcome and variance finality remain available in the Budget annual detail.

## Stable errors/warnings

- `STALE_VERSION` — 409.
- `BUDGET_CLOSED` — non-blocking response warning.
- `APPROVED_DIMENSION_REALLOCATION_REQUIRED` — 422.
- `ACTUAL_OUTSIDE_EXPENSE_YEAR` — 422.
- `HISTORY_BEFORE_ACTIVATION` — 422.
- `TENANT_RELATION_MISMATCH` — 404/422 according to existing anti-leak contract.
