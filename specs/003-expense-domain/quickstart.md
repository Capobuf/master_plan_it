# Verification quickstart — Feature 003 Expense domain

Future commands; none were executed during planning.

## Fixture

Create two tenants, years, cost centers, vendors and a Plafond. In tenant A create independent Estimate, Quote, Actual ToConfirm/Confirmed, negative Actual, Extra and Plafond-funded rows. Include one generated row, attachments, revisions and deleted rows.

## Pure accounting

```bash
./vendor/bin/sail artisan test tests/Accounting/Unit/Money
./vendor/bin/sail artisan test tests/Accounting/Unit/VatCalculatorTest.php
./vendor/bin/sail artisan test tests/Accounting/Unit/MonthlyAllocatorTest.php
```

## Aggregate integration

```bash
./vendor/bin/sail artisan test --filter=ExpenseAggregate
./vendor/bin/sail artisan test --filter=ActualConfirmation
./vendor/bin/sail artisan test --filter=ExpenseRevision
./vendor/bin/sail artisan test --filter=ExpenseTenantIsolation
```

## Acceptance path

1. Create Estimate, Quote and Actual without predecessors.
2. Verify Net/VAT/Gross and monthly allocation exact strings.
3. Modify/confirm a generated Actual and verify system-managed becomes false.
4. Delete a row and confirm every current query/output excludes it.
5. Compare/restore an aggregate revision and verify a new revision batch.
6. Submit stale lock version and receive `STALE_VERSION` without partial change.
7. Attempt other-tenant vendor/Plafond/project/contract/attachment and receive safe denial.

Run focused Dusk only for browser-owned row editor behavior:

```bash
./vendor/bin/sail artisan dusk --filter=ExpenseEditorTest
```

## Cleanup

Transaction rollback or run-ID targeted deletion only. Never reset/truncate the persistent test DB.

## Success

Accounting fixtures reconcile exactly; no float, replacement-state current row, revision/audit contribution, automatic overwrite or cross-tenant link exists.
