# Data model — Feature 007 Tenancy and access control

Status: `PROPOSED TARGET`  
Shared conventions: `docs/replatform/data-model-overview.md`

## `tenants`

- unique code and display name;
- state `active|inactive`;
- currency, language, timezone, default VAT;
- official Budget basis `net|gross` default Net;
- `attachment_quota_bytes` unsigned BIGINT default `2147483648`, with zero valid and no application-defined ceiling below the technical range, changed only through protected platform settings; values are exposed as normalized decimal strings when native signed-integer range is insufficient;
- `deletion_reason_required` boolean default false, changed only by global Administrator through the dedicated protected ability for an explicitly selected tenant;
- optional company, address, contacts and report branding;
- optional logo attachment/path according to file contract;
- lifecycle actors/timestamps;
- onboarding checklist state only if UI consumes it;
- `lock_version`, timestamps.

Permanent deletion unavailable.

## `users`

Feature 001 table with nullable tenant ID. Tenant users require one tenant; global Administrator accounts have none. User active state is independent from tenant state.

## Package roles/permissions

Spatie teams schema:

- permissions global and stable;
- roles nullable tenant ID;
- protected global Administrator role;
- tenant Editor/Viewer templates and custom roles;
- role assignments evaluated in current team context;
- no ordinary direct user-permission UI.

Role uniqueness follows package schema plus tenant/team key. Application Actions reject cross-tenant assignment and protected permission membership.

## Tenant context

Request/session state is not authoritative persistence for ownership. Administrator selected tenant ID may be stored in session; every request resolves and authorizes the current Tenant model. Tenant users derive tenant from User and cannot override it.

## Ownership

Every business aggregate root and explicit alternative dataset carries tenant ID. Important children also carry/derive tenant according to global model overview. Cross-tenant references are rejected before write and every query starts with tenant predicate.

## Global settings/audit/revisions/notifications

Owned by Feature 001/shared model:

- platform setting audit retention default 24;
- audit event tenant nullable for global operations;
- operational version batches tenant-owned;
- database notification includes tenant/global safe scope.

Audit export is unavailable and tenant portability excludes audit/global settings.

The attachment quota is tenant-owned business configuration and is included in tenant portability. Attachment usage is derived from Feature 003 current and immutable historical payload versions, not stored as an unaudited mutable counter. The deletion-reason flag is tenant-owned configuration and is included in tenant portability; changes apply prospectively only.

## Constraints/indexes

- tenant code unique globally;
- `(state,code)` for global list;
- attachment quota uses exact unsigned byte arithmetic with no float or application-defined maximum; lowering below usage never purges data, and zero blocks only new payload bytes;
- users `(tenant_id,is_active)`;
- package role/team indexes;
- no tenant-user pivot;
- no database cascade deleting tenant data.

## Lifecycle effects

Tenant deactivation changes only tenant state/lifecycle metadata. It does not update/delete users, business data, files, versions or audit. User deactivation preserves actor references. Open assignments are queried and handled explicitly by owning features.
