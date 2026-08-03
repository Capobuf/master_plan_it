# Contract — Contract occurrence generation

Feature: `004-contracts-and-projects`  
Status: `PROPOSED TARGET — PLAN COMPLETE`

## Source identity

Each expected occurrence has a deterministic immutable tenant-scoped source key derived from tenant, contract, term/rule, planning year and occurrence. Concrete canonical serialization/hash is implemented once and protected by unique tenant index.

## Expected/history dataset

`ContractGenerationHistoryQuery` returns ordered occurrence DTOs with term/rule/year/period, expected values, source key, current/deleted Expense link, Actual confirmation/system-managed state, suppression and generation/deletion/resume actor/time plus revision links.

It is control/history data and never an economic source.

## Synchronization

For each expected occurrence inside bounded transaction:

1. validate tenant/term/year and lock source occurrence/exception;
2. skip active generation exception;
3. when current row exists and is user-authoritative, return unchanged;
4. when current row exists, is Actual `ToConfirm` and `is_system_managed=true`, update only contract-derived fields through Feature 003 Action while preserving source key;
5. otherwise create exactly one Actual `ToConfirm`, system-managed, through Feature 003 Action;
6. audit typed result.

Manual Expense-row modification or `ConfirmActual` sets system-managed false. Synchronization never re-enables it and never overwrites manual/confirmed records.

## Delete choice

`DeleteGeneratedExpense` requires explicit choice:

- allow regeneration: delete through Expense Action; no exception; later sync may recreate;
- prevent regeneration: delete and insert one non-economic exception for same source key in coordinated transaction.

No default/implicit choice. Exception contains no monetary values.

## Resume/manual year

- resume: remove exception and leave occurrence missing;
- resume-and-generate: remove exception and generate once atomically;
- generate-year: validate same-tenant year, term/rule applicability, missing current key and no suppression, then create once.

Existing current key returns `GENERATION_SOURCE_DUPLICATE`; suppression returns `GENERATION_SUPPRESSED`; invalid coverage returns `GENERATION_NOT_APPLICABLE`.

## Revision interaction

Contract/term restore revalidates overlap and expected future occurrence set but never modifies/deletes existing Expense, source keys or exceptions. Expense restore preserves immutable source key and defaults generated restored row to user-authoritative unless current validated state proves otherwise.

## Authorization

Separate abilities: view history, generate occurrence, suppress, resume, Expense delete and Actual confirm. Permission never bypasses tenant, source uniqueness, applicability or no-overwrite.

## Tests

- create/update/second-sync idempotency;
- manual/confirmed no-overwrite;
- deletion choice and atomic exception;
- resume and resume-generate;
- selected-year applicability/duplicate/suppression;
- concurrent sync/manual generation one row;
- restore history/source preservation;
- control data excluded from economic totals;
- permission/inactive/deactivated/cross-tenant paths;
- explicit rollback/failure coherence.
