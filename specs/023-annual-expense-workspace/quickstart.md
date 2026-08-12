# Quickstart: Validate Slice 023 End to End

**Purpose**: runnable validation guide for the future implementation; no commands in this file were executed during this design-only work

## Prerequisites

- Docker and Docker Compose available.
- Repository checkout containing the implemented Slice 023.
- Services isolated from any shared or production database.
- `.env.testing` points to MySQL service `mysql`, database `master_plan_it_test`, strict SQL mode.
- The primary integration owner has integrated consolidated migrations, core projection, common errors, routes and permission updates.
- Xdebug is installed in the `laravel.test` image and enabled only for coverage runs.

## Start the Isolated Stack

```bash
docker compose build laravel.test
docker compose up -d mysql laravel.test frontend
docker compose ps
```

Verify that `mysql`, `laravel.test` and `frontend` are healthy/running before proceeding.

## Greenfield Schema Gate

The Product Owner confirmed on 2026-08-12 that no real data must be preserved. The following reset is authorized only for the protected local/test database after the implementation adds an explicit environment guard:

```bash
docker compose exec -T -u sail laravel.test \
  php artisan migrate:fresh --seed --env=testing --force
```

Expected:

- exit code 0 on `APP_ENV=testing`, MySQL host `mysql`, database `master_plan_it_test` and strict SQL mode;
- Tenant Net/Gross demo fixtures, active years and canonical Expenses are present;
- Expense schema has no `state`, `closure_outcome`, `closed_at`, `closed_by_user_id`;
- Tenant schema contains `economic_basis` and nullable `economic_basis_locked_at`;
- all authoritative money columns are `DECIMAL(...,2)`.

Run the guard test that proves refusal outside protected environments:

```bash
docker compose exec -T -u sail laravel.test \
  php artisan test tests/Architecture/DevelopmentEnvironmentTest.php
```

Do not execute `migrate:fresh`, `db:wipe` or volume deletion against any non-test environment. This authorization does not create an application purge capability.

## Focused Test Commands

### Backend schema, domain and API

```bash
docker compose exec -T -u sail laravel.test \
  php artisan test \
  tests/Feature/Expenses/AnnualExpenseSchemaTest.php \
  tests/Feature/PlatformOperations/TenantEconomicBasisTest.php \
  tests/Feature/Expenses/AuthoritativeExpenseTest.php \
  tests/Feature/Expenses/ExpenseActionRollbackTest.php \
  tests/Feature/Api/Expenses/AnnualExpenseApiTest.php \
  tests/Feature/Api/Reporting/AnnualExpenseProjectionApiTest.php
```

### Accounting MySQL

```bash
docker compose exec -T -u sail laravel.test \
  php artisan test --testsuite=Accounting
```

Expected: exact decimal strings, no query growth per Expense, stale rollback, and reconciliation across all projection consumers.

### Economic line and branch coverage

```bash
docker compose exec -T -u sail -e XDEBUG_MODE=coverage laravel.test \
  php -d xdebug.mode=coverage vendor/bin/phpunit \
  --configuration phpunit.economic-coverage.xml \
  --coverage-cobertura storage/logs/economic-coverage.xml \
  --path-coverage

docker compose exec -T -u sail laravel.test \
  php tests/Support/assert-economic-coverage.php \
  storage/logs/economic-coverage.xml 100 100
```

Expected: the first command generates Cobertura line/branch metrics; the second exits 0 only when both `line-rate` and `branch-rate` equal `1.0` and at least one valid branch exists. A missing Xdebug driver or missing metric is a failure, not a skipped pass.

### Frontend

```bash
docker compose exec -T frontend npm test -- --run
docker compose exec -T frontend npm run lint
docker compose exec -T frontend npm run build
```

Expected: API adapter, top shell, settings, editor, register, document, Budget and Report component tests pass; TypeScript has no legacy lifecycle fields.

## Scenario 1 — Official Basis and Lock

1. Enter a Net Tenant as a user with `tenant-settings.view|update`.
2. Open General Settings and confirm `economic_basis=net`, `economic_basis_locked_at=null`.
3. Save Gross using the current Tenant `lock_version`.
4. Confirm the version increments, audit event `tenant.settings.updated` exists, and persisted ExpenseRow Net/VAT/Gross values do not change.
5. Use a locked Tenant factory/fixture and attempt to switch its basis.
6. Confirm HTTP 409 `BUDGET_STATE_CONFLICT`, correlation ID, unchanged row and no success audit.
7. Repeat with missing ability, inactive user, inactive Tenant and stale version; confirm 403/409 and zero side effects as appropriate.

## Scenario 2 — Tenant/Year Top Shell

1. As Platform Administrator, enter Tenant A and choose its 2025 year.
2. Navigate through `/spese`, `/budget` and `/report`; confirm all requests carry Tenant A context and PlanningYear A/2025 ID.
3. Open an Expense detail, make an unsaved edit and request year 2026; first cancel, then confirm the dirty guard.
4. Confirm the accepted change routes to `/spese`, shows an informational message and never fetches the old Expense under 2026.
5. Switch to Tenant B while a delayed Tenant A request is pending.
6. Confirm the shell clears the selected year/data, loads only Tenant B years and ignores the late Tenant A response.
7. Repeat as a single-Tenant user; confirm Tenant is visible but not selectable.
8. Verify keyboard operation, focus visibility, responsive menu and both light/dark themes.

