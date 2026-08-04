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

Current contract/term revision restore revalidates overlap and expected future occurrence set but never modifies/deletes existing Expense, source keys or exceptions. It is denied for deleted contracts/terms and for snapshots that would restore a terminally deleted stable term identity. Expense restore preserves immutable source key and defaults generated restored row to user-authoritative unless current validated state proves otherwise.

## Source deletion

Synchronization excludes terminally deleted contracts and terms. Their deletion locks linked current generated rows, marks them user-authoritative and records immutable source contract/term IDs, contract title, term date range, deletion timestamp and nullable reason. Source keys remain unchanged. No deletion, restore, import or synchronization path cascades to an Expense, reactivates the source or creates a future occurrence from it.

## Authorization

Separate abilities: view history, generate occurrence, suppress, resume, Expense delete and Actual confirm. Permission never bypasses tenant, source uniqueness, applicability or no-overwrite.

Tenant deletion-reason policy changes require global Administrator plus protected `deletion-reason-setting.manage` and an explicit target tenant; contract delete and term delete retain their own resource abilities and do not inherit the setting-management ability.

## Tests

- create/update/second-sync idempotency;
- manual/confirmed no-overwrite;
- deletion choice and atomic exception;
- resume and resume-generate;
- selected-year applicability/duplicate/suppression;
- concurrent sync/manual generation one row;
- restore history/source preservation;
- contract/term deletion provenance, user-authoritative transition, no cascade and no future generation;
- control data excluded from economic totals;
- permission/inactive/deactivated/cross-tenant paths;
- explicit rollback/failure coherence.
