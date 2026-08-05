# Contract — Authorization

Feature: `001-platform-foundation`  
Purpose: ability decision procedure, policy mapping, row scoping, and deny behavior.

## Inputs

All input is represented by a typed Data/Filter object. IDs are target IDs; imported references retain legacy IDs separately. Money enters as normalized decimal strings. Dates use ISO `YYYY-MM-DD`. The actor and current tenant context are explicit. Authorization and tenant ownership checks occur before protected data or file metadata is returned.

## Output

Return a typed result or dataset. Domain writes return affected IDs, new `lock_version`, calculated values and audit correlation ID. Read datasets declare every column, type, ordering and total; views do not append hidden calculations.

## Preconditions and invariants

Apply the feature FR/INV IDs from `../spec.md`. Missing prerequisites produce validation errors; stale versions produce 409; invariant conflicts produce stable `MPIT_001_*` codes; permission failure produces 403 without confirming hidden record existence.

## Transaction and idempotency

Writes open one transaction inside the owning Action. Lock only cross-record consistency rows. Retrying the same idempotency/source key cannot create duplicates. Rollback removes all partial database side effects; file writes use temporary paths and finalize only after database success, with compensating cleanup on failure.

## Authorization decision procedure

`Allow` requires every applicable gate below. A failure denies without revealing a protected record's existence. Exact identifiers come only from `../../../docs/replatform/permission-catalogue.md`; role names never select tenant-domain behavior. Editor and Viewer are non-normative seed templates. Administrator is name-protected only at the global platform boundary and has no domain-invariant bypass. Its global role row has nullable tenant ownership; its model-role relationship uses reserved internal Spatie team key `0`, which is never a Tenant and is selected/restored only inside the protected boundary. That boundary rejects an actor, selected Tenant, or protected resource whose current primary key differs from its original persisted key. It reloads the actor and verifies Tenant/resource ownership only by the unchanged original identity before evaluating active, permission, context, and ownership eligibility. A resource identity mismatch returns the same non-disclosing not-found response as a foreign or missing resource; a selected-Tenant identity mismatch returns `TENANT_CONTEXT_REQUIRED`.

| Gate | Allow condition |
|---|---|
| Actor | Authenticated, active actor; a tenant user belongs to exactly one active tenant. |
| Operation | Actor has the exact stable ability for this operation; create/update/delete/restore/confirm/publish/generate/print/export are not implied by one another. |
| Context | Tenant operation has one explicit active `TenantContext`; protected platform operation is executed by global Administrator. |
| Ownership | Resource, parent, related identifiers, files, revisions, and output scope belong to the selected/assigned tenant. |
| State and invariants | Current state, optimistic lock, references, domain invariants, and feature-specific preconditions pass after permission allow. |
| Deny behavior | Missing ability/context, inactive state, or foreign identity fails closed before protected fields, metadata, or existence are disclosed. |

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

Read the local plan and data model. Implement exactly the authorization decision procedure, policy abilities, row scoping, and deny behavior. Do not reuse this file as a generic abstraction for other domains; shared behavior belongs only in an explicitly listed shared helper.

## Feature-specific policy rules

- `platform.tenants.*`, `platform.users.manage`, `platform.roles.manage`, `platform.settings.manage`, and `platform.audit.view-global` are protected platform abilities available only at the global Administrator boundary.
- `dashboard.view`, `audit.view`, and `notification.view` authorize their same-tenant read surfaces; absence denies regardless of the actor's tenant-role name.
- Authenticated users may change their own password under the password contract; Administrator password reset uses the protected user-management boundary and never exposes credentials.
- Ordinary logout revokes only the request's current session and preserves the account-wide remember token. Own-password change and Administrator reset revoke every database session and rotate that token for the affected persisted user; they are not aliases of ordinary logout.
- No protected or tenant ability bypasses economic, current-state, authorization, or tenant-isolation invariants.
