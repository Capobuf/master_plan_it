# Contract — Hosting

Feature: `001-platform-foundation`  
Purpose: shared hosting prerequisites, document root, build artifact, cron, storage, deploy and rollback.

## Inputs

All input is represented by a typed Data/Filter object. IDs are target IDs; imported references retain legacy IDs separately. Money enters as normalized decimal strings. Dates use ISO `YYYY-MM-DD`. The actor is explicit and authorization occurs before protected data is returned.

## Output

Return a typed result or dataset. Domain writes return affected IDs, new `lock_version`, calculated values and audit correlation ID. Read datasets declare every column, type, ordering and total; views do not append hidden calculations.

## Preconditions and invariants

Apply the feature FR/INV IDs from `../spec.md`. Missing prerequisites produce validation errors; stale versions produce 409; invariant conflicts produce stable `MPIT_001_*` codes; permission failure produces 403 without confirming hidden record existence.

## Transaction and idempotency

Writes open one transaction inside the owning Action. Lock only cross-record consistency rows. Retrying the same idempotency/source key cannot create duplicates. Rollback removes all partial database side effects; file writes use temporary paths and finalize only after database success, with compensating cleanup on failure.

## Authorization

| Ability | Administrator | Administrator | Editor | Viewer |
|---|---:|---:|---:|---:|
| viewAny/view | yes | yes | yes, scoped | yes, scoped |
| create/update | yes | yes | only where feature spec grants | no |
| delete | yes | yes | no unless verified current permission is explicitly preserved | no |
| export/print | yes | yes | yes, scoped | yes, scoped |
| administer | yes | feature-specific | no | no |

## Audit/logging

Record business state changes, actor, old/new values and correlation ID. Do not log passwords, session tokens, full attachments or unredacted migration source rows. Expected validation failures are not error logs.

## Test contract

1. valid input returns/persists exact expected values;
2. each invariant has one focused failure test;
3. unauthorized role cannot read/write outside its scope;
4. stale version and duplicate idempotency key are deterministic;
5. transaction rollback leaves no partial records/files;
6. any screen/export using this contract matches the same dataset.

## Feature-specific clauses

Read the local plan and data model. Implement exactly shared hosting prerequisites, document root, build artifact, cron, storage, deploy and rollback. Do not reuse this file as a generic abstraction for other domains; shared behavior belongs only in an explicitly listed shared helper.
