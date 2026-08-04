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

This interface inherits the exact ability/context/ownership/state decision procedure from `authorization.md`. Print requires `report.print`; filtered export requires `report.export-filtered`; complete selected report/year export requires `report.export-complete`; all require `report.view` and one tenant. Editor and Viewer names are seed-template examples only and never select behavior. Missing ability/context, inactive state, or foreign identity fails closed without existence disclosure.

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

Read the local plan and data model. Implement exactly shared dataset, filenames, CSV/XLSX/PDF/print behavior. Do not reuse this file as a generic abstraction for other domains; shared behavior belongs only in an explicitly listed shared helper.

## Output size and delivery

- CSV, XLSX and print have no application-defined row cap and never truncate the authorized selected scope.
- CSV/XLSX consume the canonical ordered DTO iterator without materializing the complete row collection.
- Generation writes to a request-scoped private temporary artifact; download starts only after complete finalization.
- Query/writer/storage/finalization failure returns a stable error with filter-narrowing guidance, never alters filters and exposes no partial file.
- Failure and success perform immediate artifact cleanup; cleanup failure is surfaced and safely recorded, never silently ignored.

## Performance and accessibility

On the deterministic 10,000-row fixture, verified target hosting must pass page/report p95 2 seconds, CSV 10 seconds, XLSX 20 seconds, print 10 seconds, peak PHP memory 128 MiB and 5 SQL queries per request. CI blocks parity/scope/order, memory and query regressions but records elapsed time as non-blocking evidence. Every chart has a WCAG 2.2 AA keyboard-accessible equivalent table/text view and output controls work at 360, 768 and 1280 CSS pixels in the approved browser matrix.
## Tenant clauses

Every economic export/print contains one tenant only. The protected Administrator global surface may export only the operational tenant metadata approved by Q-014 and never uses a tenant economic export contract.