## Scenario 3 — Authoritative Expense

Under Net basis, create Economic Year 2025 Expense with:

| Row | Type | Net | VAT 22% | Gross | Current | Real date |
|---|---|---:|---:|---:|---|---|
| 1 | Estimate | `100.00` | `22.00` | `122.00` | No | — |
| 2 | Quote | `110.00` | `24.20` | `134.20` | Yes | — |
| 3 | Actual | `40.00` | `8.80` | `48.80` | No | `2025-06-01` |
| 4 | Actual | `65.00` | `14.30` | `79.30` | No | `2026-01-10` |
| 5 | Actual | `-5.00` | `-1.10` | `-6.10` | No | `2026-02-10` |

Expected:

- Economic Year stays 2025 for all rows.
- Current Planning = `110.00` Net / `134.20` Gross.
- Actual = `100.00` Net / `122.00` Gross.
- Document preserves all five rows and identifies row 2 as current.
- One aggregate RevisionBatch and one audit event exist.
- No Expense lifecycle field/action appears.

Then validate negative paths:

1. No planning selected despite Estimate/Quote → 422, no rows created.
2. Two planning rows selected → 422, no rows created.
3. Actual-only Expense without selection → success, planning totals zero.
4. Negative Estimate/Quote → 422; negative/zero Actual → success.
5. JSON numeric money, `1.001`, `1e2`, localized `1,00`, overflow → 422.
6. Selected row from another Expense/Tenant or deleted in same update → safe failure and rollback.

## Scenario 4 — Optimistic Lock and Aggregate Rollback

1. Read one Expense at root version 3 and row version 2 in two clients.
2. Client A updates Quote and selection; expect success, incremented versions, one revision/audit.
3. Client B submits its stale snapshot; expect 409 `STALE_VERSION`.
4. Confirm none of Client B's root, row, selected-pointer, revision or audit changes persist.
5. Inject a failure in RevisionBatch creation and separately in audit recording in an integration test.
6. Confirm each failure rolls back the business update and version increments.

## Scenario 5 — Tenant Isolation and Permissions

For each settings, Expense list/detail/create/update/preview, Budget and Report endpoint:

1. Allow same Tenant with required ability.
2. Deny required ability missing.
3. Deny inactive user and inactive Tenant.
4. Use Tenant B Expense, Row, PlanningYear, Vendor and CostCenter IDs from Tenant A.
5. Confirm 404 `RESOURCE_NOT_FOUND` or field-safe validation without any foreign label/amount/existence disclosure.
6. Confirm rejected calls produce no business mutation, revision or success audit.

## Scenario 6 — Surface Reconciliation

Using Scenario 3 plus at least two additional Expenses:

1. Read Expense Document totals.
2. Read the same item and filtered totals in Expense Register.
3. Read `/api/v1/budget?planning_year_id=...`.
4. Read `/api/v1/reports?planning_year_id=...&group_by=expense` and expand drill-down lines.
5. Sum only lines marked as contributing to current planning and all Actual lines using exact decimal arithmetic.
6. Confirm every per-Expense and annual total matches at the cent in Net basis.
7. Switch an unlocked fixture Tenant to Gross and repeat; persisted row components remain unchanged while `official` changes.
8. Soft-delete/non-current fixture rows and confirm they remain visible only where history/document rules allow but do not contribute to current totals.
9. Force a projection invariant failure in a test; confirm 500 `ECONOMIC_RECONCILIATION_FAILED`, correlation ID and no UI fallback total.

## Scenario 7 — Removed Lifecycle and Parity

1. Run `php artisan route:list --path=api/v1/expenses` and confirm close/move routes are absent.
2. Inspect target schema and API fixtures for all forbidden lifecycle fields.
3. Confirm Register has no state filter/column/badge and bulk actions have no close/move variants.
4. Confirm Document and editor have no Close/Reopen controls and an economic update does not change any hidden lifecycle state.
5. Run HTTP adapter and component tests together; confirm the shared `ProjectionTotals` shape is accepted on Document, Register, Budget and Report.
6. Search compiled Frontend/source and Backend target DTO/Resource fields through stable architecture assertions, not a manual compatibility fallback.

## Full Gate

```bash
docker compose exec -T -u sail laravel.test composer test:static
docker compose exec -T -u sail laravel.test composer test:prepare
docker compose exec -T -u sail laravel.test composer test:accounting
docker compose exec -T -u sail laravel.test composer test:application
docker compose exec -T frontend npm run verify
```

Then re-run the economic coverage Gate and focused browser acceptance. Record actual commands, exit codes and concise evidence; never report unexecuted tests as passing.

## Done Evidence

- Fresh MySQL schema/seed result and non-test refusal.
- Focused/backend/full suite commands with exit code.
- Xdebug line and branch percentages, both 100%.
- Frontend test/lint/build result.
- Reconciliation table for Net and Gross.
- Domain, security/tenancy and surface parity reviews with no CRITICAL/HIGH.
- Confirmation that shared-owner changes were integrated once and no permanent docs were updated before verified implementation.
