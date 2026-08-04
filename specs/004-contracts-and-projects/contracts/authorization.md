# Contract — Authorization

Feature: `004-contracts-and-projects`  
Purpose: ability decision procedure, policy mapping, row scoping, and deny behavior.

## Inputs

All input is represented by a typed Data/Filter object. IDs are target IDs; imported references retain legacy IDs separately. Money enters as normalized decimal strings. Dates use ISO `YYYY-MM-DD`. The actor and current tenant context are explicit. Authorization and tenant ownership checks occur before protected data or file metadata is returned.

## Output

Return a typed result or dataset. Domain writes return affected IDs, new `lock_version`, calculated values and audit correlation ID. Read datasets declare every column, type, ordering and total; views do not append hidden calculations.

## Preconditions and invariants

Apply the feature FR/INV IDs from `../spec.md`. Missing prerequisites produce validation errors; stale versions produce 409; invariant conflicts produce stable `MPIT_004_*` codes; permission failure produces 403 without confirming hidden record existence.

## Transaction and idempotency

Writes open one transaction inside the owning Action. Lock only cross-record consistency rows. Retrying the same idempotency/source key cannot create duplicates. Rollback removes all partial database side effects; file writes use temporary paths and finalize only after database success, with compensating cleanup on failure.

## Authorization decision procedure

`Allow` requires every applicable gate below. A failure denies without revealing a protected record's existence. Exact identifiers come only from `../../../docs/replatform/permission-catalogue.md`; role names never select tenant-domain behavior. Editor and Viewer are non-normative seed templates. Administrator is name-protected only at the global platform boundary and has no domain-invariant bypass.

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

- Project reads, lifecycle changes, revision view, and revision restore require the matching `project.*` ability.
- Contract/term reads, lifecycle changes, revision view, and revision restore require the matching `contract.*` ability.
- Project delete requires `project.delete`; current linked Expenses deny it before mutation.
- Contract delete requires `contract.delete`; explicit term deletion uses `contract.update`. Neither operation deletes a generated Expense.
- protected `deletion-reason-setting.manage` is available only to global Administrator and controls only one explicitly selected tenant's optional/required deletion-reason toggle; it grants no project/contract deletion ability.
- Generate, suppress, resume, and generation-history operations require their distinct `contract.generate-occurrence`, `contract.suppress-generation`, `contract.resume-generation`, or `contract.view-generation-history` ability.
- Generated source identities and history already used by generated expenses are system-controlled.
- Generation commands and scheduler iterations set/reset explicit tenant context and reapply ownership, ability, source-key, and current-state rules for every tenant; no role-name shortcut is permitted.
