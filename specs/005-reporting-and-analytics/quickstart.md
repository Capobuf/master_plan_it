# Verification quickstart — Feature 005 Reporting and analytics

Future commands; none were executed during planning.

## Fixture

Seed two tenants. The Slice 2 fixture starts with current manual Estimate/Quote/Actual rows, Net/VAT/Gross, Extra and Plafond cases plus deleted/revision rows that must be excluded; it does not require Feature 004, scenarios or BudgetVersion. T005-026 adds a representative manual-Expense performance fixture. After T004-005, T005-027 adds project-stage cases. The later full-feature fixture reaches 10,000 rows and adds scenario/version exclusions plus manual total-only/partial/full BudgetVersion drafts.

## Focused tests

```bash
./vendor/bin/sail artisan test tests/Accounting/Unit/EconomicEngineTest.php
./vendor/bin/sail artisan test --filter=EconomicDataset
./vendor/bin/sail artisan test --filter=BudgetVersion
./vendor/bin/sail artisan test --filter=BudgetComparison
./vendor/bin/sail artisan test --filter=ReportingParity
```

## Acceptance path

1. Enter the authorized tenant, create/edit a manual Expense, open current Budget and verify one economic query result supplies KPI/table/chart.
2. Verify independent Estimate/Quote/Actual, Net/VAT/Gross and Plafond reconciliation without Feature 004; verify project buckets later through T005-027.
3. Switch tenant official Net/Gross basis without changing stored components.
4. Publish a current snapshot during a concurrent Expense update and verify one consistent snapshot.
5. Publish manual total-only/partial version and verify missing dimensions are unavailable.
6. Change current data/settings and verify Published version unchanged.
7. Compare current/version and version/version with additions/removals/changes.
8. Export filtered and complete report/year, including more than 10,000 rows; verify scope metadata, no application row cap and exact parity.
9. Generate CSV/XLSX through private temporary artifacts, inspect exact decimal values, and inject failure to prove no partial download plus cleanup/error guidance.
10. Open print view and use browser print smoke; no server PDF service.
11. Verify other-tenant IDs and data are inaccessible.

Browser focus:

```bash
./vendor/bin/sail artisan dusk tests/Browser/Reporting/ExpenseBudgetVerticalSliceTest.php
```

## Performance evidence

On verified target hosting, record and require page/report p95 ≤2 s, CSV ≤10 s, XLSX ≤20 s, print ≤10 s, peak PHP memory ≤128 MiB and ≤5 SQL queries at 10,000 rows. In CI, require parity/scope/order, memory and query ceilings while recording time without failing on runner variability. Do not introduce row caps, cache or preaggregation without a plan amendment.

## Cleanup

Transactions or run-ID targeted cleanup only. Never reset/truncate the persistent test database.

## Success

All consumers match the same dataset, Published versions remain immutable, scopes are explicit and no current formula is duplicated outside the kernel.
