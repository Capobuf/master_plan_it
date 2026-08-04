# Implementation plan — Feature 004 Contracts and projects

Status: `PLAN COMPLETE AND MERGED; IMPLEMENTATION BLOCKED UNTIL /speckit.analyze PASSES`  
Dependencies: Features 001–003 and 007; shared revision/audit infrastructure

## Summary

Implement projects as decision context and contracts as term-based generators of Actual rows. A generated occurrence has one immutable source key. Synchronization creates or updates only a system-managed Actual `ToConfirm`; manual modification or confirmation makes it user-authoritative. Deletion may allow regeneration or create a non-economic suppression exception. Contracts/projects never contribute independent monetary totals.

## Constitution check

Passes C-03, C-04, C-05, C-07, C-10, C-11, C-12 and C-13. Economic observers, duplicate source rows, silent overwrite, project/contract totals and hidden regeneration are prohibited.

## Target files

### Persistence

- `Project`, `Contract`, `ContractTerm`, `ContractGenerationException` models and migrations;
- enums `ProjectStage`, `BillingCycle`;
- source key value object/normalizer.

### Project Actions

- `CreateProject`, `UpdateProject`, `ChangeProjectStage`, `DeleteProject`, `RestoreProjectRevision`;
- `PromoteDeferredProjects` bounded Action and command.

### Contract Actions

- `CreateContract`, `UpdateContract`, `DeleteContract`, `RestoreContractRevision`;
- `SynchronizeContractOccurrences`;
- `DeleteGeneratedExpense` coordinating Feature 003 delete plus regeneration choice;
- `SuppressContractOccurrence`, `ResumeContractOccurrence`, `ResumeAndGenerateOccurrence`, `GenerateContractOccurrenceForYear`;
- `ConfirmActual` remains Feature 003-owned.

### Queries/UI

- `ProjectListQuery`, `ContractListQuery`, `ContractDetailQuery`, `ContractGenerationHistoryQuery`;
- one Policy per resource plus explicit generation Gates;
- Filament Project/Contract Resources, term repeater/timeline and generation-history relation/page.

No `ContractAnnualizer` service unless tests prove a reusable calculation independent from generation; term values use shared Money/VAT services.

## Project rules

- stages: Idea, Proposed, Approved, Deferred, Rejected;
- Deferred requires same-tenant target planning year;
- promotion command moves eligible Deferred to Proposed once, under optimistic concurrency;
- project has no persisted total;
- stage is read by the economic kernel; an Actual remains primary regardless of later stage.

## Contract/term transaction

Contract save receives complete intended term set with explicit deletions. It locks the contract/current terms, validates same-tenant vendor/cost center, date order, non-overlap, billing cycle and decimal values, calculates term Net/VAT/Gross and records one revision batch.

Missing existing terms are not implicitly deleted unless explicitly marked, avoiding UI serialization loss.

Auto-renew creates at most one successor term with stable lineage and no overlap; it does not generate duplicate expenses.

## Source identity

Normalized source key includes tenant, contract, term/rule, planning year and occurrence identity. Persist the readable component columns plus a SHA-256 key or canonical string; unique index includes tenant. The key cannot change after occurrence creation.

Generation transaction locks the source-key range/current occurrence and exception. It then:

- returns existing user-authoritative occurrence without mutation;
- updates allowed derived fields on existing system-managed ToConfirm row;
- skips a suppressed occurrence;
- creates exactly one missing ToConfirm Actual through Feature 003 Action;
- audits result and returns a typed status.

## System-managed boundary

Allowed automatic updates are only fields derived from contract term/rule: description, vendor, cost center, quantity/price/VAT/date/distribution and calculated values. Manual update or confirmation sets `is_system_managed=false`. Synchronization never resets that flag.

Later correction, revision restore or deletion uses Feature 003 Actions and may not alter the immutable source key. Restoring a generated row as system-managed is denied unless the restored snapshot and current history prove it was never manually modified/confirmed; default safe outcome is user-authoritative.

## Deletion and suppression

`DeleteGeneratedExpense` requires explicit `allow_regeneration` boolean:

- true: delete current expense; no exception; future sync may recreate;
- false: delete and insert one generation exception for the source key.

Resume deletes the exception after authorization/audit. Resume-and-generate performs both inside one transaction. Manual-year generation validates applicability, missing source and suppression state.

## Notifications

Daily bounded command evaluates renewal thresholds 30/7/1 and expiration, creates deduplicated database notifications for recipients with permission and attempts optional sync email. Mail failure is visible and does not remove the database notification.

## Revision behavior

Project/contract/term saves create aggregate revision batches. Restore uses owning Actions and validates current term overlap, tenant references and generated history. Restore never deletes/rewrites generated expenses or exceptions and cannot recreate a duplicate source key.

## Tests

- project stage/deferred target/promotion/idempotency;
- project bucket fixtures consumed later by kernel;
- contract term overlap, auto-renew, concurrency and revisions;
- source-key uniqueness/idempotent create/update;
- system-managed versus manual/confirmed no-overwrite;
- delete allow-regeneration versus suppress;
- resume, resume-and-generate and selected-year applicability;
- restore cannot duplicate/rewrite generated history;
- tenant/permission isolation;
- notification thresholds/dedup/email failure.

Dusk is limited to term editor and regeneration-choice/history controls when browser behavior cannot be proven with Livewire tests.

## Sequence

1. project/contract schemas, enums, factories;
2. project Actions/tests;
3. contract term Money/overlap/revision Actions/tests;
4. source-key and generation exception schema;
5. synchronization and generation matrix tests;
6. deletion/suppression/resume Actions;
7. notifications;
8. policies/queries/Filament UI;
9. full accounting/tenant/browser verification.

## Post-design check

Pass. Generation remains explicit, idempotent and subordinate to the Expense aggregate.
