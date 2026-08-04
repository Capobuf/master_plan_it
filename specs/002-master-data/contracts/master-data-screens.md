# Contract — Master Data Screens

Feature: `002-master-data`  
Purpose: year, cost-center and vendor register/editor states.

## Inputs

All input is represented by a typed Data/Filter object. IDs are target IDs; imported references retain legacy IDs separately. Money enters as normalized decimal strings. Dates use ISO `YYYY-MM-DD`. The actor and current tenant context are explicit. Authorization and tenant ownership checks occur before protected data or file metadata is returned.

## Output

Return a typed result or dataset. Domain writes return affected IDs, new `lock_version`, calculated values and audit correlation ID. Read datasets declare every column, type, ordering and total; views do not append hidden calculations.

## Preconditions and invariants

Apply the feature FR/INV IDs from `../spec.md`. Missing prerequisites produce validation errors; stale versions produce 409; invariant conflicts produce stable `MPIT_002_*` codes; permission failure produces 403 without confirming hidden record existence.

## Transaction and idempotency

Writes open one transaction inside the owning Action. Lock only cross-record consistency rows. Retrying the same idempotency/source key cannot create duplicates. Rollback removes all partial database side effects; file writes use temporary paths and finalize only after database success, with compensating cleanup on failure.

## Authorization

This interface inherits the exact ability/context/ownership/state decision procedure from `authorization.md`. Registers require the matching `planning-year.view`, `cost-center.view`, or `vendor.view`; each lifecycle or revision action requires its distinct catalogue ability. Editor and Viewer names are seed-template examples only and never select behavior. Missing ability/context, inactive state, or foreign identity fails closed without existence disclosure.

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

Read the local plan and data model. Implement exactly year, cost-center and vendor register/editor states. Do not reuse this file as a generic abstraction for other domains; shared behavior belongs only in an explicitly listed shared helper.
## Tenant and ability clauses

- Registers and selectors contain only the current tenant's years, cost centers, and vendors.
- Planning years expose only create/deactivate/reactivate controls; their January 1/December 31 boundaries are derived, and delete/update/revision controls do not exist.
- Vendor and cost-center writes appear only with the exact create/update/delete/deactivate/reactivate/revision ability. Delete is disabled unless reference checks pass; cost-center delete additionally requires no descendants.
- Cost-center hierarchy renders at most root/child/grandchild and orders siblings by case-insensitive name then ID; no cost-center code field is displayed or accepted.
- An actor with only the matching view ability receives the same-tenant read-only surface; customized role names do not change this rule.
