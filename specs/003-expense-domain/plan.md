# Implementation plan — Feature 003 Expense domain

Status: `PLAN COMPLETE AND MERGED; IMPLEMENTATION BLOCKED UNTIL /speckit.analyze PASSES`  
Dependencies: Features 001, 002 and 007; shared revision infrastructure

## Summary

Implement decimal Money/VAT/allocation primitives and the Expense aggregate with independent Estimate, Quote and Actual rows. The current aggregate has one identity, operational snapshots, soft-delete infrastructure, Actual confirmation and no replacement-state graph. Confirmation stops contract synchronization but does not remove authorized version/update/delete operations.

## Constitution check

Passes C-02, C-03, C-04, C-05, C-07, C-10, C-11 and C-12. Float arithmetic, economic observers, audit-as-source, immutable-Actual target and `Active/Replaced/Cancelled` current state are prohibited.

## Target files

### Money

- `app/Domain/Money/Data/Money.php` immutable decimal string/currency value;
- `VatBreakdown.php`;
- `MoneyCalculator.php` BCMath add/subtract/multiply/round;
- `VatCalculator.php` included/excluded VAT;
- `MonthlyAllocator.php` exact residual distribution.

### Expense data/enums

- `ExpenseKind`, `ExpenseType`, `ActualConfirmationState`, `Distribution`;
- `SaveExpenseData`, `SaveExpenseRowData`, typed Result DTOs.

### Models/migrations

- `Expense`, `ExpenseRow` and factories;
- current/non-deleted scopes are explicit query methods, not global magic that can hide migration/admin data;
- attachments use shared table/Actions.

### Actions

- `CreateExpense`;
- `UpdateExpense` aggregate save;
- `ConfirmActual`;
- `DeleteExpense`, `DeleteExpenseRow`;
- `RestoreExpenseRevision`;
- attachment upload/delete Actions shared through parent authorization.

No `ReplaceExpenseRow` Action in the target model.

### Queries/UI

- `ExpenseRegisterQuery` returns register DTOs and server totals;
- `ExpenseDetailQuery` includes rows, attachments and revision summary;
- `ExpensePolicy`;
- Filament `ExpenseResource`, relation manager/custom editor for rows, revision/history page.

## Aggregate transaction

Create/update receives full header plus intended current row set, validates tenant/master data/context, calculates every row server-side and persists one revision batch.

Existing rows use ID + expected `lock_version`; new rows have no ID. Missing existing rows are not implicitly deleted: deletion must be explicit in input and authorized, preventing UI serialization bugs from removing data.

Each mutation:

1. authorizes exact ability;
2. validates tenant/current references;
3. locks aggregate/current rows when replacing the submitted state;
4. checks stale versions;
5. calculates Money/VAT/date allocation;
6. creates revision batch;
7. writes current models;
8. links vendor snapshots;
9. writes minimized audit;
10. commits.

No deadlock retry at launch.

## Invariants

- Expense has at least one current non-deleted row after transaction;
- project XOR contract;
- Ordinary/Plafond fields valid;
- Estimate/Quote non-negative; Actual may be negative;
- non-zero amount has VAT/default;
- quantity × unit price derives entered amount when unit price present;
- spend date XOR complete period;
- monthly allocation sums exactly to Net;
- Extra and Plafond funding mutually exclusive;
- funded Plafond same tenant/year and kind Plafond;
- Actual confirmation only on Actual;
- generated source key unique and immutable;
- manual update to generated row sets `is_system_managed=false`;
- confirmation sets status/actor/time and `is_system_managed=false`;
- delete removes current economic contribution;
- restore revalidates all current rules and creates a new snapshot.

## Money rules

MySQL input/intermediate `DECIMAL(19,6)`, results `DECIMAL(19,2)`. PHP receives strings. `Money` rejects exponent notation, scale overflow and mixed currencies.

Rounding is half-up at documented result boundaries. Monthly allocation computes high-precision shares, rounds each month and assigns residual deterministically to the last eligible month according to distribution order.

## Revision integration

Use Overtrue snapshot strategy. Versioned fields are business values only. One aggregate save links Expense and changed rows to `revision_batches`. The package restore action is disabled/replaced by `RestoreExpenseRevision`, which rebuilds typed input and calls current validation.

Deletion history is accessed from a dedicated page using `withTrashed`; ordinary resource queries never expose deleted rows.

## Migration mapping

Legacy current row selection is deterministic from verified state/replacement evidence. Accepted current values become one current row. Useful prior states may become initial operational snapshots and audit metadata, but never current copies. Mapping must reconcile exact current totals before apply.

## Tests

### Pure accounting

- Money parsing/scale/currency;
- VAT included/excluded, zero, negative Actual, half-cent boundaries;
- quantity multiplication;
- date modes and all/start/end allocation residuals.

### Aggregate integration

- independent types/no progression;
- create/update/explicit delete;
- confirmation and system-managed transition;
- Extra/Plafond rules;
- project/contract XOR;
- tenant/permission/concurrency;
- aggregate revision batch, compare/restore/deleted exclusion;
- attachments transaction/failure handling;
- legacy mapping fixtures.

Dusk only for row-editor JS/focus/action menu and reinforced delete confirmation if not provable below browser.

## Sequence

1. Money/VAT/allocation tests and value objects;
2. migrations/models/factories;
3. revision package smoke and batch infrastructure;
4. create/update/delete/restore Actions;
5. Actual confirmation/generated ownership behavior;
6. Policies/queries;
7. Filament editor/register/history;
8. migration mapping fixtures;
9. full accounting/tenant/browser gate.

## Post-design check

Pass. The Expense aggregate remains the sole current monetary source and does not absorb reporting or contract orchestration.
