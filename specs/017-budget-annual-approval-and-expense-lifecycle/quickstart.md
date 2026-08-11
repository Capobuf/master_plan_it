# Quickstart verification — Feature 017

## Prerequisites

- Compose services use PHP 8.3.32, Node 22 and MySQL 8.4.
- Dependencies and `.env.testing` are prepared according to `docs/OPERATIONS.md`.
- Test migrations are forward-only. Never use `migrate:fresh`, `db:wipe`, `RefreshDatabase` or
  `DatabaseTruncation`.

## Automated verification

```bash
docker compose exec -T laravel.test composer test:static
docker compose exec -T laravel.test composer test:prepare
docker compose exec -T laravel.test composer test:accounting
docker compose exec -T laravel.test composer test:application
docker compose exec -T laravel.test composer verify

docker compose exec -T laravel.test php artisan route:list --path=api/v1/budget
docker compose exec -T laravel.test php artisan route:list --path=api/v1/expenses
docker compose exec -T laravel.test php artisan route:list --path=api/v1/contracts
docker compose exec -T laravel.test php artisan route:list --path=api/v1/reports

docker compose exec -T frontend npm run lint
docker compose exec -T frontend npm run build
```

Historical benchmark:

```bash
docker compose exec -T laravel.test php artisan test tests/Performance/HistoricalBudgetBenchmarkTest.php
```

## End-to-end acceptance

1. Create an annual Expense with Estimate and multiple Quotes; select one planning row and verify only it enters
   proposed totals while alternatives remain visible.
2. Approve several Expenses atomically, including zero; execute a zero-delta reallocation and verify initial,
   variation and current totals.
3. Add positive and negative Actual rows. Verify immediate totals, same-year rejection, close, descriptive edit
   without reopen and economic edit with reopen.
4. Close the Budget and mutate an Expense. Verify visible warning, unchanged closed state, revision and current
   Report update.
5. Create a Plafond and funded Expenses. Verify consumption, no double count, distinct detail and visible overrun.
6. Link Contract to Project, generate annual planning and verify no Actual is created. Override/select planning,
   change terms and verify the difference is proposed without overwrite.
7. Move a planned Expense to another year and create a next-year negative credit linked to its origin. Verify
   original Actual rows remain unchanged.
8. Compare Budget and Report totals and all five grouping dimensions.
9. Select as-of cutoffs before/after a multi-record approval and deletion. Verify complete states, old labels,
   read-only UI and explicit rejection before activation.
10. Repeat every tenant-bound mutation with missing ability, foreign Tenant, inactive user/Tenant and stale locks;
    confirm no side effect or data leakage.

## Visual verification

Inspect `/spese`, Expense create/edit/detail, `/contratti`, `/budget` and `/report` on desktop/mobile and dark
mode. Cover loading, empty, validation, stale, closed warning, approval modal, selected-plan affordance, Plafond
overrun and historical read-only states.
