# Data model — Feature 001 Platform foundation

Status: `PROPOSED TARGET`  
Shared conventions: `docs/replatform/data-model-overview.md`

## Owned tables

### `platform_settings`

Singleton:

- `id` fixed 1;
- `audit_retention_months` unsigned smallint default 24, validated and constrained to integer values 1 through 120;
- `lock_version` default 1;
- `updated_by_user_id` nullable FK;
- timestamps.

Only Administrator updates. Lowering requires reinforced confirmation in Action.

### `users`

- unsigned bigint ID;
- nullable `tenant_id` FK, null for global platform users;
- name, globally unique email, password hash;
- `is_active` boolean;
- `lock_version`;
- timestamps.

Tenant users require tenant ID. Protected Administrator role is global through package roles; role name is not stored as a user enum.

### `audit_events`

- nullable tenant ID;
- nullable actor user ID and actor label snapshot;
- event type;
- nullable subject morph type/ID;
- correlation UUID/string indexed;
- minimized JSON properties;
- `occurred_at` indexed.

Append-only except explicit retention deletion. No password/token/file payload. No `expires_at`; cutoff derives from platform setting.

### Laravel notifications

Use native table with safe payload. Add indexed `deduplication_key` through application migration if native payload-only indexing is insufficient.

### Package RBAC

Publish Spatie tables after enabling teams with `team_foreign_key=tenant_id`. Roles are nullable-tenant; permissions global. Direct permission assignment to users is not exposed.

## Cross-feature relation

`tenants` is Feature 007-owned. User tenant FK is added only after tenant migration exists, or Feature 001/007 migrations are ordered in the same foundation phase.

## Constraints

- tenant user cannot have null tenant;
- global Administrator account cannot receive tenant membership;
- platform setting singleton enforced by seeder/Action and fixed PK;
- audit JSON size bounded by Action validation;
- email uniqueness case normalization defined in Action/database collation;
- user deactivation preserves FK references.

## Indexes

- users: unique normalized email, `(tenant_id,is_active)`;
- audit: `(occurred_at,id)`, `(tenant_id,occurred_at)`, correlation ID;
- notifications: `(notifiable_type,notifiable_id,read_at)` and dedup key.
