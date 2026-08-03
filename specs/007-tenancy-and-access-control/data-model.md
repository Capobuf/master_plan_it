# Data model — Tenancy and access control

Status: `CLARIFIED LOGICAL MODEL — PHYSICAL PLAN REQUIRED`

## `tenants`

Required semantic attributes:

- stable identifier and unique code;
- display name;
- `Active` or `Inactive` state;
- currency, language, timezone, default VAT rate;
- optional logo, company data, address, contacts, report header/footer;
- creation, deactivation, and reactivation audit metadata;
- optional legacy site identifier;
- timestamps and optimistic `lock_version`.

Permanent tenant deletion is unavailable.

## `users`

- global account identity;
- email and password hash;
- active/deactivated state;
- nullable `tenant_id`: null only for protected global Administrator; required for tenant users;
- no self-service password-recovery requirement;
- timestamps and `lock_version`.

A tenant user belongs to exactly one tenant. Historical actor references are preserved after deactivation. Passwords/hashes are excluded from audit, revisions, notifications, and tenant export.

## Roles and permissions

The approved direction uses the standard schema of the selected Laravel permission package with tenant/team scoping enabled.

Logical requirements:

- one protected global `Administrator` role outside tenant customization;
- tenant-scoped roles, including seeded `Editor` and `Viewer` templates;
- one or more tenant roles per tenant user;
- additive permissions;
- stable permission catalogue defined by application capabilities;
- no tenant role assignment for another tenant;
- no tenant-manageable permission for protected platform operations or invariant bypass;
- role/permission changes audited with actor and tenant context.

The physical use of package `team_id`/`tenant_id`, model morphs, cache reset, and Filament tenancy integration is decided and tested in `/speckit.plan`.

## Tenant ownership

Every tenant business aggregate, operational revision, named budget version, scenario, attachment, generation exception, notification, and tenant audit event has unambiguous tenant ownership. Child records inherit and validate ownership through their aggregate root. Cross-tenant foreign references are invalid before persistence.

## Global platform settings

The physical storage is selected in `/speckit.plan`, using one existing typed platform-setting mechanism rather than a dedicated audit-settings subsystem.

Required setting:

| Key | Scope | Default | Write authority | Semantics |
|---|---|---:|---|---|
| `audit_retention_months` | installation-wide | `24` | Administrator only | current value used by the next audit-retention execution |

The setting keeps value, last modifying actor, timestamp, and optimistic version according to the platform-setting contract. Lowering the value requires reinforced confirmation. Increasing it does not recreate previously removed audit events.

## Tenant settings

Tenant-specific settings include:

- financial locale and default VAT;
- report branding and company data;
- templates/categories where feature contracts permit;
- notification configuration permitted by the launch contract;
- report configuration;
- optional onboarding-checklist completion indicators.

Checklist indicators are guidance only and do not create an alternative domain state. Audit retention is global, not tenant-specific.

## Audit events

Minimum logical columns:

- `id`;
- nullable `tenant_id` for global events;
- `actor_id` and actor-context label;
- `event_type`;
- subject type and nullable subject ID;
- minimized old/new or event properties;
- correlation ID;
- timestamp.

The retention command calculates its cutoff from the current global `audit_retention_months` value. It deletes eligible audit events only; it does not delete current business records, named budget versions, or required logical revision identity. Audit export is not implemented at launch and audit events are excluded from tenant portability packages.

## Operational revisions

Operational revision storage is supplied by the approved versioning direction, with application-owned metadata where required:

- tenant ownership;
- versionable type and ID;
- revision number;
- operation (`created`, `updated`, `restored`, `deleted`);
- snapshot or diff according to approved package strategy;
- actor and timestamp;
- `revision_batch_uuid` for aggregate operations;
- optional `restored_from_revision_id`;
- minimized deleted-record tombstone data.

Revision storage is never queried as current domain state.

## Notifications

Database notifications must preserve recipient, event type, tenant/global scope, subject identity, threshold/date, deduplication key, read timestamp, and safe display payload. Email delivery result may be recorded separately; no silent retry loop or queue-state model is required.

## Legacy identifiers

Imported records preserve stable Frappe identifiers for reconciliation. Identity uniqueness is `(tenant_id, source_type, legacy_id)`. A legacy identifier never substitutes tenant ownership. Collision and unassignable-row state belongs to migration staging/quarantine, not current business tables.
