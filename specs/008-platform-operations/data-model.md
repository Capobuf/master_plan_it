# Data model — Feature 008 Platform operations

## Tenant (existing)

Campi usati dalla superficie delegata:

| Field | Type/current constraint | Surface rule |
|---|---|---|
| `name` | string, max 255 | view/update |
| `currency_code` | 3-char code | read-only context |
| `timezone` | valid timezone string | view/update, no historical rewrite |
| `default_vat_rate` | decimal(12,2), non-negative validated string | view/update, forward-only default |
| `budget_basis` | enum `net`/`gross` | view/update until first approval |
| `deletion_reason_required` | boolean, default false | view/update, future deletes only |
| `lock_version` | unsigned integer | required optimistic lock |

Campi esplicitamente esclusi dalla projection delegata: `id` come input, `code`,
`attachment_quota_bytes`, `language_code`, company/contact/address/logo fields e state-management
fields. La projection può includere l'id corrente come identità response, mai come selettore input.

State transition: update succeeds only from lock N to N+1. `budget_basis` becomes immutable when at
least one `approval_operations` row exists for the Tenant.

## PlatformSetting (existing singleton)

| Field | Constraint |
|---|---|
| `id` | always 1 |
| `audit_retention_months` | integer 1..120, default 24 |
| `lock_version` | optimistic lock |
| `updated_by_user_id` | global Administrator actor |

Reducing months requires reinforced confirmation and may delete only eligible `audit_events`.

## Permission / Role (existing, catalogue delta)

New tenant-scoped names:

- `tenant-settings.view`, `tenant-settings.update`
- `tenant-users.view`, `tenant-users.manage`
- `tenant-roles.view`, `tenant-roles.manage`

Retired names after forward migration:

- `platform.users.manage`
- `platform.roles.manage`
- `deletion-reason-setting.manage`

Protected names remain catalogue entries but cannot be attached to Tenant roles. Permissions are
application-defined; no endpoint creates Permission records.

## User (existing)

Tenant users retain exactly one `tenant_id`; global Administrator retains `tenant_id = null`.
Existing mutations only: create, identity update, role assignment, deactivate and password reset.
No reactivation transition is added.

## AuditEvent (existing append-only)

Tenant events have `tenant_id`; global events may be null. Reads expose id, actor label, event type,
subject identity, correlation id, safe properties and UTC occurrence timestamp. Passwords, tokens,
file payload and sensitive data are forbidden. Retention deletes only records older than the
calculated cutoff.

## DatabaseNotification (existing)

Reads are constrained by `notifiable_type` and `notifiable_id` of the authenticated User. Exposed
data is a safe normalized projection of stored notification data, read timestamp and creation
timestamp. No cross-user or global notification browser is introduced.

## Derived overview projection

Not persisted. Per Tenant: id/name/state, active/total user counts and last audit activity; optional
operational counts use only currently available non-economic sources. No Expense/Budget/Report sums.

## Economic records (existing, unchanged schema)

`expense_rows.vat_rate` and `contract_terms.vat_rate` remain persisted exact decimals together with
Net/VAT/Gross. Changing Tenant default never updates these records or their revisions.
