# Verification quickstart — Feature 003 Expense domain

Future commands; none were executed during planning.

## Fixture

Create two tenants, years, cost centers, vendors and a Plafond. In tenant A create independent Estimate, Quote, Actual ToConfirm/Confirmed, negative Actual, Extra and Plafond-funded rows. Include one generated row, Expense-level and row-level attachments, multiple file-set revisions and deleted rows. Set an explicit small test quota while retaining the production-default assertion of 2 GiB.

## Pure accounting

```bash
./vendor/bin/sail artisan test tests/Accounting/Unit/MoneyTest.php
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
5. Upload approved files up to 10 MiB; reject empty, oversized, mismatched MIME/extension, unlisted and foreign-parent files without orphans.
6. Compare/restore an aggregate revision and verify data plus the exact complete attachment set create a new revision batch.
7. Exceed quota concurrently and verify one atomic denial; lower quota to zero and verify no deletion plus blocked new payloads. At zero, save a data-only revision with unchanged attachments and verify it succeeds, references the existing payload versions and adds zero usage.
8. Delete an attachment/row and restore its earlier file set while the Expense exists; permanently delete the Expense and verify every payload is purged and restore is unavailable.
9. Submit stale lock version and receive `STALE_VERSION` without partial change.
10. Attempt other-tenant vendor/Plafond/project/contract/attachment and receive safe denial.

Run focused Dusk only for browser-owned row editor behavior:

```bash
./vendor/bin/sail artisan dusk --filter=ExpenseEditorTest
```

## Cleanup

Transaction rollback or run-ID targeted deletion only. Never reset/truncate the persistent test DB.

## Success

Accounting fixtures reconcile exactly; no float, replacement-state current row, revision/audit contribution, automatic overwrite or cross-tenant link exists.
