# Data model — Platform foundation

## Conventions

- Primary keys: unsigned bigint target IDs; immutable `legacy_id` nullable unique with source type when migrated.
- Timestamps stored UTC; business dates are `date`; display timezone Europe/Rome.
- Money: `decimal(19,6)` inputs/intermediate persisted values and `decimal(19,2)` computed business results as specified.
- Foreign-key deletes default to restrict. No cascade delete for economic history.
- `lock_version` unsigned integer supports optimistic concurrency on editable business records.

### `users`

Purpose: persistence required by Platform foundation. Exact columns and migration order are defined in the plan file map. Delete behavior defaults to `restrict`; soft delete is used only for user-authored master/business records that must remain referenceable.

### `roles`

Purpose: persistence required by Platform foundation. Exact columns and migration order are defined in the plan file map. Delete behavior defaults to `restrict`; soft delete is used only for user-authored master/business records that must remain referenceable.

### `role_user`

Purpose: persistence required by Platform foundation. Exact columns and migration order are defined in the plan file map. Delete behavior defaults to `restrict`; soft delete is used only for user-authored master/business records that must remain referenceable.

### `application_settings`

Purpose: persistence required by Platform foundation. Exact columns and migration order are defined in the plan file map. Delete behavior defaults to `restrict`; soft delete is used only for user-authored master/business records that must remain referenceable.


## Relationships

Relationships are owned by the record carrying the foreign key. Required relationships are non-null after migration reconciliation. Historical references remain valid when a master record is inactive.

## Audit

Create explicit audit entries for state, funding, replacement, project stage, contract term and generated-row changes. Record actor ID, UTC timestamp, operation, old/new structured values and correlation ID. Do not audit derived report reads.

## Migration notes

All imported records retain `(legacy_doctype, legacy_id)`. Transformation errors are quarantined rather than coerced silently.
