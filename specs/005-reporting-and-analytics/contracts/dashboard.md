# Contract — Dashboard

Feature: `005-reporting-and-analytics`  
Purpose: KPI placement, questions, drill-down and empty states.

## Inputs

All input is represented by a typed Data/Filter object. IDs are target IDs; imported references retain legacy IDs separately. Money enters as normalized decimal strings. Dates use ISO `YYYY-MM-DD`. The actor and current tenant context are explicit. Authorization and tenant ownership checks occur before protected data or file metadata is returned.

## Output

Return a typed result or dataset. Domain writes return affected IDs, new `lock_version`, calculated values and audit correlation ID. Read datasets declare every column, type, ordering and total; views do not append hidden calculations.

## Preconditions and invariants

Apply the feature FR/INV IDs from `../spec.md`. Missing prerequisites produce validation errors; stale versions produce 409; invariant conflicts produce stable `MPIT_005_*` codes; permission failure produces 403 without confirming hidden record existence.

## Transaction and idempotency

Writes open one transaction inside the owning Action. Lock only cross-record consistency rows. Retrying the same idempotency/source key cannot create duplicates. Rollback removes all partial database side effects; file writes use temporary paths and finalize only after database success, with compensating cleanup on failure.

## Authorization

This interface inherits the exact ability/context/ownership/state decision procedure from `authorization.md`. Tenant dashboard and current Budget surfaces require `dashboard.view` and `budget.view` respectively. The global operational overview remains a separate protected Administrator surface. Editor and Viewer names are seed-template examples only and never select behavior. Missing ability/context, inactive state, or foreign identity fails closed without existence disclosure.

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

Read the local plan and data model. Implement exactly KPI placement, questions, drill-down and empty states. Do not reuse this file as a generic abstraction for other domains; shared behavior belongs only in an explicitly listed shared helper.
## Tenant clauses

Tenant dashboard datasets contain exactly one tenant. The separate Administrator global overview contains only tenant state, role counts, last activity, entry action, operational alerts, renewals, and import/migration errors; it contains no combined economic values.

The tenant current-Budget surface is a React/TailAdmin page in the shared Inertia layout. It renders minimum filters, KPI, equivalent table, one useful Chart.js chart and loading/empty/error states from the same server dataset. React mount/unmount owns Chart.js lifecycle per INV-PLT-008, and no browser code calculates economics.
