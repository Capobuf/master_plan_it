# Data model — Platform foundation

## Conventions

- Primary keys: unsigned bigint target IDs; immutable legacy IDs are retained only where migration requires them.
- Timestamps are stored UTC; tenant timezone is applied at presentation and business-boundary interpretation.
- Foreign-key deletes default to restrict. User and tenant deactivation preserve historical references.
- `lock_version` supports optimistic concurrency on editable business records.

## `tenants`

Semantic contract is defined by Feature 007. Required: stable ID, unique code, display name, `Active`/`Inactive`, currency, language, timezone, default VAT rate. Optional: logo, company data, address, contacts, report header/footer, legacy site identifier. Permanent deletion is unavailable.

## `users`

- Global account identity and authentication state.
- Exactly one product role: Administrator, Editor, or Viewer.
- Administrator has no tenant membership.
- Editor and Viewer have exactly one required tenant association.
- Deactivation preserves authorship and audit references.
- A multi-tenant user pivot is not part of the approved model.

## `roles`

Closed product role set: `Administrator`, `Editor`, `Viewer`. Legacy roles are migration inputs only and map according to Q-001.

## `application_settings`

Contains technical platform configuration only. Tenant business/local/report settings belong to the tenant and are not mutable global business catalogues.

## Relationships and isolation

Every tenant-bound route and aggregate requires valid tenant context. Cross-tenant references are invalid. Missing context fails closed. Administrator context is recorded separately from actor identity.

## Audit

Record actor ID, role, tenant context when applicable, operation, old/new structured values, UTC timestamp, and correlation ID. Do not log passwords, tokens, or attachment bytes.
