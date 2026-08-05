# Contract — Screen Shell

Feature: `001-platform-foundation`  
Purpose: layout, navigation, focus, responsive and active route behavior.

## Inputs

All input is represented by a typed Data/Filter object. IDs are target IDs; imported references retain legacy IDs separately. Money enters as normalized decimal strings. Dates use ISO `YYYY-MM-DD`. The actor and current tenant context are explicit. Authorization and tenant ownership checks occur before protected data or file metadata is returned.

## Output

Return a typed result or dataset. Domain writes return affected IDs, new `lock_version`, calculated values and audit correlation ID. Read datasets declare every column, type, ordering and total; views do not append hidden calculations.

## Preconditions and invariants

Apply the feature FR/INV IDs from `../spec.md`. Missing prerequisites produce validation errors; stale versions produce 409; invariant conflicts produce stable `MPIT_001_*` codes; permission failure produces 403 without confirming hidden record existence.

## Transaction and idempotency

Writes open one transaction inside the owning Action. Lock only cross-record consistency rows. Retrying the same idempotency/source key cannot create duplicates. Rollback removes all partial database side effects; file writes use temporary paths and finalize only after database success, with compensating cleanup on failure.

## Authorization

This interface inherits the exact ability/context/ownership/state decision procedure from `authorization.md`. Editor and Viewer names are seed-template examples only and never select behavior. Navigation visibility does not grant access; each destination reauthorizes its own stable ability and tenant boundary. Missing ability/context, inactive state, or foreign identity fails closed without existence disclosure.

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

Read the local plan and data model. Implement exactly layout, navigation, focus, responsive and active route behavior. Do not reuse this file as a generic abstraction for other domains; shared behavior belongs only in an explicitly listed shared helper.

## Accessibility and compatibility

- Every operation is keyboard reachable with visible focus, programmatic name, WCAG 2.2 AA contrast and identifiable validation errors.
- Charts expose an equivalent non-visual table or text representation using the same server-calculated values.
- The shell and feature surfaces remain usable at 360, 768 and 1280 CSS-pixel viewports.
- The browser matrix is the latest two stable Chrome, Edge and Firefox releases plus current stable Safari.
- Ordinary logout invalidates only the active session and preserves the account-wide remember token; password change/reset delete all database sessions for the affected user and rotate that token atomically.

## Audit-retention setting UX

The Administrator may enter only an integer from 1 through 120 months; initial value is 24. Lowering the value requires reinforced confirmation with a generic warning that older events may be deleted by the next prune. The UI must not calculate or display a cutoff date or count of eligible events.
## Tenant context clauses

- Current tenant name/state is always visible in side navigation and page breadcrumbs.
- Only the protected global Administrator boundary exposes tenant enter/leave controls.
- Tenant users derive their one assigned tenant and cannot switch, regardless of customized tenant-role names or assignments.
- Application shell retains Master Plan IT branding; tenant branding applies to reports/print only.
