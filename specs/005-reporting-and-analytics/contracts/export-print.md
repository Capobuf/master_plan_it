# Contract — Export Print

Feature: `005-reporting-and-analytics`  
Purpose: shared dataset, filenames, CSV/XLSX/PDF/print behavior.

## Inputs

All input is represented by a typed Data/Filter object. IDs are target IDs; imported references retain legacy IDs separately. Money enters as normalized decimal strings. Dates use ISO `YYYY-MM-DD`. The actor and current tenant context are explicit. Authorization and tenant ownership checks occur before protected data or file metadata is returned.

## Output

Return a typed result or dataset. Domain writes return affected IDs, new `lock_version`, calculated values and audit correlation ID. Read datasets declare every column, type, ordering and total; views do not append hidden calculations.

## Preconditions and invariants

Apply the feature FR/INV IDs from `../spec.md`. Missing prerequisites produce validation errors; stale versions produce 409; invariant conflicts produce stable `MPIT_005_*` codes; permission failure produces 403 without confirming hidden record existence.

## Transaction and idempotency

Writes open one transaction inside the owning Action. Lock only cross-record consistency rows. Retrying the same idempotency/source key cannot create duplicates. Rollback removes all partial database side effects; file writes use temporary paths and finalize only after database success, with compensating cleanup on failure.

## Authorization

| Ability | Administrator | Editor same tenant | Viewer same tenant | User other tenant |
|---|---:|---:|---:|---:|
| viewAny/view | Allow where contract permits, in explicit tenant context or global operational scope | Allow for assigned tenant | Allow read-only for assigned tenant | Deny |
| create/update | Allow where contract and invariant permit | Allow only where the feature-specific clause grants | Deny | Deny |
| delete/archive | Only where explicitly specified; never bypass immutable history | Only where explicitly granted; never immutable history | Deny | Deny |
| export/print | Tenant-scoped; global exports contain operational metadata only | Tenant-scoped | Tenant-scoped | Deny |
| administer/global operation | Allow | Deny | Deny | Deny |

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

Read the local plan and data model. Implement exactly shared dataset, filenames, CSV/XLSX/PDF/print behavior. Do not reuse this file as a generic abstraction for other domains; shared behavior belongs only in an explicitly listed shared helper.
## Tenant clauses

Every economic export/print contains one tenant only. Administrator global exports are limited to operational tenant metadata approved by Q-014.
