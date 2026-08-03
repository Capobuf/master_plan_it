# Contract — Expense editor

Feature: `003-expense-domain`  
Status: `PROPOSED TARGET — PLAN COMPLETE`

## Input

One typed aggregate request contains authorized tenant context, Expense header, intended current rows, explicit row deletions, expected lock versions and attachment operations. Money is normalized decimal strings; dates ISO. Existing row omission alone never means delete.

## Output

Typed result with Expense/row IDs, calculated Net/VAT/Gross, confirmation/system-managed state, new lock versions, revision batch and audit correlation ID.

## Header/rows

Header: tenant, year, cost center, kind, title/notes and project XOR contract context.

Rows: position, vendor, independent type Estimate/Quote/Actual, description, quantity/unit price/amount, VAT input, calculated components, Extra/Plafond funding, date mode/distribution, Actual confirmation, generated source metadata and attachments.

Conditional UI visibility mirrors these fields but server validation is authoritative.

## Save transaction

`CreateExpense`/`UpdateExpense` authorize exact permission, validate same-tenant/current references, lock current aggregate where required, reject stale versions, calculate all values, create one revision batch, persist explicit changes/deletions, link snapshots, audit and commit.

File upload uses temporary/private path and is finalized only with coherent DB result. Cleanup failure is surfaced/recorded; it is not ignored.

No automatic retry and no observer/model-hook economic side effect.

## Lifecycle

- Estimate, Quote and Actual are independent; no mandatory progression.
- Actor with permission may update/delete Actual.
- `ConfirmActual` is separate permission/Action; it records actor/time and makes generated row user-authoritative.
- Confirmation does not make Actual permanently immutable.
- Delete removes current contribution and ordinary visibility; history remains in revisions/audit.
- Restore builds typed input from a snapshot, revalidates current rules and creates a new revision.
- Generated source key is immutable and cannot be changed by editor/restore.

## Authorization

Use stable abilities from `permission-catalogue.md`: view/create/update/delete/view-revisions/restore-revision/confirm-actual/print/export plus attachment abilities. Role names do not grant behavior. Same-tenant, active state and domain invariants remain mandatory.

## Concurrency/errors

Stale aggregate or row version returns `STALE_VERSION` with no partial write. Other stable codes come from `error-catalogue.md`; other-tenant IDs return safe denial/not-found.

## Test contract

- full aggregate create/update and explicit deletion;
- missing-row omission does not delete;
- independent row types;
- exact calculations and conditional validation;
- Actual update/confirm/delete/restore;
- generated manual override and source-key immutability;
- aggregate revision batch;
- attachment rollback/failure;
- tenant/permission/concurrency;
- register/report values equal server result.
