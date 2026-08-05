# Implementation plan — Feature 004 Contracts and projects

Status: `PLAN COMPLETE; INTEGRATED ANALYSIS PASSED; IMPLEMENTATION READY; IMPLEMENTATION NOT STARTED`
Dependencies: Features 001–003 and 007; shared revision/audit infrastructure

## Summary

Implement projects as decision context and contracts as term-based generators of Actual rows. A generated occurrence has one immutable source key. Synchronization creates or updates only a system-managed Actual `ToConfirm`; manual modification, confirmation, or deletion of its source contract/term makes it user-authoritative. Project deletion is blocked by current linked Expenses. Project/contract/term deletion is irreversible in the application; contract/term deletion never cascades to Expenses and writes immutable deletion provenance while stopping future generation. Contracts/projects never contribute independent monetary totals.

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

- `CreateContract`, `UpdateContract`, `DeleteContract`, `DeleteContractTerm`, `RestoreContractRevision`;
- `SynchronizeContractOccurrences`;
- `DeleteGeneratedExpense` coordinating Feature 003 delete plus regeneration choice;
- `SuppressContractOccurrence`, `ResumeContractOccurrence`, `ResumeAndGenerateOccurrence`, `GenerateContractOccurrenceForYear`;
- `ConfirmActual` remains Feature 003-owned.

### Queries/UI

- `ProjectListQuery`, `ContractListQuery`, `ContractDetailQuery`, `ContractGenerationHistoryQuery`;
- one Policy per resource plus explicit generation Gates;
- operational Livewire Project/Contract list/editor/timeline/history components rendered by Blade/Preline in the shared tenant layout.

No `ContractAnnualizer` service unless tests prove a reusable calculation independent from generation; term values use shared Money/VAT services.

## Project rules

- stages: Idea, Proposed, Approved, Deferred, Rejected;
- Deferred requires same-tenant target planning year;
- promotion command moves eligible Deferred to Proposed once, under optimistic concurrency;
- project has no persisted total;
- stage is read by the economic kernel; an Actual remains primary regardless of later stage.
- delete requires `project.delete`, explicit confirmation, the tenant reason policy and zero current linked Expenses; it never cascades, detaches or reassigns Expenses and cannot be undone by restore/import.

## Contract/term transaction

Contract save receives complete intended term set with explicit deletions. It locks the contract/current terms, validates same-tenant vendor/cost center, date order, non-overlap, billing cycle and decimal values, calculates term Net/VAT/Gross and records one revision batch.

Missing existing terms are not implicitly deleted unless explicitly marked, avoiding UI serialization loss.

Explicit term deletion applies the tenant reason policy, writes a terminal tombstone, stops its future generation and atomically marks every linked current generated Expense user-authoritative with immutable contract/term/date/deletion provenance. No source key changes, no Expense is deleted and no restore/import can restore that same stable term identity.

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

`DeleteProject` locks current same-tenant Expense references and succeeds only when none remain. Deleted Expense history does not cause a cascade; minimized project tombstone/revision/audit evidence remains.

`DeleteContract` and `DeleteContractTerm` lock the source aggregate and linked current generated rows, validate the tenant deletion-reason policy, irreversibly stop future generation, mark linked rows user-authoritative and append immutable provenance. Contract/term title/IDs/date range, deletion timestamp and supplied reason are captured; source keys and Expenses remain. Every write succeeds or rolls back together. Tombstones are evidence, never a restorable state.

Each tenant owns `deletion_reason_required`, default false. Only global Administrator with protected `deletion-reason-setting.manage` may toggle it for one explicitly selected tenant; Editor and custom tenant roles never receive the ability. Reasons are trimmed, at most 500 characters, and required only when the current setting is true; setting changes never rewrite prior evidence.

`DeleteGeneratedExpense` requires explicit `allow_regeneration` boolean:

- true: delete current expense; no exception; future sync may recreate;
- false: delete and insert one generation exception for the source key.

Resume deletes the exception after authorization/audit. Resume-and-generate performs both inside one transaction. Manual-year generation validates applicability, missing source and suppression state.

## Notifications

Daily bounded command evaluates renewal thresholds 30/7/1 and expiration, creates deduplicated database notifications for recipients with permission and attempts optional sync email. Mail failure is visible and does not remove the database notification.

## Revision behavior

Current project/contract/term saves create aggregate revision batches. Restore uses owning Actions and validates current term overlap, tenant references and generated history. Restore never deletes/rewrites generated expenses or exceptions, cannot duplicate a source key, and is denied for any deleted project/contract/term or any snapshot that would restore a deleted stable term identity.

## Tests

- project stage/deferred target/promotion/idempotency;
- project bucket fixtures consumed later by kernel;
- contract term overlap, auto-renew, concurrency and revisions;
- source-key uniqueness/idempotent create/update;
- system-managed versus manual/confirmed no-overwrite;
- delete allow-regeneration versus suppress;
- resume, resume-and-generate and selected-year applicability;
- current-record restore cannot duplicate/rewrite generated history or reactivate/restore the same deleted source identity;
- project delete blocked by current Expense; contract/term delete preserves Expenses/source keys, provenance and stops generation;
- optional/required deletion reason, 500-character boundary, protected Administrator authority and explicit target-tenant denial;
- tenant/permission isolation;
- notification thresholds/dedup/email failure.

Dusk is limited to term editor and regeneration-choice/history controls when browser behavior cannot be proven with Livewire tests.

## Sequence

1. project/contract schemas, enums, factories;
2. project Actions/tests;
3. contract term Money/overlap/revision Actions/tests;
4. source-key and generation exception schema;
5. synchronization and generation matrix tests;
6. project/contract/term deletion provenance and tenant reason-setting Actions/tests;
7. generated-expense deletion/suppression/resume Actions;
8. notifications;
9. policies/queries/operational Livewire/Blade/Preline UI;
10. full accounting/tenant/browser verification.

## Post-design check

Pass. Generation remains explicit, idempotent and subordinate to the Expense aggregate.
