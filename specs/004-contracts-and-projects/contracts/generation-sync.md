# Contract — Contract expense generation

Feature: `004-contracts-and-projects`  
Status: `CLARIFIED — PLAN REQUIRED`  
Purpose: expected occurrences, immutable source identity, append-missing synchronization, suppression/resume, manual year generation, and history display.

## Inputs

Every operation receives:

- authenticated actor and explicit tenant context;
- authorized contract and optional term/rule;
- optional planning year;
- expected `lock_version` where a current record changes;
- explicit operation: `sync`, `delete_allow_regeneration`, `delete_and_suppress`, `resume`, `resume_and_generate`, or `generate_year`;
- optional reason for suppression/deletion.

Dates are ISO. Money is decimal strings. Authorization and tenant ownership are checked before contract, expense, exception, or source-key metadata is returned.

## Source key

Every expected occurrence has one deterministic tenant-scoped source key containing at minimum:

```text
tenant + contract + contract-term-or-rule + planning-year + occurrence
```

The concrete canonical encoding and hash are fixed in `/speckit.plan`. The business identity fields are immutable. A non-null key is unique inside one tenant.

## Expected occurrence dataset

For each contract, the generator produces an ordered dataset containing:

- contract/term/rule identity;
- planning year and occurrence period;
- expected monetary/date/vendor/cost-center values;
- source key;
- linked current expense ID when present;
- generation exception ID when suppressed;
- state: `Generated`, `UserModified`, `DeletedRegenerationAllowed`, `Suppressed`, or `Missing`;
- generation, deletion, suppression, and resume metadata;
- links to current expense and revision histories.

This is a control/history dataset, not an economic total source.

## Synchronization

For every expected occurrence:

1. fail if tenant context and contract tenant differ;
2. if a current expense exists for the source key, leave it unchanged;
3. if a generation exception exists, skip it;
4. otherwise create exactly one missing expense through the owning Expense Action;
5. record generation actor/system operation and correlation ID.

Synchronization never updates or replaces an existing generated expense, even when contract terms later change. The expense becomes user-authoritative after creation.

## Delete generated expense

The UI must ask whether the occurrence may be generated again.

### Delete only

- delete the current expense through the versioned Expense delete Action;
- do not create an exception;
- mark the control history as `DeletedRegenerationAllowed`/`Missing`;
- a later synchronization may recreate it if still expected.

### Delete and suppress

- delete the current expense;
- insert one generation exception for the source key;
- mark state `Suppressed`;
- later synchronization must skip it.

The Expense deletion and exception insertion are one transaction when suppression is selected. File cleanup follows the Expense contract. The exception contains no economic amount used by reports.

## Resume

### Resume generation

Delete the generation exception only. The occurrence becomes `Missing`; future synchronization may generate it.

### Resume and generate now

Delete the exception and create the missing expense in one transaction. If another current expense already owns the source key, return the existing expense and do not create a duplicate.

## Generate for selected year

An authorized actor chooses one planning year. The Action:

1. proves the year belongs to the tenant;
2. proves a contract term/rule applies;
3. computes the one expected source key;
4. rejects an existing current expense;
5. rejects an active suppression unless the actor explicitly uses resume-and-generate;
6. creates one expense through the same generator path;
7. records manual-generation actor and correlation ID.

This is not a free-form Expense copy and cannot bypass contract applicability.

## Revision interaction

- Contract and term revision restore revalidates term overlap and generation identities.
- Restore never modifies an existing generated expense.
- A contract revision may alter future expected occurrences only.
- Existing source keys and generation history remain traceable.
- Deleting/restoring an Expense does not delete contract history events.

## Authorization

Separate policy abilities cover:

- view generation history;
- run synchronization;
- generate selected year;
- delete a generated expense;
- suppress occurrence;
- resume occurrence.

No permission bypasses tenant scope, source-key uniqueness, term applicability, or no-overwrite.

## Transaction and concurrency

Each write opens one owning transaction. Contract/term/exception rows needed for consistency are locked narrowly. Repeating the same source-key operation is deterministic and cannot duplicate an expense or exception. Stale `lock_version` returns conflict without partial state.

## Errors

Stable errors shall distinguish:

- unauthorized or hidden tenant/resource;
- invalid year/term applicability;
- duplicate current source key;
- active suppression;
- missing exception on resume;
- stale version;
- invalid contract state;
- file/domain rollback failure.

## Test contract

1. sync creates each missing unsuppressed occurrence once;
2. second sync is idempotent;
3. existing user-edited expense is never overwritten;
4. delete-only permits later regeneration;
5. delete-and-suppress prevents later regeneration;
6. resume makes occurrence missing;
7. resume-and-generate creates once;
8. manual selected-year generation validates applicability and uniqueness;
9. exception/control history never enters economic totals;
10. contract and expense revision history remains linked;
11. every operation has permission, inactive-tenant, deactivated-user, and cross-tenant deny tests;
12. transaction rollback leaves expense, exception, file, and history state coherent.
