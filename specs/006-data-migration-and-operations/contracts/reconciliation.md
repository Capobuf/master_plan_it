# Contract — Reconciliation

Feature: `006-data-migration-and-operations`  
Purpose: counts, sums, hashes, tolerances and sign-off gates.

## Inputs

All input is represented by a typed Data/Filter object. IDs are target IDs; imported references retain legacy IDs separately. Money enters as normalized decimal strings. Dates use ISO `YYYY-MM-DD`. The actor and current tenant context are explicit. Authorization and tenant ownership checks occur before protected data or file metadata is returned.

## Output

Return a typed result or dataset. Domain writes return affected IDs, new `lock_version`, calculated values and audit correlation ID. Read datasets declare every column, type, ordering and total; views do not append hidden calculations.

## Preconditions and invariants

Apply the feature FR/INV IDs from `../spec.md`. Missing prerequisites produce validation errors; stale versions produce 409; invariant conflicts produce stable `MPIT_006_*` codes; permission failure produces 403 without confirming hidden record existence.

## Transaction and idempotency

Writes open one transaction inside the owning Action. Lock only cross-record consistency rows. Retrying the same idempotency/source key cannot create duplicates. Rollback removes all partial database side effects; file writes use temporary paths and finalize only after database success, with compensating cleanup on failure.

## Authorization

This interface inherits the protected-platform and tenant-context decision procedure from `../../007-tenancy-and-access-control/contracts/authorization.md`. Create/dry-run/apply/exclusion approval/reconciliation view or export require protected `platform.migration.run`; tenant role abilities cannot grant migration source or global operation access. The selected target tenant is explicit and immutable. Missing protected ability/context, inactive state, or foreign identity fails closed without existence disclosure.

## Audit/logging

Record business state changes, actor, old/new values and correlation ID. Do not log passwords, session tokens, full attachments or unredacted migration source rows. Expected validation failures are not error logs.

## Test contract

1. valid input returns/persists exact expected values;
2. each invariant has one focused failure test;
3. an actor missing the protected ability or valid target tenant cannot read/write outside its scope;
4. stale version and duplicate idempotency key are deterministic;
5. transaction rollback leaves no partial records/files;
6. any screen/export using this contract matches the same dataset.

## Feature-specific clauses

Read the local plan and data model. Implement exactly counts, sums, hashes, tolerances and sign-off gates. Do not reuse this file as a generic abstraction for other domains; shared behavior belongs only in an explicitly listed shared helper.

## Feature-specific policy rules

Protected `platform.migration.run` at the global Administrator boundary is required to create, execute, approve, view, or export migration reconciliation. A migration run has one immutable target tenant. Tenant roles cannot receive migration source-row or global-operation access.
