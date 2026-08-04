# Contract — Contract Screen

Feature: `004-contracts-and-projects`  
Purpose: contract header, terms timeline, renewal, sync state and errors.

## Inputs

All input is represented by a typed Data/Filter object. IDs are target IDs; imported references retain legacy IDs separately. Money enters as normalized decimal strings. Dates use ISO `YYYY-MM-DD`. The actor and current tenant context are explicit. Authorization and tenant ownership checks occur before protected data or file metadata is returned.

## Output

Return a typed result or dataset. Domain writes return affected IDs, new `lock_version`, calculated values and audit correlation ID. Read datasets declare every column, type, ordering and total; views do not append hidden calculations.

## Preconditions and invariants

Apply the feature FR/INV IDs from `../spec.md`. Missing prerequisites produce validation errors; stale versions produce 409; invariant conflicts produce stable `MPIT_004_*` codes; permission failure produces 403 without confirming hidden record existence.

## Transaction and idempotency

Writes open one transaction inside the owning Action. Lock only cross-record consistency rows. Retrying the same idempotency/source key cannot create duplicates. Rollback removes all partial database side effects; file writes use temporary paths and finalize only after database success, with compensating cleanup on failure.

## Authorization

This interface inherits the exact ability/context/ownership/state decision procedure from `authorization.md`. Read/lifecycle/revision/generation controls require their distinct `contract.*` catalogue abilities. Editor and Viewer names are seed-template examples only and never select behavior. Missing ability/context, inactive state, or foreign identity fails closed without existence disclosure.

## Audit/logging

Record business state changes, actor, old/new values and correlation ID. Do not log passwords, session tokens, full attachments or unredacted migration source rows. Expected validation failures are not error logs.

## Test contract

1. valid input returns/persists exact expected values;
2. each invariant has one focused failure test;
3. an actor missing the exact ability or valid tenant context cannot read/write outside its scope;
4. stale version and duplicate idempotency key are deterministic;
5. transaction rollback leaves no partial records/files;
6. any screen/export using this contract matches the same dataset.

## Feature-specific clauses

Read the local plan and data model. Implement exactly contract header, terms timeline, renewal, sync state and errors. Do not reuse this file as a generic abstraction for other domains; shared behavior belongs only in an explicitly listed shared helper.
## Tenant and ability clauses

Vendor, cost center, project, terms, renewals, attachments, and linked/generated expenses are same-tenant only. An actor with the exact lifecycle/generation ability may invoke only that operation; generated source identity and used history remain system-controlled regardless of permission.

Contract/term deletion requires the matching `contract.delete` or `contract.update` ability, explicit selection and the current tenant deletion-reason policy. The prompt is always shown; its trimmed value is optional by default, required only when the toggle is enabled, and never exceeds 500 characters.

Deleting a contract or explicit term irreversibly stops its future generation but never deletes a generated Expense. Linked current occurrences atomically become user-authoritative and retain immutable contract/term/date/deletion provenance plus unchanged source keys. UI, Action, revision restore and import cannot reactivate or restore the same deleted logical source identity. Partial provenance, partial deletion and cascade Expense deletion are forbidden.
